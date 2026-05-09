<style>
[x-cloak]{display:none!important}

:root{--dv-bg:#f8fafc;--dv-surface:#ffffff;--dv-border:#e2e8f0;--dv-border2:#cbd5e1;--dv-text:#0f172a;--dv-muted:#475569;--dv-faint:#94a3b8;--dv-accent:#6366f1;--dv-accent-h:#4f46e5;--dv-code-bg:#0d1117;--dv-row-alt:#f8fafc;}
.dark{--dv-bg:#0f172a;--dv-surface:#1e293b;--dv-border:#334155;--dv-border2:#475569;--dv-text:#f1f5f9;--dv-muted:#94a3b8;--dv-faint:#64748b;--dv-accent:#818cf8;--dv-accent-h:#6366f1;--dv-code-bg:#020617;--dv-row-alt:#243249;}

/* ── Tab Navigation ─────────────────────────────────────────────────── */
.af-tab-nav{display:flex;border-bottom:2px solid var(--dv-border);margin-bottom:1.25rem;gap:.25rem;}
.af-tab{padding:.625rem 1.25rem;font-size:.875rem;font-weight:600;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;background:transparent;color:var(--dv-faint);transition:color .15s,border-color .15s;border-radius:.375rem .375rem 0 0;}
.af-tab:hover{color:var(--dv-muted);}
.af-tab-active{color:var(--dv-accent)!important;border-bottom-color:var(--dv-accent)!important;}

/* ── 3-column docs shell ─────────────────────────────────────────────── */
.docs-wrap{display:grid;grid-template-columns:268px 1fr 368px;height:calc(100vh - 10rem);border:1px solid var(--dv-border);border-radius:.75rem;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);}

/* Sidebar */
.docs-sb{background:#0f172a;border-right:1px solid #1e293b;display:flex;flex-direction:column;overflow:hidden;}
.docs-sb-head{padding:.875rem 1.125rem;border-bottom:1px solid #1e293b;}
.docs-sb-scroll{flex:1;overflow-y:auto;padding:.375rem 0 1rem;}
.docs-sb-scroll::-webkit-scrollbar{width:3px;}
.docs-sb-scroll::-webkit-scrollbar-thumb{background:#334155;border-radius:2px;}
.sg-head{display:flex;align-items:center;justify-content:space-between;padding:.3rem 1.125rem;cursor:pointer;user-select:none;}
.sg-label{font-size:.575rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b;transition:color .12s;}
.sg-head:hover .sg-label{color:#94a3b8;}
.sg-chevron{width:.8rem;height:.8rem;color:#475569;transition:transform .18s;flex-shrink:0;}
.sg-chevron.is-collapsed{transform:rotate(-90deg);}
.ep-row{display:flex;align-items:center;gap:.5rem;padding:.3rem .75rem .3rem 1.125rem;cursor:pointer;border-left:2px solid transparent;transition:background .1s;}
.ep-row:hover{background:#1e293b;}
.ep-row.is-active{background:rgba(99,102,241,.15);border-left-color:#818cf8;}
.ep-path{font-family:ui-monospace,monospace;font-size:.685rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:color .1s;}
.ep-row:hover .ep-path,.ep-row.is-active .ep-path{color:#e0e7ff;}
.schema-row{display:flex;align-items:center;gap:.4rem;padding:.275rem .75rem .275rem 1.125rem;cursor:pointer;border-left:2px solid transparent;transition:background .1s;}
.schema-row:hover{background:#1e293b;}
.schema-row.is-active{background:rgba(99,102,241,.15);border-left-color:#818cf8;}
.sr-name{font-size:.75rem;color:#94a3b8;transition:color .1s;}
.schema-row:hover .sr-name,.schema-row.is-active .sr-name{color:#e0e7ff;}
.schema-icon{width:.7rem;height:.7rem;color:#475569;flex-shrink:0;}
.export-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.225rem .55rem;border-radius:.3rem;font-size:.675rem;font-weight:500;color:#94a3b8;border:1px solid #334155;background:transparent;text-decoration:none;cursor:pointer;transition:all .12s;margin-top:.5rem;}
.export-btn:hover{background:#1e293b;color:#e2e8f0;border-color:#475569;}
.export-icon{width:.7rem;height:.7rem;flex-shrink:0;}

/* Method badges (sidebar) */
.mb{display:inline-flex;align-items:center;justify-content:center;min-width:2.875rem;padding:.125rem .375rem;border-radius:.25rem;font-size:.575rem;font-weight:700;font-family:ui-monospace,monospace;letter-spacing:.04em;flex-shrink:0;}
.mb-get{background:#14532d;color:#86efac;}.mb-post{background:#1e3a8a;color:#93c5fd;}.mb-put,.mb-patch{background:#78350f;color:#fcd34d;}.mb-delete{background:#7f1d1d;color:#fca5a5;}

/* Method badges (content area) */
.mbc{display:inline-flex;align-items:center;justify-content:center;min-width:2.875rem;padding:.15rem .45rem;border-radius:.25rem;font-size:.6rem;font-weight:700;font-family:ui-monospace,monospace;letter-spacing:.04em;flex-shrink:0;}
.mbc-get{background:#dcfce7;color:#166534;}.mbc-post{background:#dbeafe;color:#1e40af;}.mbc-put,.mbc-patch{background:#fef3c7;color:#92400e;}.mbc-delete{background:#fee2e2;color:#991b1b;}

/* Content area */
.docs-content{background:var(--dv-bg);border-right:1px solid var(--dv-border);overflow-y:auto;padding:1.875rem 2rem 3rem;}
.docs-content::-webkit-scrollbar{width:5px;}
.docs-content::-webkit-scrollbar-thumb{background:var(--dv-border2);border-radius:3px;}
.ep-title{font-family:ui-monospace,monospace;font-size:.9375rem;font-weight:600;color:var(--dv-text);word-break:break-all;line-height:1.4;}
.ep-summary{color:var(--dv-muted);font-size:.8125rem;margin:.375rem 0 0;line-height:1.5;}
.auth-badge{display:inline-flex;align-items:center;gap:.35rem;margin-top:.5rem;padding:.2rem .625rem;border-radius:.3rem;background:#fefce8;border:1px solid #fef08a;font-size:.72rem;font-weight:500;color:#713f12;}
.dark .auth-badge{background:#422006;border-color:#854d0e;color:#fcd34d;}
.auth-icon{width:.8rem;height:.8rem;flex-shrink:0;}
.divider{border:none;border-top:1px solid var(--dv-border);margin:.875rem 0;}
.sec-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--dv-faint);margin-bottom:.5rem;margin-top:1.375rem;display:block;}

/* Params table */
.ptbl{width:100%;border-collapse:collapse;font-size:.8rem;border:1px solid var(--dv-border);border-radius:.5rem;overflow:hidden;}
.ptbl th{text-align:left;padding:.35rem .625rem;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--dv-faint);background:var(--dv-surface);border-bottom:1px solid var(--dv-border);}
.ptbl td{padding:.4rem .625rem;border-bottom:1px solid var(--dv-border);vertical-align:top;background:var(--dv-surface);}
.ptbl tr:nth-child(even) td{background:var(--dv-row-alt);}
.ptbl tr:last-child td{border-bottom:none;}
.pname{font-family:ui-monospace,monospace;color:var(--dv-accent);font-size:.78rem;}
.ptype{font-family:ui-monospace,monospace;color:var(--dv-faint);font-size:.7rem;}
.req-tag{display:inline-flex;padding:.05rem .3rem;border-radius:.2rem;background:#fef2f2;color:#b91c1c;font-size:.55rem;font-weight:700;margin-left:.3rem;}
.dark .req-tag{background:#450a0a;color:#fca5a5;}

/* Code block */
.codeblock{font-family:ui-monospace,monospace;font-size:.73rem;line-height:1.7;padding:.875rem 1.125rem;background:var(--dv-code-bg);color:#e6edf3;border-radius:.5rem;overflow:auto;white-space:pre;margin:0;border:1px solid #21262d;}
.jk{color:#79c0ff;}.js{color:#a5d6ff;}.jn{color:#ff9966;}.jb{color:#d2a8ff;}.jnl{color:#8b949e;}.jp{color:#8b949e;}

/* Inner tabs (responses etc) */
.tabs-row{display:flex;border-bottom:1px solid var(--dv-border);margin-bottom:.625rem;}
.tab-btn{padding:.3rem .75rem;font-size:.7rem;font-weight:600;border:none;border-bottom:2px solid transparent;cursor:pointer;background:transparent;color:var(--dv-faint);transition:color .1s;margin-bottom:-1px;border-radius:.25rem .25rem 0 0;white-space:nowrap;}
.tab-btn:hover{color:var(--dv-muted);background:var(--dv-surface);}
.tab-btn.tab-on{color:var(--dv-accent);border-bottom-color:var(--dv-accent);background:var(--dv-surface);}
.resp-tab-on-2xx{color:#15803d!important;border-bottom-color:#16a34a!important;background:#f0fdf4!important;}
.dark .resp-tab-on-2xx{background:#052e16!important;color:#4ade80!important;}
.resp-tab-on-4xx{color:#b45309!important;border-bottom-color:#d97706!important;background:#fffbeb!important;}
.dark .resp-tab-on-4xx{background:#431407!important;color:#fbbf24!important;}
.resp-tab-on-5xx{color:#b91c1c!important;border-bottom-color:#dc2626!important;background:#fef2f2!important;}
.dark .resp-tab-on-5xx{background:#450a0a!important;color:#f87171!important;}

/* Try It panel */
.docs-try{background:var(--dv-surface);overflow-y:auto;display:flex;flex-direction:column;}
.try-head{padding:.875rem 1.25rem .75rem;border-bottom:1px solid var(--dv-border);background:var(--dv-surface);position:sticky;top:0;z-index:10;}
.try-body{padding:.875rem 1.25rem;display:flex;flex-direction:column;gap:.75rem;}
.flabel{font-size:.7rem;font-weight:600;color:var(--dv-muted);margin-bottom:.25rem;display:flex;align-items:center;gap:.375rem;}
.finput{width:100%;padding:.4rem .65rem;border:1px solid var(--dv-border);border-radius:.375rem;font-size:.78rem;font-family:ui-monospace,monospace;background:var(--dv-bg);outline:none;box-sizing:border-box;color:var(--dv-text);transition:border-color .15s,box-shadow .15s;}
.finput:focus{border-color:var(--dv-accent);box-shadow:0 0 0 3px rgba(99,102,241,.12);}
.ftextarea{width:100%;min-height:100px;padding:.4rem .65rem;border:1px solid var(--dv-border);border-radius:.375rem;font-size:.7rem;font-family:ui-monospace,monospace;resize:vertical;background:var(--dv-bg);outline:none;box-sizing:border-box;color:var(--dv-text);}
.ftextarea:focus{border-color:var(--dv-accent);box-shadow:0 0 0 3px rgba(99,102,241,.12);}
.send-btn{display:flex;align-items:center;justify-content:center;gap:.375rem;width:100%;padding:.5rem 1.25rem;border-radius:.375rem;font-size:.8rem;font-weight:600;background:var(--dv-accent);color:#fff;border:none;cursor:pointer;transition:background .15s,opacity .15s;}
.send-btn:hover:not([disabled]){background:var(--dv-accent-h);}
.send-btn[disabled]{opacity:.6;cursor:not-allowed;}
.chip{display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:.25rem;font-size:.7rem;font-weight:700;font-family:ui-monospace,monospace;}
.chip-2xx{background:#dcfce7;color:#166534;}.dark .chip-2xx{background:#052e16;color:#4ade80;}
.chip-4xx{background:#fef9c3;color:#854d0e;}.dark .chip-4xx{background:#431407;color:#fbbf24;}
.chip-5xx{background:#fee2e2;color:#991b1b;}.dark .chip-5xx{background:#450a0a;color:#f87171;}
.chip-0{background:var(--dv-border);color:var(--dv-muted);}
.sm-btn{display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .4rem;border-radius:.25rem;font-size:.65rem;font-weight:500;border:1px solid var(--dv-border);background:var(--dv-bg);color:var(--dv-faint);cursor:pointer;transition:all .1s;white-space:nowrap;}
.sm-btn:hover{background:var(--dv-border);color:var(--dv-muted);}
.smbtn-icon{width:.6rem;height:.6rem;flex-shrink:0;}
.try-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:var(--dv-faint);font-size:.8125rem;gap:.5rem;padding:2rem;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── Access Control Tab ──────────────────────────────────────────────── */
.ac-page{display:flex;flex-direction:column;gap:1rem;padding:.25rem 0 3rem;}
.ac-card{background:var(--dv-surface);border:1px solid var(--dv-border);border-radius:.875rem;overflow:hidden;transition:opacity .2s;}
.ac-card-off{opacity:.55;}

/* Card header */
.ac-card-header{display:flex;align-items:center;justify-content:space-between;padding:.875rem 1.375rem;background:var(--dv-surface);}
.ac-card-identity{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
.ac-resource-name{font-size:.9375rem;font-weight:700;color:var(--dv-text);}
.ac-resource-tag{font-size:.625rem;font-weight:600;padding:.175rem .45rem;border-radius:.3rem;background:var(--dv-row-alt);color:var(--dv-faint);font-family:monospace;}
.ac-badge-off{font-size:.575rem;font-weight:700;padding:.175rem .4rem;border-radius:.3rem;background:#fef2f2;color:#b91c1c;text-transform:uppercase;letter-spacing:.06em;}
.ac-badge-info{font-size:.575rem;font-weight:600;padding:.175rem .4rem;border-radius:.3rem;background:rgba(99,102,241,.1);color:var(--dv-accent);font-family:monospace;}
.dark .ac-badge-off{background:#450a0a;color:#fca5a5;}
.ac-header-actions{display:flex;align-items:center;gap:.75rem;}

/* Enable toggle */
.ac-toggle{display:inline-flex;align-items:center;width:44px;height:24px;border-radius:12px;padding:4px;cursor:pointer;border:none;transition:background .2s;flex-shrink:0;}
.ac-toggle-on{background:#4f46e5;}.ac-toggle-off{background:#94a3b8;}
.ac-toggle-thumb{width:16px;height:16px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25);}
.ac-toggle-on .ac-toggle-thumb{transform:translateX(20px);}
.ac-toggle:disabled{opacity:.5;cursor:not-allowed;}

/* Chevron */
.ac-chevron{display:flex;align-items:center;color:var(--dv-faint);transition:transform .2s;}
.ac-chevron svg{width:1rem;height:1rem;}
.ac-chevron-open{transform:rotate(180deg);}

/* Sections inside card */
.ac-section{padding:1rem 1.375rem;border-top:1px solid var(--dv-border);}
.ac-section-title{display:flex;align-items:center;gap:.4rem;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--dv-faint);margin-bottom:.875rem;}
.ac-section-icon{width:.75rem;height:.75rem;flex-shrink:0;}
.ac-section-hint{font-weight:400;text-transform:none;letter-spacing:0;color:var(--dv-faint);font-size:.625rem;margin-left:.25rem;}

/* Resource settings row */
.ac-settings-row{display:flex;align-items:flex-start;gap:.875rem;flex-wrap:wrap;}
.ac-field{display:flex;flex-direction:column;gap:.3rem;}
.ac-field-grow{flex:1;min-width:180px;}
.ac-field-save{display:flex;align-items:flex-end;padding-bottom:1px;}
.ac-label{font-size:.68rem;font-weight:600;color:var(--dv-muted);}
.ac-label-hint{font-weight:400;color:var(--dv-faint);margin-left:.2rem;}
.ac-input{width:140px;padding:.375rem .6rem;border:1px solid var(--dv-border);border-radius:.375rem;font-size:.78rem;font-family:ui-monospace,monospace;background:var(--dv-bg);color:var(--dv-text);outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box;}
.ac-input-sm{width:100%;}
.ac-input:focus{border-color:var(--dv-accent);box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.ac-textarea{width:100%;padding:.375rem .6rem;border:1px solid var(--dv-border);border-radius:.375rem;font-size:.72rem;font-family:ui-monospace,monospace;background:var(--dv-bg);color:var(--dv-text);outline:none;resize:vertical;box-sizing:border-box;transition:border-color .15s,box-shadow .15s;}
.ac-textarea-sm{font-size:.68rem;}
.ac-textarea:focus{border-color:var(--dv-accent);box-shadow:0 0 0 3px rgba(99,102,241,.1);}

/* Buttons */
.ac-save-btn,.ac-method-save-btn{display:inline-flex;align-items:center;gap:.3rem;border-radius:.375rem;font-weight:600;border:none;cursor:pointer;transition:background .15s,opacity .15s;white-space:nowrap;}
.ac-save-btn{padding:.4rem .9rem;font-size:.75rem;background:var(--dv-accent);color:#fff;}
.ac-save-btn:hover{background:var(--dv-accent-h);}
.ac-method-save-btn{padding:.3rem .7rem;font-size:.68rem;background:var(--dv-row-alt);color:var(--dv-muted);border:1px solid var(--dv-border);margin-top:.25rem;width:100%;justify-content:center;}
.ac-method-save-btn:hover{background:var(--dv-border);color:var(--dv-text);}
.ac-save-btn:disabled,.ac-method-save-btn:disabled{opacity:.5;cursor:not-allowed;}
.ac-btn-icon{width:.7rem;height:.7rem;flex-shrink:0;}

/* Methods grid */
.ac-methods-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;}
.ac-method-card{border:1px solid var(--dv-border);border-radius:.625rem;padding:.875rem;background:var(--dv-bg);display:flex;flex-direction:column;gap:.5rem;transition:opacity .2s;}
.ac-method-card-off{opacity:.45;border-style:dashed;}
.ac-method-header{display:flex;align-items:center;gap:.4rem;}
.ac-method-name{font-size:.75rem;font-weight:700;color:var(--dv-text);flex:1;}
.ac-method-badge{display:inline-flex;align-items:center;padding:.15rem .375rem;border-radius:.2rem;font-size:.55rem;font-weight:700;font-family:ui-monospace,monospace;letter-spacing:.04em;flex-shrink:0;}
.ac-badge-get{background:#dcfce7;color:#166534;}.dark .ac-badge-get{background:#14532d;color:#86efac;}
.ac-badge-post{background:#dbeafe;color:#1e40af;}.dark .ac-badge-post{background:#1e3a8a;color:#93c5fd;}
.ac-badge-put{background:#fef3c7;color:#92400e;}.dark .ac-badge-put{background:#78350f;color:#fcd34d;}
.ac-badge-delete{background:#fee2e2;color:#991b1b;}.dark .ac-badge-delete{background:#7f1d1d;color:#fca5a5;}

/* Mini toggle inside method */
.ac-mini-toggle{display:inline-flex;align-items:center;width:30px;height:17px;border-radius:9px;padding:2px;cursor:pointer;border:none;transition:background .2s;flex-shrink:0;}
.ac-mtoggle-on{background:#4f46e5;}.ac-mtoggle-off{background:#94a3b8;}
.ac-mtoggle-thumb{width:13px;height:13px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 2px rgba(0,0,0,.2);}
.ac-mtoggle-on .ac-mtoggle-thumb{transform:translateX(13px);}
.ac-mini-toggle:disabled{opacity:.5;cursor:not-allowed;}

.ac-method-desc{font-size:.7rem;color:var(--dv-muted);line-height:1.45;margin:0;}

/* Info chips */
.ac-info-block{display:flex;flex-direction:column;gap:.25rem;}
.ac-info-label{font-size:.575rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--dv-faint);}
.ac-chips{display:flex;flex-wrap:wrap;gap:.25rem;}
.ac-chip{font-family:ui-monospace,monospace;font-size:.6rem;padding:.125rem .35rem;border-radius:.2rem;background:var(--dv-row-alt);color:var(--dv-muted);border:1px solid var(--dv-border);}
.ac-chip-accent{background:rgba(99,102,241,.08);color:var(--dv-accent);border-color:rgba(99,102,241,.2);}

/* Per-method settings inputs */
.ac-method-settings{display:flex;flex-direction:column;gap:.4rem;border-top:1px solid var(--dv-border);padding-top:.5rem;margin-top:.1rem;}
.ac-method-ips{display:flex;flex-direction:column;gap:.2rem;}

.ac-disabled-msg{padding:1rem 1.375rem;font-size:.78rem;color:var(--dv-faint);border-top:1px solid var(--dv-border);}
.ac-empty{text-align:center;padding:3rem;color:var(--dv-faint);font-size:.875rem;}
</style>
