<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('filament-api-forge.docs.title', 'API Documentation') }}</title>
    <meta name="description" content="{{ config('filament-api-forge.docs.description', '') }}">
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        :root {
            --bg-body:        #f8fafc;
            --bg-topbar:      #ffffff;
            --bg-topbar-b:    #e2e8f0;
            --text-title:     #0f172a;
            --text-muted:     #64748b;
            --badge-bg:       #f59e0b;
            --badge-color:    #1c1917;
            --toggle-bg:      #e2e8f0;
            --toggle-color:   #475569;
            --toggle-hover:   #cbd5e1;
        }
        [data-theme="dark"] {
            --bg-body:        #0f172a;
            --bg-topbar:      #1e293b;
            --bg-topbar-b:    #334155;
            --text-title:     #f1f5f9;
            --text-muted:     #94a3b8;
            --badge-bg:       #f59e0b;
            --badge-color:    #1c1917;
            --toggle-bg:      #334155;
            --toggle-color:   #94a3b8;
            --toggle-hover:   #475569;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg-body);
            transition: background 0.2s;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-topbar);
            border-bottom: 1px solid var(--bg-topbar-b);
        }
        .topbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-title {
            color: var(--text-title);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .topbar-badge {
            background: var(--badge-bg);
            color: var(--badge-color);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 9999px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .topbar-spacer { flex: 1; }
        .ver-switch {
            display: inline-flex;
            gap: 2px;
            background: var(--toggle-bg);
            border-radius: 8px;
            padding: 2px;
        }
        .ver-pill {
            font-size: 11px;
            font-weight: 700;
            font-family: ui-monospace, monospace;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 3px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--toggle-color);
            transition: background 0.15s, color 0.15s;
        }
        .ver-pill:hover { color: var(--text-title); }
        .ver-pill-active {
            background: var(--badge-bg);
            color: var(--badge-color);
        }
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--toggle-bg);
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            color: var(--toggle-color);
            font-size: 13px;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
        }
        .theme-toggle:hover { background: var(--toggle-hover); }
        .theme-toggle svg { width: 15px; height: 15px; flex-shrink: 0; }

        #swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 16px 60px;
        }

        /* ── Swagger UI: base resets ── */
        .swagger-ui { background: transparent !important; }
        .swagger-ui .topbar { display: none !important; }
        .swagger-ui .info .title,
        .swagger-ui .info h1, .swagger-ui .info h2 { color: var(--text-title) !important; }
        .swagger-ui .info p, .swagger-ui .info li,
        .swagger-ui .info .description p { color: var(--text-muted) !important; }

        /* ── Scheme container (server selector + authorize bar): boxed ── */
        .swagger-ui .scheme-container {
            max-width: 1400px;
            margin: 16px auto !important;
            border-radius: 10px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
            padding: 12px 20px !important;
        }

        /* ── Dark theme overrides ── */
        [data-theme="dark"] .swagger-ui .scheme-container {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .opblock-tag {
            color: #e2e8f0 !important;
            border-bottom-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .opblock-tag:hover { background: #1e293b !important; }
        [data-theme="dark"] .swagger-ui .opblock-tag small { color: #94a3b8 !important; }

        [data-theme="dark"] .swagger-ui .opblock {
            border-color: #334155 !important;
            background: #1e293b !important;
            box-shadow: none !important;
        }
        [data-theme="dark"] .swagger-ui .opblock .opblock-summary {
            border-bottom-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .opblock .opblock-summary-description,
        [data-theme="dark"] .swagger-ui .opblock .opblock-summary-path,
        [data-theme="dark"] .swagger-ui .opblock .opblock-summary-path__deprecated { color: #cbd5e1 !important; }
        [data-theme="dark"] .swagger-ui .opblock-body { background: #0f172a !important; }
        [data-theme="dark"] .swagger-ui .opblock-body pre { background: #0f172a !important; color: #e2e8f0 !important; }

        [data-theme="dark"] .swagger-ui section.models {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui section.models h4,
        [data-theme="dark"] .swagger-ui .model-title { color: #e2e8f0 !important; }
        [data-theme="dark"] .swagger-ui .model { color: #cbd5e1 !important; }
        [data-theme="dark"] .swagger-ui .prop-type,
        [data-theme="dark"] .swagger-ui .model span { color: #818cf8 !important; }
        [data-theme="dark"] .swagger-ui .model-toggle:after { background: #334155 !important; }

        [data-theme="dark"] .swagger-ui table thead tr td,
        [data-theme="dark"] .swagger-ui table thead tr th {
            color: #94a3b8 !important;
            border-bottom-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .parameter__name { color: #e2e8f0 !important; }
        [data-theme="dark"] .swagger-ui .parameter__type { color: #818cf8 !important; }
        [data-theme="dark"] .swagger-ui .parameter__in { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .parameter-item { color: #cbd5e1 !important; }
        [data-theme="dark"] .swagger-ui label { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .col_header { color: #94a3b8 !important; }

        [data-theme="dark"] .swagger-ui input[type=text],
        [data-theme="dark"] .swagger-ui input[type=password],
        [data-theme="dark"] .swagger-ui textarea,
        [data-theme="dark"] .swagger-ui select {
            background: #0f172a !important;
            border-color: #475569 !important;
            color: #e2e8f0 !important;
        }
        [data-theme="dark"] .swagger-ui .btn {
            border-color: #475569 !important;
            color: #94a3b8 !important;
            background: #1e293b !important;
        }
        [data-theme="dark"] .swagger-ui .btn:hover { background: #334155 !important; }
        [data-theme="dark"] .swagger-ui .btn.execute {
            background: #f59e0b !important;
            border-color: #f59e0b !important;
            color: #1c1917 !important;
        }
        [data-theme="dark"] .swagger-ui .btn.authorize {
            color: #34d399 !important;
            border-color: #34d399 !important;
            background: transparent !important;
        }
        [data-theme="dark"] .swagger-ui .btn.cancel {
            border-color: #f87171 !important;
            color: #f87171 !important;
        }
        [data-theme="dark"] .swagger-ui .response-col_status { color: #e2e8f0 !important; }
        [data-theme="dark"] .swagger-ui .microlight { background: #0f172a !important; color: #e2e8f0 !important; }
        [data-theme="dark"] .swagger-ui .highlight-code > .microlight { color: #e2e8f0 !important; }
        [data-theme="dark"] .swagger-ui .response-col_description__inner p,
        [data-theme="dark"] .swagger-ui .markdown p { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .tab li { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .tab li.active { color: #f1f5f9 !important; border-bottom-color: #f59e0b !important; }
        [data-theme="dark"] .swagger-ui .dialog-ux .modal-ux {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .dialog-ux .modal-ux-header {
            background: #0f172a !important;
            border-bottom-color: #334155 !important;
        }
        [data-theme="dark"] .swagger-ui .dialog-ux .modal-ux-header h3 { color: #f1f5f9 !important; }
        [data-theme="dark"] .swagger-ui .dialog-ux .modal-ux-content p,
        [data-theme="dark"] .swagger-ui .dialog-ux .modal-ux-content h4 { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .filter .operation-filter-input {
            background: #0f172a !important;
            border-color: #475569 !important;
            color: #e2e8f0 !important;
        }
        [data-theme="dark"] .swagger-ui .wrapper { background: transparent !important; }
        [data-theme="dark"] .swagger-ui .info { background: transparent !important; }
        [data-theme="dark"] .swagger-ui .servers > label { color: #94a3b8 !important; }
        [data-theme="dark"] .swagger-ui .servers > label select { background: #0f172a !important; color: #e2e8f0 !important; border-color: #475569 !important; }
    </style>
</head>
<body>
    <div class="topbar">
      <div class="topbar-inner">
        <span class="topbar-title">{{ config('filament-api-forge.docs.title', 'API Documentation') }}</span>
        @if(!empty($versionLinks ?? []))
            <span class="ver-switch">
                @foreach($versionLinks as $v => $link)
                    <a href="{{ $link }}" class="ver-pill {{ ($currentVersion ?? '') === $v ? 'ver-pill-active' : '' }}">{{ $v }}</a>
                @endforeach
            </span>
        @else
            <span class="topbar-badge">{{ config('filament-api-forge.api_version', 'v1') }}</span>
        @endif
        <div class="topbar-spacer"></div>
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme">
            <svg id="iconLight" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <svg id="iconDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            <span id="themeLabel">Dark</span>
        </button>
      </div>
    </div>

    <div id="swagger-ui"></div>

    <div style="
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px 16px 32px;
        text-align: center;
        font-size: 12px;
        color: var(--text-muted);
        border-top: 1px solid var(--bg-topbar-b);
    ">
        Auto-generated by
        <a href="https://github.com/yusufgenc34/filament-api-forge"
           target="_blank"
           rel="noopener"
           style="color: var(--text-muted); font-weight: 600; text-decoration: none; border-bottom: 1px solid currentColor; padding-bottom: 1px;">
            Filament Api Forge
        </a>
    </div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        const THEME_KEY = 'api_forge_theme';

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.getElementById('iconLight').style.display  = theme === 'dark'  ? 'block' : 'none';
            document.getElementById('iconDark').style.display   = theme === 'light' ? 'block' : 'none';
            document.getElementById('themeLabel').textContent   = theme === 'dark'  ? 'Light' : 'Dark';
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'light' ? 'dark' : 'light';
            localStorage.setItem(THEME_KEY, next);
            applyTheme(next);
        }

        // Init: default light, override from localStorage
        const saved = localStorage.getItem(THEME_KEY) || 'light';
        applyTheme(saved);

        SwaggerUIBundle({
            url: "{{ $openApiUrl }}",
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: "BaseLayout",
            deepLinking: true,
            displayRequestDuration: true,
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 1,
            tryItOutEnabled: true,
            filter: true,
        });
    </script>
</body>
</html>
