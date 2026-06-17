#!/usr/bin/env python3
"""DashedCMS print daemon. Polls API per configured printer, claims jobs, prints via CUPS."""

from __future__ import annotations

import logging
import logging.handlers
import os
import signal
import subprocess
import sys
import time
from pathlib import Path
from typing import Any

import requests
import yaml

CONFIG_PATH = Path(os.environ.get("DASHEDCMS_PRINTER_CONFIG", "/opt/dashedcms-printer/config.yaml"))
LOG_PATH = Path(os.environ.get("DASHEDCMS_PRINTER_LOG", "/var/log/dashedcms-printer.log"))

shutdown = False


def setup_logging(level: str) -> logging.Logger:
    logger = logging.getLogger("dashedcms-printer")
    logger.setLevel(getattr(logging, level.upper(), logging.INFO))
    handler = logging.handlers.RotatingFileHandler(LOG_PATH, maxBytes=10 * 1024 * 1024, backupCount=5)
    handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
    logger.addHandler(handler)
    logger.addHandler(logging.StreamHandler(sys.stdout))
    return logger


def load_config() -> dict[str, Any]:
    with CONFIG_PATH.open("r", encoding="utf-8") as f:
        cfg = yaml.safe_load(f) or {}

    if "printers" not in cfg:
        if "token" in cfg and "cups_printer" in cfg:
            cfg["printers"] = [{
                "token": cfg["token"],
                "cups_printer": cfg["cups_printer"],
            }]
        else:
            cfg["printers"] = []

    return cfg


def api(cfg: dict, token: str, method: str, path: str, **kwargs) -> requests.Response:
    headers = kwargs.pop("headers", {})
    headers["Authorization"] = f"Bearer {token}"
    headers["Accept"] = "application/json"
    return requests.request(method, f"{cfg['api_url']}{path}", headers=headers, timeout=15, **kwargs)


def handle_signal(signum, frame):
    global shutdown
    shutdown = True


def ensure_printer_ready(cups_printer: str, logger: logging.Logger) -> None:
    """Hervat een door CUPS gepauzeerde/gestopte printer. Best effort: faalt dit,
    dan loggen we het en proberen we alsnog te printen."""
    for cmd in (["cupsenable", cups_printer], ["cupsaccept", cups_printer]):
        try:
            subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        except Exception as exc:
            logger.warning("%s faalde voor %s: %s", cmd[0], cups_printer, exc)


def print_pdf(cups_printer: str, pdf_path: Path, logger: logging.Logger) -> bool:
    ensure_printer_ready(cups_printer, logger)
    try:
        result = subprocess.run(
            ["lp", "-d", cups_printer, str(pdf_path)],
            capture_output=True,
            text=True,
            timeout=20,
        )
        if result.returncode != 0:
            logger.error("lp failed for %s: rc=%s stderr=%s", cups_printer, result.returncode, result.stderr.strip())
            return False
        logger.info("lp ok on %s: %s", cups_printer, result.stdout.strip())
        return True
    except subprocess.TimeoutExpired:
        logger.error("lp timed out for %s on %s", pdf_path, cups_printer)
        return False
    except Exception as exc:
        logger.exception("lp exception on %s: %s", cups_printer, exc)
        return False


def process_job(cfg: dict, printer_cfg: dict, job: dict, logger: logging.Logger) -> None:
    token = printer_cfg["token"]
    cups_printer = printer_cfg["cups_printer"]
    ulid = job["ulid"]

    claim = api(cfg, token, "POST", f"/api/print/{ulid}/claim")
    if claim.status_code == 409:
        logger.info("Job %s already claimed by another daemon, skipping", ulid)
        return
    if claim.status_code != 200:
        logger.warning("Claim %s for %s returned %s", ulid, cups_printer, claim.status_code)
        return

    claimed = claim.json()
    pdf_url = claimed["pdf_url"]
    pdf_response = api(cfg, token, "GET", pdf_url.replace(cfg["api_url"], ""))
    if pdf_response.status_code != 200:
        logger.error("PDF fetch for %s failed: %s", ulid, pdf_response.status_code)
        api(cfg, token, "POST", f"/api/print/{ulid}/failed",
            json={"error_message": f"PDF fetch HTTP {pdf_response.status_code}"})
        return

    tmp_path = Path(f"/tmp/print-{ulid}.pdf")
    tmp_path.write_bytes(pdf_response.content)

    if print_pdf(cups_printer, tmp_path, logger):
        api(cfg, token, "POST", f"/api/print/{ulid}/done")
    else:
        api(cfg, token, "POST", f"/api/print/{ulid}/failed",
            json={"error_message": f"lp print failed on {cups_printer} (zie daemon log)"})

    try:
        tmp_path.unlink(missing_ok=True)
    except Exception:
        pass


def main() -> int:
    cfg = load_config()
    logger = setup_logging(cfg.get("log_level", "INFO"))

    printers = cfg.get("printers", [])
    if not printers:
        logger.error("Geen printers geconfigureerd in config.yaml. Daemon stopt.")
        return 1

    logger.info("DashedCMS print daemon starting, api=%s, %d printer(s)", cfg["api_url"], len(printers))
    for p in printers:
        token_hint = (p.get("token") or "")[:8]
        logger.info("  - %s (token %s...)", p.get("cups_printer", "?"), token_hint)

    signal.signal(signal.SIGTERM, handle_signal)
    signal.signal(signal.SIGINT, handle_signal)

    interval = int(cfg.get("poll_interval_seconds", 5))
    last_ping: dict[str, float] = {}

    while not shutdown:
        for printer_cfg in printers:
            if shutdown:
                break
            token = printer_cfg.get("token")
            cups_printer = printer_cfg.get("cups_printer", "?")
            if not token or not cups_printer:
                continue
            key = token[:12]
            try:
                now = time.time()
                if now - last_ping.get(key, 0.0) > 30:
                    api(cfg, token, "POST", "/api/print/ping")
                    last_ping[key] = now

                resp = api(cfg, token, "GET", "/api/print/pending")
                if resp.status_code == 200:
                    for job in resp.json():
                        if shutdown:
                            break
                        process_job(cfg, printer_cfg, job, logger)
                else:
                    logger.warning("pending for %s returned %s", cups_printer, resp.status_code)
            except requests.RequestException as exc:
                logger.warning("network error for %s: %s", cups_printer, exc)
            except Exception as exc:
                logger.exception("unexpected error for %s: %s", cups_printer, exc)

        for _ in range(interval):
            if shutdown:
                break
            time.sleep(1)

    logger.info("Shutdown")
    return 0


if __name__ == "__main__":
    sys.exit(main())
