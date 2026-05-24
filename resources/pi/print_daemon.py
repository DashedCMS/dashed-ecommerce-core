#!/usr/bin/env python3
"""DashedCMS print daemon. Polls API, claims jobs, prints via CUPS."""

from __future__ import annotations

import logging
import logging.handlers
import os
import signal
import subprocess
import sys
import time
from pathlib import Path

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


def load_config() -> dict:
    with CONFIG_PATH.open("r", encoding="utf-8") as f:
        return yaml.safe_load(f)


def api(cfg: dict, method: str, path: str, **kwargs) -> requests.Response:
    headers = kwargs.pop("headers", {})
    headers["Authorization"] = f"Bearer {cfg['token']}"
    headers["Accept"] = "application/json"
    return requests.request(method, f"{cfg['api_url']}{path}", headers=headers, timeout=15, **kwargs)


def handle_signal(signum, frame):
    global shutdown
    shutdown = True


def print_pdf(cfg: dict, pdf_path: Path, logger: logging.Logger) -> bool:
    try:
        result = subprocess.run(
            ["lp", "-d", cfg["cups_printer"], str(pdf_path)],
            capture_output=True,
            text=True,
            timeout=30,
        )
        if result.returncode != 0:
            logger.error("lp failed: rc=%s stderr=%s", result.returncode, result.stderr.strip())
            return False
        logger.info("lp ok: %s", result.stdout.strip())
        return True
    except subprocess.TimeoutExpired:
        logger.error("lp timed out for %s", pdf_path)
        return False
    except Exception as exc:
        logger.exception("lp exception: %s", exc)
        return False


def process_job(cfg: dict, job: dict, logger: logging.Logger) -> None:
    ulid = job["ulid"]
    claim = api(cfg, "POST", f"/api/print/{ulid}/claim")
    if claim.status_code == 409:
        logger.info("Job %s already claimed by other Pi, skipping", ulid)
        return
    if claim.status_code != 200:
        logger.warning("Claim %s returned %s", ulid, claim.status_code)
        return

    claimed = claim.json()
    pdf_url = claimed["pdf_url"]
    pdf_response = api(cfg, "GET", pdf_url.replace(cfg["api_url"], ""))
    if pdf_response.status_code != 200:
        logger.error("Failed to fetch PDF for %s: %s", ulid, pdf_response.status_code)
        api(cfg, "POST", f"/api/print/{ulid}/failed", json={"error_message": f"PDF fetch HTTP {pdf_response.status_code}"})
        return

    tmp_path = Path(f"/tmp/print-{ulid}.pdf")
    tmp_path.write_bytes(pdf_response.content)

    if print_pdf(cfg, tmp_path, logger):
        api(cfg, "POST", f"/api/print/{ulid}/done")
    else:
        api(cfg, "POST", f"/api/print/{ulid}/failed", json={"error_message": "lp print failed (zie /var/log/dashedcms-printer.log)"})

    try:
        tmp_path.unlink(missing_ok=True)
    except Exception:
        pass


def main() -> int:
    cfg = load_config()
    logger = setup_logging(cfg.get("log_level", "INFO"))
    logger.info("DashedCMS print daemon starting, api=%s printer=%s", cfg["api_url"], cfg["cups_printer"])

    signal.signal(signal.SIGTERM, handle_signal)
    signal.signal(signal.SIGINT, handle_signal)

    interval = int(cfg.get("poll_interval_seconds", 5))
    last_ping = 0.0

    while not shutdown:
        try:
            now = time.time()
            if now - last_ping > 30:
                api(cfg, "POST", "/api/print/ping")
                last_ping = now

            resp = api(cfg, "GET", "/api/print/pending")
            if resp.status_code == 200:
                for job in resp.json():
                    if shutdown:
                        break
                    process_job(cfg, job, logger)
            else:
                logger.warning("pending returned %s", resp.status_code)

        except requests.RequestException as exc:
            logger.warning("network error: %s", exc)
        except Exception as exc:
            logger.exception("unexpected error: %s", exc)

        for _ in range(interval):
            if shutdown:
                break
            time.sleep(1)

    logger.info("Shutdown")
    return 0


if __name__ == "__main__":
    sys.exit(main())
