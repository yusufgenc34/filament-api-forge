<x-filament-panels::page>
@php
$httpMap = [
    'index'   => ['GET',    'get'],
    'show'    => ['GET',    'get'],
    'store'   => ['POST',   'post'],
    'update'  => ['PUT',    'put'],
    'destroy' => ['DELETE', 'delete'],
];
$pathMap = [
    'index'   => '',
    'show'    => '/{id}',
    'store'   => '',
    'update'  => '/{id}',
    'destroy' => '/{id}',
];
@endphp

<style>
/* ── Layout ──────────────────────────────────────────────── */
.dd-root { display: flex; flex-direction: column; gap: 1.75rem; }

/* ── Stat cards ──────────────────────────────────────────── */
.dd-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
@media(max-width:900px){ .dd-stats{ grid-template-columns: repeat(2,1fr); } }
@media(max-width:540px){ .dd-stats{ grid-template-columns: 1fr; } }

.dd-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
}
.dark .dd-stat { background: #1f2937; border-color: #374151; }

.dd-stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 0.5rem;
}
.dark .dd-stat-label { color: #9ca3af; }

.dd-stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    color: #111827;
    margin-bottom: 0.375rem;
}
.dark .dd-stat-value { color: #f9fafb; }

.dd-stat-sub {
    font-size: 0.75rem;
    color: #6b7280;
    font-family: ui-monospace, monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .dd-stat-sub { color: #9ca3af; }

/* ── Card ─────────────────────────────────────────────────── */
.dd-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}
.dark .dd-card { background: #1f2937; border-color: #374151; }

.dd-card-head {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
}
.dark .dd-card-head { border-bottom-color: #374151; }

.dd-card-head-icon {
    width: 1.125rem;
    height: 1.125rem;
    color: #6b7280;
    flex-shrink: 0;
}
.dd-card-head-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
}
.dark .dd-card-head-title { color: #f9fafb; }
.dd-card-head-sub {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-left: auto;
}

/* ── Resource table ───────────────────────────────────────── */
.dd-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.dd-table th {
    padding: 0.625rem 1.25rem;
    text-align: left;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #9ca3af;
    background: #f9fafb;
    border-bottom: 1px solid #f3f4f6;
}
.dark .dd-table th { background: #111827; border-bottom-color: #374151; color: #6b7280; }
.dd-table td {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid #f9fafb;
    vertical-align: middle;
}
.dark .dd-table td { border-bottom-color: #374151; }
.dd-table tr:last-child td { border-bottom: none; }

.dd-res-name  { font-weight: 600; color: #111827; }
.dark .dd-res-name { color: #f9fafb; }
.dd-res-panel { color: #9ca3af; font-size: 0.75rem; }
.dd-endpoint  { font-family: ui-monospace, monospace; color: #4b5563; font-size: 0.75rem; }
.dark .dd-endpoint { color: #9ca3af; }

/* ── HTTP method badges ───────────────────────────────────── */
.dd-methods  { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.dd-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    font-family: ui-monospace, monospace;
}
.dd-badge-get    { background:#eff6ff; color:#1d4ed8; }
.dd-badge-post   { background:#f0fdf4; color:#15803d; }
.dd-badge-put    { background:#fffbeb; color:#b45309; }
.dd-badge-delete { background:#fff1f2; color:#be123c; }
.dark .dd-badge-get    { background:#1e3a5f; color:#93c5fd; }
.dark .dd-badge-post   { background:#14532d; color:#86efac; }
.dark .dd-badge-put    { background:#451a03; color:#fcd34d; }
.dark .dd-badge-delete { background:#4c0519; color:#fda4af; }

/* ── Auth section ─────────────────────────────────────────── */
.dd-auth-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
@media(max-width:720px){ .dd-auth-grid{ grid-template-columns:1fr; } }

.dd-auth-col { padding: 1.25rem 1.5rem; }
.dd-auth-col + .dd-auth-col { border-left: 1px solid #f3f4f6; }
.dark .dd-auth-col + .dd-auth-col { border-left-color: #374151; }

.dd-auth-col-title {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 0.875rem;
}

/* ── Code block ───────────────────────────────────────────── */
.dd-code {
    background: #111827;
    border-radius: 8px;
    padding: 0.875rem 1rem;
    font-family: ui-monospace, monospace;
    font-size: 0.76rem;
    line-height: 1.7;
    overflow-x: auto;
    position: relative;
}
.dd-code .c  { color: #6b7280; }
.dd-code .kw { color: #7dd3fc; }
.dd-code .st { color: #86efac; }
.dd-code .vr { color: #fbbf24; }
.dd-code .url{ color: #c4b5fd; }

/* copy button */
.dd-copy-wrap { position: relative; }
.dd-copy-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.2rem 0.6rem;
    background: #374151;
    border: none;
    border-radius: 4px;
    color: #9ca3af;
    font-size: 0.68rem;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.dd-copy-btn:hover { background:#4b5563; color:#e5e7eb; }

/* ── Empty ────────────────────────────────────────────────── */
.dd-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    color: #9ca3af;
    font-size: 0.875rem;
}
</style>

<div class="dd-root">

    {{-- ── Stat cards ──────────────────────────────────────── --}}
    <div class="dd-stats">
        <div class="dd-stat">
            <div class="dd-stat-label">API Resources</div>
            <div class="dd-stat-value">{{ $resourceCount }}</div>
            <div class="dd-stat-sub">HasApi resources discovered</div>
        </div>
        <div class="dd-stat">
            <div class="dd-stat-label">Active Endpoints</div>
            <div class="dd-stat-value">{{ $totalEndpoints }}</div>
            <div class="dd-stat-sub">Across all resources</div>
        </div>
        <div class="dd-stat">
            <div class="dd-stat-label">Active Tokens</div>
            <div class="dd-stat-value">{{ $activeTokens }}</div>
            <div class="dd-stat-sub">Valid, non-expired</div>
        </div>
        <div class="dd-stat">
            <div class="dd-stat-label">Total Requests</div>
            <div class="dd-stat-value" title="{{ number_format($totalRequests) }}">{{ $formattedTotalRequests }}</div>
            <div class="dd-stat-sub">All-time API calls</div>
        </div>
    </div>

    {{-- ── Resource Tree (full-width) ───────────────────────── --}}
    @php
    $treeMeta = [
        'index'   => ['verb' => 'GET',    'fg' => '#1d4ed8', 'bg' => '#eff6ff', 'pill' => '#bfdbfe', 'path' => ''],
        'show'    => ['verb' => 'GET',    'fg' => '#1d4ed8', 'bg' => '#eff6ff', 'pill' => '#bfdbfe', 'path' => '/:id'],
        'store'   => ['verb' => 'POST',   'fg' => '#166534', 'bg' => '#f0fdf4', 'pill' => '#bbf7d0', 'path' => ''],
        'update'  => ['verb' => 'PUT',    'fg' => '#92400e', 'bg' => '#fffbeb', 'pill' => '#fde68a', 'path' => '/:id'],
        'destroy' => ['verb' => 'DELETE', 'fg' => '#9f1239', 'bg' => '#fff1f2', 'pill' => '#fecdd3', 'path' => '/:id'],
    ];
    $treeN = count($treeResources);
    @endphp

    <style>
    /* ── Tree ───────────────────────────────────────────── */
    .api-tree-wrap {
        overflow-y: auto;
        max-height: 480px;
        padding: 1rem 1.5rem;
        scrollbar-width: thin;
        scrollbar-color: #e2e8f0 transparent;
    }
    .dark .api-tree-wrap { scrollbar-color: #374151 transparent; }

    .api-tree-root-node {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: #0f172a;
        color: #f8fafc;
        padding: 0.35rem 0.875rem;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.8rem;
        margin-left: 0.5rem;
    }
    .api-tree-root-dot { width:6px; height:6px; border-radius:50%; background:#38bdf8; flex-shrink:0; }

    .api-tree-branch {
        padding-left: 1.25rem;
        border-left: 1.5px solid #e2e8f0;
        margin-left: 1rem;
        margin-top: 0.125rem;
    }
    .dark .api-tree-branch { border-left-color: #334155; }

    .api-tree-node { position: relative; padding-top: 0.5rem; }
    .api-tree-node::before {
        content: '';
        position: absolute;
        top: calc(0.5rem + 0.875rem);
        left: -1.25rem;
        width: 0.875rem;
        height: 1.5px;
        background: #e2e8f0;
    }
    .dark .api-tree-node::before { background: #334155; }

    /* Enabled resource row */
    .api-tree-res {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.35rem 0.625rem;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.12s;
        user-select: none;
    }
    .dark .api-tree-res { background: #1e293b; border-color: #334155; }
    .api-tree-res:hover { border-color: #94a3b8; }
    .dark .api-tree-res:hover { border-color: #475569; }

    /* Disabled resource row — not clickable, red tint */
    .api-tree-res-disabled {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.35rem 0.625rem;
        border-radius: 6px;
        border: 1px solid #fecaca;
        background: #fff5f5;
        opacity: 0.75;
        user-select: none;
    }
    .dark .api-tree-res-disabled { background: #1a0a0a; border-color: #7f1d1d; }

    .api-tree-res-name { font-size:0.8rem; font-weight:600; color:#1e293b; flex-shrink:0; }
    .dark .api-tree-res-name { color:#f1f5f9; }
    .api-tree-res-name-disabled { font-size:0.8rem; font-weight:600; color:#9f1239; flex-shrink:0; text-decoration:line-through; }

    .api-tree-res-panel { font-size:0.67rem; color:#94a3b8; font-family:ui-monospace,monospace; flex-shrink:0; }

    .api-tree-disabled-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        font-size: 0.62rem;
        font-weight: 600;
        color: #be123c;
        background: #ffe4e6;
        padding: 0.1rem 0.4rem;
        border-radius: 3px;
        flex-shrink: 0;
    }
    .api-tree-disabled-badge svg { width:10px; height:10px; }

    .api-tree-pills { display:flex; gap:0.2rem; flex-wrap:wrap; flex:1; }
    .api-tree-pill { padding:0.06rem 0.3rem; border-radius:3px; font-size:0.59rem; font-weight:700; font-family:ui-monospace,monospace; letter-spacing:0.04em; }

    .api-tree-chevron { width:13px; height:13px; color:#9ca3af; flex-shrink:0; transition:transform 0.15s; margin-left:auto; }
    .api-tree-chevron.rotated { transform:rotate(90deg); }

    .api-tree-methods {
        padding-left: 1rem;
        border-left: 1.5px solid #f1f5f9;
        margin-left: 0.875rem;
        margin-top: 0.25rem;
        padding-bottom: 0.125rem;
        display: flex;
        flex-direction: column;
        gap: 0.175rem;
    }
    .dark .api-tree-methods { border-left-color: #334155; }

    .api-tree-method { display:flex; align-items:center; gap:0.5rem; padding:0.2rem 0.375rem; border-radius:4px; position:relative; }
    .api-tree-method::before { content:''; position:absolute; top:50%; left:-1rem; width:0.625rem; height:1.5px; background:#e2e8f0; }
    .dark .api-tree-method::before { background:#334155; }

    /* Disabled method row */
    .api-tree-method-disabled { display:flex; align-items:center; gap:0.5rem; padding:0.2rem 0.375rem; border-radius:4px; position:relative; opacity:0.45; }
    .api-tree-method-disabled::before { content:''; position:absolute; top:50%; left:-1rem; width:0.625rem; height:1.5px; background:#fca5a5; }

    .api-tree-verb { font-family:ui-monospace,monospace; font-size:0.63rem; font-weight:700; letter-spacing:0.06em; padding:0.15rem 0.4rem; border-radius:3px; min-width:48px; text-align:center; flex-shrink:0; }
    .api-tree-verb-disabled { font-family:ui-monospace,monospace; font-size:0.63rem; font-weight:700; letter-spacing:0.06em; padding:0.15rem 0.4rem; border-radius:3px; min-width:48px; text-align:center; flex-shrink:0; background:#f3f4f6; color:#9ca3af; text-decoration:line-through; }

    .api-tree-path { font-family:ui-monospace,monospace; font-size:0.71rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dark .api-tree-path { color:#94a3b8; }
    .api-tree-path-disabled { font-family:ui-monospace,monospace; font-size:0.71rem; color:#d1d5db; white-space:nowrap; text-decoration:line-through; }

    .api-tree-disabled-x { display:inline-flex; align-items:center; justify-content:center; width:13px; height:13px; color:#be123c; margin-left:auto; flex-shrink:0; }
    </style>

    <div class="dd-card">
        <div class="dd-card-head">
            <svg class="dd-card-head-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h9a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h5a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h3a1 1 0 110 2H4a1 1 0 01-1-1zm9-13a1 1 0 10-2 0v9.586l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L12 12.586V3z" clip-rule="evenodd"/>
            </svg>
            <span class="dd-card-head-title">Resource Tree</span>
            <span class="dd-card-head-sub">
                {{ $treeN }} resource{{ $treeN !== 1 ? 's' : '' }}
                @php $disabledCount = collect($treeResources)->where('enabled', false)->count(); @endphp
                @if($disabledCount > 0)
                &nbsp;·&nbsp;<span style="color:#be123c;">{{ $disabledCount }} disabled</span>
                @endif
                &nbsp;·&nbsp;<code style="font-size:0.68rem;color:#9ca3af;">{{ $apiBaseUrl }}</code>
            </span>
        </div>

        @if($treeN === 0)
        <div class="dd-empty">No resources found. Add <code>implements HasApi</code> to a Filament Resource.</div>
        @else
        <div class="api-tree-wrap">

            <div style="margin-bottom:0.125rem;">
                <div class="api-tree-root-node">
                    <span class="api-tree-root-dot"></span>
                    API Forge
                </div>
            </div>

            <div class="api-tree-branch">
                @foreach($treeResources as $res)
                @php
                    $enabled = $res['enabled'];
                    $methods = $res['methods'] ?? [];
                    $slug    = $res['slug'];
                    $panel   = $res['panel_id'];
                    $hasDisabledMethod = collect($methods)->where('disabled', true)->count() > 0;
                @endphp

                @if($enabled)
                {{-- Enabled resource --}}
                <div class="api-tree-node" x-data="{ open: false }">
                    <div class="api-tree-res" @click="open = !open">
                        <span class="api-tree-res-name">{{ $res['plural_label'] }}</span>
                        <span class="api-tree-res-panel">{{ $panel }}</span>

                        <div class="api-tree-pills" x-show="!open">
                            @foreach($methods as $m)
                            @php $meta = $treeMeta[$m['method']] ?? ['verb' => strtoupper($m['method']), 'pill' => '#e5e7eb', 'fg' => '#6b7280']; @endphp
                            @if(!$m['disabled'])
                            <span class="api-tree-pill" style="background:{{ $meta['pill'] }};color:{{ $meta['fg'] }};">{{ $meta['verb'] }}</span>
                            @endif
                            @endforeach
                            @if($hasDisabledMethod)
                            <span style="font-size:0.6rem;color:#be123c;font-weight:600;">· some methods disabled</span>
                            @endif
                        </div>

                        <svg class="api-tree-chevron" :class="open && 'rotated'" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                        </svg>
                    </div>

                    <div class="api-tree-methods"
                         x-show="open"
                         x-transition:enter="transition-opacity ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0">
                        @foreach($methods as $m)
                        @php
                            $meta = $treeMeta[$m['method']] ?? ['verb' => strtoupper($m['method']), 'fg' => '#6b7280', 'bg' => '#f9fafb', 'path' => ''];
                            $path = '/' . $panel . '/' . $slug . $meta['path'];
                        @endphp
                        @if(!$m['disabled'])
                        <div class="api-tree-method">
                            <span class="api-tree-verb" style="background:{{ $meta['bg'] }};color:{{ $meta['fg'] }};">{{ $meta['verb'] }}</span>
                            <span class="api-tree-path" title="{{ $apiBaseUrl }}{{ $path }}">{{ $path }}</span>
                        </div>
                        @else
                        <div class="api-tree-method-disabled" title="This method is disabled">
                            <span class="api-tree-verb-disabled">{{ $meta['verb'] }}</span>
                            <span class="api-tree-path-disabled">{{ $path }}</span>
                            <svg class="api-tree-disabled-x" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>

                @else
                {{-- Disabled resource — no expand --}}
                <div class="api-tree-node">
                    <div class="api-tree-res-disabled">
                        <span class="api-tree-res-name-disabled">{{ $res['plural_label'] }}</span>
                        <span class="api-tree-res-panel">{{ $panel }}</span>
                        <span class="api-tree-disabled-badge">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                            Disabled
                        </span>
                    </div>
                </div>
                @endif

                @endforeach
            </div>
        </div>
        @endif
    </div>

</div>

{{-- ── Insights (audit log, tokens, webhooks, features) ─────────────────── --}}
<style>
.af-insights { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; margin-top: 24px; }
.af-ins-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
}
.dark .af-ins-card { background: #1f2937; border-color: #374151; }
.af-ins-title { font-size: 0.875rem; font-weight: 600; color: #111827; display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px; }
.dark .af-ins-title { color: #f9fafb; }
.af-ins-sub { font-size: 0.75rem; font-weight: 400; color: #9ca3af; }
.af-span-6 { grid-column: span 6; } .af-span-4 { grid-column: span 4; }
.af-span-8 { grid-column: span 8; } .af-span-12 { grid-column: span 12; }
@media (max-width: 1100px) { .af-span-6, .af-span-4, .af-span-8 { grid-column: span 12; } }

.af-bars { display: flex; align-items: flex-end; gap: 10px; height: 110px; padding-top: 6px; }
.af-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
.af-bar { width: 100%; max-width: 44px; border-radius: 6px 6px 2px 2px; background: linear-gradient(180deg, #818cf8, #6366f1); min-height: 3px; }
.af-bar--zero { background: rgba(148,163,184,0.25); }
.af-bar-label { font-size: 0.65rem; color: #9ca3af; }
.af-bar-count { font-size: 0.65rem; font-weight: 600; color: #6b7280; }
.dark .af-bar-count { color: #d1d5db; }

.af-ins-table { width: 100%; font-size: 12.5px; border-collapse: collapse; }
.af-ins-table th { text-align: left; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; padding: 0.5rem; border-bottom: 1px solid #f3f4f6; }
.dark .af-ins-table th { border-bottom-color: #374151; color: #6b7280; }
.af-ins-table td { padding: 0.625rem 0.5rem; border-top: 1px solid #f9fafb; vertical-align: middle; }
.dark .af-ins-table td { border-top-color: #374151; }

.af-mini-list { display: flex; flex-direction: column; gap: 9px; font-size: 12.5px; }
.af-mini-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.af-mini-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.af-mini-meta { font-size: 0.6875rem; color: #9ca3af; font-family: ui-monospace, monospace; }
.af-mini-value { font-size: 12px; font-weight: 700; white-space: nowrap; }

.af-chip { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.af-chip--on  { background: rgba(16,185,129,0.12); color: #10b981; }
.af-chip--off { background: rgba(148,163,184,0.16); color: #64748b; }
.af-chip--warn { background: rgba(245,158,11,0.14); color: #d97706; }
.af-chip--err { background: rgba(244,63,94,0.12); color: #f43f5e; }
.af-dot { width: 6px; height: 6px; border-radius: 999px; background: currentColor; }

.af-kpis { display: flex; gap: 22px; flex-wrap: wrap; margin-bottom: 4px; }
.af-kpi-v { font-size: 20px; font-weight: 700; line-height: 1.1; }
.af-kpi-l { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.af-empty { font-size: 0.8125rem; color: #9ca3af; padding: 14px 0; }
</style>

<div class="af-insights">

    {{-- Traffic: last 7 days --}}
    <div class="af-ins-card af-span-6">
        <div class="af-ins-title">
            Requests — Last 7 Days
            <span class="af-ins-sub">
                today: <strong>{{ $requestsToday }}</strong>
                @if ($avgResponseMs !== null) · avg {{ $avgResponseMs }} ms @endif
                @if ($errorRate !== null) · errors {{ $errorRate }}% @endif
            </span>
        </div>
        @if (empty($dailyRequests) || collect($dailyRequests)->sum('count') === 0)
            <div class="af-empty">No API traffic recorded yet — requests appear here as soon as clients start calling the API.</div>
        @else
            <div class="af-bars">
                @foreach ($dailyRequests as $day)
                    <div class="af-bar-col">
                        <span class="af-bar-count">{{ $day['count'] ?: '' }}</span>
                        <div class="af-bar {{ $day['count'] === 0 ? 'af-bar--zero' : '' }}" style="height: {{ max(3, $day['pct']) }}%"></div>
                        <span class="af-bar-label">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Top endpoints --}}
    <div class="af-ins-card af-span-3" style="grid-column: span 3;">
        <div class="af-ins-title">Top Endpoints <span class="af-ins-sub">7 days</span></div>
        @if (empty($topEndpoints))
            <div class="af-empty">Nothing yet.</div>
        @else
            <div class="af-mini-list">
                @foreach ($topEndpoints as $endpoint)
                    <div class="af-mini-row">
                        <div style="min-width:0">
                            <span class="dd-badge dd-badge-{{ ['GET' => 'get', 'POST' => 'post', 'PUT' => 'put', 'PATCH' => 'put', 'DELETE' => 'delete'][$endpoint['method']] ?? 'get' }}">{{ $endpoint['method'] }}</span>
                            <span class="af-mini-name">{{ $endpoint['resource'] }}</span>
                            <div class="af-mini-meta">{{ $endpoint['action'] }}</div>
                        </div>
                        <span class="af-mini-value">{{ $endpoint['count'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Top tokens --}}
    <div class="af-ins-card af-span-3" style="grid-column: span 3;">
        <div class="af-ins-title">Top Tokens <span class="af-ins-sub">all time</span></div>
        @if (empty($topTokens))
            <div class="af-empty">No token usage yet.</div>
        @else
            <div class="af-mini-list">
                @foreach ($topTokens as $token)
                    <div class="af-mini-row">
                        <div style="min-width:0">
                            <div class="af-mini-name">{{ $token['name'] }}</div>
                            <div class="af-mini-meta">{{ $token['prefix'] }}…</div>
                        </div>
                        <span class="af-mini-value">{{ $token['count'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent requests --}}
    <div class="af-ins-card af-span-8">
        <div class="af-ins-title">Recent API Requests</div>
        @if (empty($recentRequests))
            <div class="af-empty">No requests logged yet.</div>
        @else
            <table class="af-ins-table">
                <thead>
                    <tr><th>Method</th><th>Path</th><th>Action</th><th>Status</th><th>Time</th><th>When</th></tr>
                </thead>
                <tbody>
                    @foreach ($recentRequests as $req)
                        <tr>
                            <td><span class="dd-badge dd-badge-{{ ['GET' => 'get', 'POST' => 'post', 'PUT' => 'put', 'PATCH' => 'put', 'DELETE' => 'delete'][$req['method']] ?? 'get' }}">{{ $req['method'] }}</span></td>
                            <td style="font-family:ui-monospace,monospace; word-break:break-all;">{{ $req['path'] }}</td>
                            <td style="color:#64748b;">{{ $req['action'] }}</td>
                            <td><strong style="color: {{ $req['status'] < 400 ? '#10b981' : '#f43f5e' }};">{{ $req['status'] }}</strong></td>
                            <td>{{ $req['duration_ms'] }} ms</td>
                            <td style="color:#64748b;">{{ $req['when'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Right column: webhooks + expiring tokens + features --}}
    <div class="af-span-4" style="display:flex; flex-direction:column; gap:20px;">

        <div class="af-ins-card">
            <div class="af-ins-title">Webhook Health</div>
            @if (empty($webhookOverview))
                <div class="af-empty">No webhooks registered — add one under Developer Center → Webhooks.</div>
            @else
                <div class="af-mini-list">
                    @foreach ($webhookOverview as $hook)
                        <div class="af-mini-row">
                            <div style="min-width:0">
                                <div class="af-mini-name">{{ $hook['name'] }}</div>
                                <div class="af-mini-meta">last: {{ $hook['last'] }}</div>
                            </div>
                            <div style="white-space:nowrap">
                                @if ($hook['failures'] > 0)
                                    <span class="af-chip af-chip--err">{{ $hook['failures'] }} failed</span>
                                @endif
                                <span class="af-chip {{ $hook['active'] ? 'af-chip--on' : 'af-chip--off' }}">
                                    <span class="af-dot"></span>{{ $hook['active'] ? 'active' : 'paused' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="af-ins-card">
            <div class="af-ins-title">Expiring Tokens <span class="af-ins-sub">next 14 days</span></div>
            @if (empty($expiringTokens))
                <div class="af-empty">Nothing expiring soon 🎉</div>
            @else
                <div class="af-mini-list">
                    @foreach ($expiringTokens as $token)
                        <div class="af-mini-row">
                            <div style="min-width:0">
                                <div class="af-mini-name">{{ $token['name'] }}</div>
                                <div class="af-mini-meta">{{ $token['prefix'] }}…</div>
                            </div>
                            <span class="af-chip {{ $token['days'] <= 3 ? 'af-chip--err' : 'af-chip--warn' }}">
                                {{ $token['days'] }}d left{{ $token['notified'] ? ' · notified' : '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="af-ins-card">
            <div class="af-ins-title">Feature Status</div>
            <div style="display:flex; flex-wrap:wrap; gap:7px;">
                @foreach ($featureFlags as $flag)
                    <span class="af-chip {{ $flag['on'] ? 'af-chip--on' : 'af-chip--off' }}">
                        <span class="af-dot"></span>{{ $flag['label'] }}
                    </span>
                @endforeach
            </div>
        </div>

    </div>

</div>
</x-filament-panels::page>
