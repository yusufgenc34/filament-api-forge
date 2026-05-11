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

    {{-- ── Discovered resources ─────────────────────────────── --}}
    <div class="dd-card">
        <div class="dd-card-head">
            <svg class="dd-card-head-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v11.5A2.25 2.25 0 004.25 18h11.5A2.25 2.25 0 0018 15.75V4.25A2.25 2.25 0 0015.75 2H4.25zM15 5.75a.75.75 0 00-1.5 0v8.5a.75.75 0 001.5 0v-8.5zm-8.5 6a.75.75 0 00-1.5 0v2.5a.75.75 0 001.5 0v-2.5zM11 10a.75.75 0 00-1.5 0v4.25a.75.75 0 001.5 0V10z" clip-rule="evenodd"/>
            </svg>
            <span class="dd-card-head-title">API Resources</span>
            <span class="dd-card-head-sub">Base: <code style="font-size:0.72rem;color:#6b7280;">{{ $apiBaseUrl }}</code></span>
        </div>

        @if(count($apiResources) > 0)
        <table class="dd-table">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>Base Endpoint</th>
                    <th>HTTP Methods</th>
                </tr>
            </thead>
            <tbody>
                @foreach($apiResources as $resource)
                @php
                    $slug    = $resource['slug'];
                    $panel   = $resource['panel_id'];
                    $methods = $resource['api_config']['allowed_methods'] ?? [];
                    $baseEp  = "/{$panel}/{$slug}";

                    $httpMethods = collect($methods)->map(fn($m) => $httpMap[$m][0] ?? strtoupper($m))->unique()->values();
                @endphp
                <tr>
                    <td>
                        <div class="dd-res-name">{{ $resource['plural_label'] }}</div>
                        <div class="dd-res-panel">panel: {{ $panel }}</div>
                    </td>
                    <td>
                        <span class="dd-endpoint">{{ $apiBaseUrl }}/{{ $panel }}/{{ $slug }}</span>
                    </td>
                    <td>
                        <div class="dd-methods">
                            @foreach($methods as $method)
                            @php
                                [$http, $color] = $httpMap[$method] ?? [strtoupper($method), 'get'];
                                $label = $http . ($method === 'index' ? ' list' : ($method === 'show' ? ' /{id}' : ($method === 'store' ? '' : ($method === 'update' ? ' /{id}' : ' /{id}'))));
                            @endphp
                            <span class="dd-badge dd-badge-{{ $color }}">{{ $http }}{{ in_array($method, ['show','update','destroy']) ? ' /{id}' : '' }}</span>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="dd-empty">
            No resources found. Add <code>implements HasApi</code> to a Filament Resource.
        </div>
        @endif
    </div>

    {{-- ── Authentication & Quick Start ────────────────────── --}}
    <div class="dd-card">
        <div class="dd-card-head">
            <svg class="dd-card-head-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8 7a5 5 0 113.61 4.804L11 13H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.414-5.414A5 5 0 018 7zm2-3a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
            </svg>
            <span class="dd-card-head-title">Authentication & Quick Start</span>
        </div>

        <div class="dd-auth-grid">
            {{-- Left: how auth works --}}
            <div class="dd-auth-col">
                <div class="dd-auth-col-title">Bearer Token Authentication</div>
                <div class="dd-copy-wrap" x-data="{copied:false}">
                    <div class="dd-code">
                        <span class="c"># All API requests require a Bearer token</span><br>
                        <span class="kw">curl</span> -H <span class="st">"Authorization: Bearer forge_..."</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; <span class="url">{{ $apiBaseUrl }}/..</span>
                    </div>
                </div>

                <div style="margin-top:0.875rem;padding:0.75rem 1rem;background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;">
                    <div style="font-size:0.72rem;font-weight:600;color:#374151;margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.05em;">Token Format</div>
                    <code style="font-family:ui-monospace,monospace;font-size:0.8rem;color:#4b5563;">forge_<span style="color:#9ca3af;">&lt;40-char random&gt;</span></code>
                    <div style="margin-top:0.375rem;font-size:0.72rem;color:#9ca3af;">SHA-256 hashed at rest · shown once at creation</div>
                </div>

                <div style="margin-top:0.875rem;">
                    <div style="font-size:0.72rem;font-weight:600;color:#374151;margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.05em;">Scopes</div>
                    <div style="display:flex;flex-direction:column;gap:0.35rem;font-size:0.8rem;">
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <code style="background:#eff6ff;color:#1d4ed8;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.7rem;">read</code>
                            <span style="color:#6b7280;">GET requests</span>
                        </div>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <code style="background:#fffbeb;color:#b45309;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.7rem;">write</code>
                            <span style="color:#6b7280;">POST / PUT / PATCH requests</span>
                        </div>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <code style="background:#fff1f2;color:#be123c;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.7rem;">delete</code>
                            <span style="color:#6b7280;">DELETE requests</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: example requests --}}
            <div class="dd-auth-col">
                <div class="dd-auth-col-title">Example Requests</div>

                @if(count($apiResources) > 0)
                @php
                    $first   = $apiResources[0];
                    $panel   = $first['panel_id'];
                    $slug    = $first['slug'];
                    $filters = $first['api_config']['allowed_filters'] ?? [];
                    $sorts   = $first['api_config']['allowed_sorts'] ?? [];
                    $includes= $first['api_config']['allowed_includes'] ?? [];
                    $qs = [];
                    if(!empty($filters)) $qs[] = 'filter['.($filters[0]).']=value';
                    if(!empty($sorts))   $qs[] = 'sort=-'.($sorts[0]);
                    if(!empty($includes))$qs[] = 'include='.($includes[0]);
                @endphp

                @php
                    $cmd1 = 'curl -H "Authorization: Bearer forge_..." ' . $apiBaseUrl . '/' . $panel . '/' . $slug;
                @endphp
                <div class="dd-copy-wrap" x-data="{ copied: false, text: @js($cmd1) }">
                    <div class="dd-code">
                        <span class="c"># List {{ $first['plural_label'] }}</span><br>
                        <span class="kw">curl</span> -H <span class="st">"Authorization: Bearer forge_..."</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; <span class="url">{{ $apiBaseUrl }}/{{ $panel }}/{{ $slug }}</span>
                    </div>
                    <button class="dd-copy-btn" @click="navigator.clipboard.writeText(text); copied=true; setTimeout(()=>copied=false,2000)">
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                </div>

                @if(!empty($qs))
                @php
                    $qstr = implode('&', $qs);
                    $cmd2 = 'curl -H "Authorization: Bearer forge_..." "' . $apiBaseUrl . '/' . $panel . '/' . $slug . '?' . $qstr . '"';
                @endphp
                <div class="dd-copy-wrap" style="margin-top:0.625rem;" x-data="{ copied: false, text: @js($cmd2) }">
                    <div class="dd-code">
                        <span class="c"># With filters & sorting</span><br>
                        <span class="kw">curl</span> -H <span class="st">"Authorization: Bearer forge_..."</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; <span class="url">"{{ $apiBaseUrl }}/{{ $panel }}/{{ $slug }}?{{ $qstr }}"</span>
                    </div>
                    <button class="dd-copy-btn" @click="navigator.clipboard.writeText(text); copied=true; setTimeout(()=>copied=false,2000)">
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                </div>
                @endif

                <div class="dd-copy-wrap" style="margin-top:0.625rem;" x-data="{copied:false}">
                    <div class="dd-code">
                        <span class="c"># Create a record (write scope required)</span><br>
                        <span class="kw">curl</span> -X POST \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; -H <span class="st">"Authorization: Bearer forge_..."</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; -H <span class="st">"Content-Type: application/json"</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; -d <span class="st">'{"field": "value"}'</span> \<br>
                        &nbsp;&nbsp;&nbsp;&nbsp; <span class="url">{{ $apiBaseUrl }}/{{ $panel }}/{{ $slug }}</span>
                    </div>
                </div>
                @else
                <div class="dd-empty" style="padding:1.5rem 0 0.5rem;">
                    No resources discovered yet.
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
</x-filament-panels::page>
