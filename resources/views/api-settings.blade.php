<x-filament-panels::page>

@include('filament-api-forge::partials.docs-styles')

<style>
.settings-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding-top: 8px;
}
@media (max-width: 900px) { .settings-wrap { grid-template-columns: 1fr; } }

.settings-card {
    background: var(--af-card-bg, rgba(255,255,255,0.04));
    border: 1px solid var(--af-border, rgba(148,163,184,0.15));
    border-radius: 12px;
    padding: 24px;
}
.dark .settings-card {
    background: rgba(30,41,59,0.6);
    border-color: rgba(71,85,105,0.4);
}

.settings-card-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--af-text, #0f172a);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dark .settings-card-title { color: #f1f5f9; }
.settings-card-title svg { width: 16px; height: 16px; opacity: 0.7; }

.settings-card-desc {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 20px;
    line-height: 1.5;
}

.settings-field-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.settings-field { flex: 1; }
.settings-label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
    margin-bottom: 6px;
}
.settings-input {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    font-family: ui-monospace, monospace;
    color: #0f172a;
    transition: border-color 0.15s;
    outline: none;
}
.dark .settings-input {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
}
.settings-input:focus { border-color: #f59e0b; }
.settings-input-prefix {
    font-size: 12px;
    color: #94a3b8;
    font-family: ui-monospace, monospace;
    margin-bottom: 6px;
}

.settings-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: opacity 0.15s;
    white-space: nowrap;
}
.settings-btn:hover { opacity: 0.85; }
.settings-btn-primary { background: #f59e0b; color: #1c1917; }
.settings-btn-ghost {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.dark .settings-btn-ghost { border-color: #334155; color: #94a3b8; }

.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 4px;
}
.preview-table th {
    text-align: left;
    padding: 6px 10px;
    color: #94a3b8;
    font-weight: 500;
    border-bottom: 1px solid rgba(148,163,184,0.15);
}
.preview-table td {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(148,163,184,0.08);
    font-family: ui-monospace, monospace;
    color: #475569;
    vertical-align: middle;
}
.dark .preview-table td { color: #94a3b8; }
.preview-table td.changed { color: #f59e0b; }
.preview-table .resource-label {
    font-family: inherit;
    font-weight: 500;
    color: #0f172a;
    font-size: 12px;
}
.dark .preview-table .resource-label { color: #e2e8f0; }
.arrow-icon { color: #34d399; margin: 0 4px; }

.info-block {
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 12px;
    color: #92400e;
    line-height: 1.6;
    margin-top: 16px;
}
.dark .info-block { color: #fde68a; background: rgba(245,158,11,0.06); }
.info-block code {
    background: rgba(245,158,11,0.15);
    padding: 1px 5px;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
    font-size: 11px;
}

.empty-state {
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    padding: 24px 0;
}
</style>

<div class="settings-wrap" style="grid-template-columns: 1fr 1fr; margin-bottom: 24px;">

    {{-- ── Left: Route Segment Config ── --}}
    <div class="settings-card">
        <div class="settings-card-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
            Route Segment
        </div>
        <p class="settings-card-desc">
            The URL segment that appears between the API prefix and the resource slug.
            By default this is the Filament panel ID (e.g. <code style="font-size:11px;background:rgba(148,163,184,0.15);padding:1px 5px;border-radius:4px;font-family:monospace">admin</code>),
            so routes look like <code style="font-size:11px;background:rgba(148,163,184,0.15);padding:1px 5px;border-radius:4px;font-family:monospace">/api/v1/admin/posts</code>.
            Set a custom value here to use a cleaner segment like <code style="font-size:11px;background:rgba(148,163,184,0.15);padding:1px 5px;border-radius:4px;font-family:monospace">filament</code> or <code style="font-size:11px;background:rgba(148,163,184,0.15);padding:1px 5px;border-radius:4px;font-family:monospace">api</code>.
        </p>

        <div class="settings-input-prefix">{{ url('/') }}/{{ $apiPrefix }}/<strong>{{ $routeSegment ?: '{panelId}' }}</strong>/{resource}</div>

        <div class="settings-field-row">
            <div class="settings-field">
                <label class="settings-label">Segment name</label>
                <input
                    type="text"
                    class="settings-input"
                    wire:model.live="routeSegment"
                    placeholder="e.g. filament, api, v1"
                    pattern="[a-zA-Z0-9\-_]*"
                    maxlength="40">
            </div>
            <button type="button" class="settings-btn settings-btn-primary"
                wire:click="saveRouteSegment"
                wire:loading.attr="disabled">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                Save
            </button>
            @if($routeSegment)
            <button type="button" class="settings-btn settings-btn-ghost"
                wire:click="resetRouteSegment">
                Reset
            </button>
            @endif
        </div>

        <div class="info-block">
            <strong>Note:</strong> This only affects how paths appear in the API documentation and OpenAPI spec.
            Actual HTTP requests work with both the old segment (<code>/admin/posts</code>) and the new one — no breaking changes.
        </div>
    </div>

    {{-- ── Right: Route Preview ── --}}
    <div class="settings-card">
        <div class="settings-card-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.58-3.01-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
            Route Preview
        </div>
        <p class="settings-card-desc">
            How your API resource paths will appear in the documentation. Highlighted in amber are paths that differ from the panel ID default.
        </p>

        @if(empty($routePreview))
            <div class="empty-state">No API resources found. Implement the <code>HasApi</code> interface on a Filament Resource.</div>
        @else
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Path in docs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($routePreview as $row)
                        <tr>
                            <td class="resource-label">{{ $row['label'] }}</td>
                            <td class="{{ $row['changed'] ? 'changed' : '' }}">
                                {{ $row['preview'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

{{-- ── Request Counters ── --}}
<div class="settings-card" style="margin-top: 0;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:24px; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <div class="settings-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                Request Counters
            </div>
            <p class="settings-card-desc">
                Cumulative API request counts per token. Resets all counters to zero — useful for a fresh start after testing or a new release.
            </p>
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:2px;">Total Requests</div>
                    <div style="font-size:28px; font-weight:700; color:var(--text-title, #0f172a); font-variant-numeric: tabular-nums;" title="{{ number_format($totalRequests) }}">
                        {{ $formattedTotalRequests }}
                    </div>
                </div>
            </div>
            <button type="button" class="settings-btn settings-btn-ghost"
                style="border-color: #f87171; color: #ef4444;"
                wire:click="resetRequestCounts"
                wire:confirm="Reset all request counters to zero? This cannot be undone."
                wire:loading.attr="disabled">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V3.93a.75.75 0 0 0-1.5 0V6.36l-.31-.31A7 7 0 0 0 3.239 9.187a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" /></svg>
                Reset All Counters
            </button>
        </div>

        {{-- Per-token breakdown --}}
        @if(!empty($tokenCounts))
        <div style="flex:2; min-width:300px;">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th style="text-align:right;">Requests</th>
                        <th>Last Used</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokenCounts as $t)
                    <tr>
                        <td>
                            <span class="resource-label">{{ $t['name'] }}</span>
                            <span style="color:#94a3b8; font-size:11px; margin-left:6px;">{{ $t['prefix'] }}…</span>
                        </td>
                        <td style="text-align:right; font-weight:600;" title="{{ number_format($t['count']) }}">
                            {{ $t['formatted'] }}
                        </td>
                        <td style="color:#94a3b8;">{{ $t['last_used'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

</x-filament-panels::page>
