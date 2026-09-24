{{--
    Eigen, afgeschermde stijl voor de offertepagina's. Niet via Tailwind: niet
    elk klantproject scant de views in vendor/dashed, en dan zou de pagina in
    zo'n project zonder opmaak verschijnen.
--}}
<style>
    .dq { --dq-brand: {{ $color }}; --dq-ink: #18181b; --dq-text: #3f3f46; --dq-muted: #71717a; --dq-line: #e4e4e7; --dq-soft: #f4f4f5; color: var(--dq-text); font-size: 15px; line-height: 1.55; padding: clamp(24px, 5vw, 64px) 16px; background: #fafafa; }
    .dq * { box-sizing: border-box; }
    .dq [x-cloak] { display: none !important; }
    .dq-wrap { max-width: 860px; margin: 0 auto; }
    .dq-card { background: #fff; border: 1px solid var(--dq-line); border-radius: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.04); overflow: hidden; }
    .dq-card + .dq-card { margin-top: 20px; }
    .dq-head { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; justify-content: space-between; padding: 28px clamp(20px, 4vw, 40px); border-top: 5px solid var(--dq-brand); }
    .dq-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--dq-brand); margin: 0; }
    .dq-h1, .dq-h2 { font-family: inherit; letter-spacing: normal; }
    .dq-h1 { font-size: clamp(24px, 3.2vw, 34px); line-height: 1.15; color: var(--dq-ink); font-weight: 700; margin: 6px 0 0; }
    .dq-sub { color: var(--dq-muted); margin: 6px 0 0; }
    .dq-badges { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .dq-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; background: var(--dq-soft); padding: 5px 12px; font-size: 13px; color: var(--dq-text); }
    .dq-link { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid var(--dq-line); padding: 5px 12px; font-size: 13px; color: var(--dq-ink); text-decoration: none; }
    .dq-link:hover { border-color: var(--dq-brand); color: var(--dq-brand); }
    .dq-body { padding: 0 clamp(20px, 4vw, 40px) 32px; }
    .dq-parties { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; padding: 20px 0 8px; border-top: 1px solid var(--dq-line); }
    .dq-parties p { margin: 0; }
    .dq-parties b { color: var(--dq-ink); }
    .dq-parties .dq-eyebrow { margin-bottom: 6px; }
    .dq-text p { margin: 14px 0 0; }
    .dq-text ul { margin: 12px 0 0; padding-left: 20px; list-style: disc; }
    .dq-text li { margin: 6px 0; }
    .dq-text b { color: var(--dq-ink); }
    .dq-h2 { font-size: 20px; color: var(--dq-ink); font-weight: 700; margin: 0; }
    .dq-lines { margin-top: 24px; border: 1px solid var(--dq-line); border-radius: 12px; overflow: hidden; }
    .dq-lines-head { display: grid; grid-template-columns: 1fr 70px 130px; gap: 12px; background: var(--dq-brand); color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 10px 16px; }
    .dq-line { display: grid; grid-template-columns: 1fr 70px 130px; gap: 12px; padding: 16px; border-top: 1px solid var(--dq-line); align-items: start; }
    .dq-lines-head + .dq-line { border-top: 0; }
    .dq-line.is-selectable { cursor: pointer; }
    .dq-line.is-selectable:hover { background: #fcfcfc; }
    .dq-line.is-off { color: #a1a1aa; }
    .dq-line.is-off .dq-name { color: #a1a1aa; }
    .dq-line-main { display: flex; gap: 12px; align-items: flex-start; }
    .dq-line-main input { margin-top: 4px; width: 18px; height: 18px; accent-color: var(--dq-brand); flex: none; }
    .dq-name { font-weight: 600; color: var(--dq-ink); margin: 0; }
    .dq-desc { font-size: 14px; color: var(--dq-muted); margin: 4px 0 0; }
    .dq-pill { display: inline-block; margin-top: 8px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--dq-brand); border: 1px solid var(--dq-brand); border-radius: 999px; padding: 1px 8px; }
    .dq-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .dq-totals { margin: 20px 0 0 auto; max-width: 340px; }
    .dq-totals-row { display: flex; justify-content: space-between; gap: 16px; padding: 3px 16px; color: var(--dq-muted); }
    .dq-totals-row span:last-child { color: var(--dq-text); font-variant-numeric: tabular-nums; }
    .dq-grand { display: flex; justify-content: space-between; gap: 16px; margin-top: 10px; background: var(--dq-brand); color: #fff; border-radius: 12px; padding: 14px 16px; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .dq-section { padding: 28px clamp(20px, 4vw, 40px) 32px; }
    .dq-field { display: block; margin-top: 18px; }
    .dq-field > span { display: block; font-size: 14px; font-weight: 600; color: var(--dq-ink); margin-bottom: 6px; }
    .dq-input { width: 100%; border: 1px solid #d4d4d8; border-radius: 10px; padding: 10px 12px; font: inherit; color: var(--dq-ink); background: #fff; }
    .dq-input:focus { outline: 2px solid var(--dq-brand); outline-offset: 1px; border-color: transparent; }
    .dq-pad { position: relative; border: 1.5px dashed #d4d4d8; border-radius: 12px; background: #fff; height: 180px; touch-action: none; }
    .dq-pad.is-signed { border-style: solid; border-color: var(--dq-brand); }
    .dq-pad canvas { position: absolute; inset: 0; width: 100%; height: 100%; cursor: crosshair; }
    .dq-pad-hint { position: absolute; left: 0; right: 0; bottom: 38px; text-align: center; color: #a1a1aa; font-size: 14px; pointer-events: none; }
    .dq-pad-base { position: absolute; left: 24px; right: 24px; bottom: 30px; border-bottom: 1px solid var(--dq-line); pointer-events: none; }
    .dq-pad-clear { position: absolute; top: 8px; right: 8px; font-size: 13px; background: #fff; border: 1px solid var(--dq-line); border-radius: 999px; padding: 3px 10px; cursor: pointer; color: var(--dq-text); }
    .dq-check { display: flex; gap: 10px; align-items: flex-start; margin-top: 18px; font-size: 14px; cursor: pointer; }
    .dq-check input { margin-top: 3px; width: 18px; height: 18px; accent-color: var(--dq-brand); flex: none; }
    .dq-error { color: #dc2626; font-size: 13px; margin: 6px 0 0; }
    .dq-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 22px; background: var(--dq-brand); color: #fff; border: 0; border-radius: 10px; padding: 13px 26px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
    .dq-btn:hover { filter: brightness(1.08); }
    .dq-btn[disabled] { opacity: .6; cursor: wait; }
    .dq-btn-ghost { background: #fff; color: var(--dq-ink); border: 1px solid #d4d4d8; }
    .dq-textbtn { background: none; border: 0; padding: 0; font: inherit; font-size: 14px; color: var(--dq-muted); text-decoration: underline; cursor: pointer; }
    .dq-reject { margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--dq-line); }
    .dq-center { text-align: center; }
    @media (max-width: 560px) {
        .dq-lines-head { display: none; }
        .dq-line { grid-template-columns: 1fr auto; }
        .dq-line .dq-qty { display: none; }
    }
</style>
