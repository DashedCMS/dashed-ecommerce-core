@once
<style>
    .dpr { --dpr-star: #f59e0b; --dpr-muted: #6b7280; --dpr-border: #e5e7eb; --dpr-accent: #111827; font-size: 15px; line-height: 1.5; color: inherit; }
    .dpr * { box-sizing: border-box; }
    .dpr-title { font-size: 1.25em; font-weight: 700; margin: 0 0 12px; }
    .dpr-summary { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--dpr-border); }
    .dpr-average { font-size: 2.25em; font-weight: 700; line-height: 1; }
    .dpr-stars { color: var(--dpr-star); letter-spacing: 2px; white-space: nowrap; }
    .dpr-stars .dpr-off { color: var(--dpr-border); }
    .dpr-muted { color: var(--dpr-muted); font-size: .9em; }
    .dpr-bars { flex: 1 1 220px; min-width: 200px; }
    .dpr-bar { display: grid; grid-template-columns: 2.5em 1fr 2.5em; gap: 8px; align-items: center; font-size: .9em; }
    .dpr-bar-track { height: 8px; background: var(--dpr-border); border-radius: 4px; overflow: hidden; }
    .dpr-bar-fill { height: 100%; background: var(--dpr-star); }
    .dpr-list { list-style: none; margin: 0; padding: 0; }
    .dpr-item { padding: 16px 0; border-bottom: 1px solid var(--dpr-border); }
    .dpr-item-head { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: baseline; }
    .dpr-item-title { font-weight: 600; margin: 4px 0; }
    .dpr-item-body { margin: 4px 0 0; white-space: pre-line; }
    .dpr-verified { color: #047857; font-size: .85em; }
    .dpr-reply { margin: 12px 0 0 16px; padding: 8px 12px; border-left: 3px solid var(--dpr-border); }
    .dpr-reply-label { font-weight: 600; font-size: .9em; }
    .dpr-button { display: inline-block; margin-top: 12px; padding: 10px 18px; border: 1px solid var(--dpr-accent); border-radius: 6px; background: var(--dpr-accent); color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; }
    .dpr-button-light { background: transparent; color: var(--dpr-accent); }
    .dpr-form { display: grid; gap: 12px; margin-top: 16px; max-width: 640px; }
    .dpr-field label { display: block; font-weight: 600; margin-bottom: 4px; }
    .dpr-field input, .dpr-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--dpr-border); border-radius: 6px; font: inherit; color: inherit; background: #fff; }
    .dpr-field textarea { min-height: 120px; }
    .dpr-error { color: #b91c1c; font-size: .85em; margin-top: 4px; }
    .dpr-notice { padding: 12px 16px; border-radius: 6px; background: #ecfdf5; color: #065f46; }
    .dpr-hp { position: absolute !important; left: -10000px !important; width: 1px; height: 1px; overflow: hidden; }
    .dpr-rate { display: flex; gap: 4px; }
    .dpr-rate button { font-size: 2em; line-height: 1; background: none; border: 0; padding: 0 2px; cursor: pointer; color: var(--dpr-border); }
    .dpr-rate button.dpr-on { color: var(--dpr-star); }
    .dpr-card { max-width: 640px; margin: 32px auto; padding: 24px 16px; }
    .dpr-product { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; }
    .dpr-product img { width: 64px; height: auto; border-radius: 6px; }
    .dpr-qa-q { font-weight: 600; margin: 0; }
    .dpr-qa-a { margin: 6px 0 0; white-space: pre-line; }
</style>
@endonce
