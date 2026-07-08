@php
$methodMeta = [
    'index'       => ['label'=>'List',         'http'=>'GET',    'color'=>'get',    'hasBody'=>false, 'desc'=>'Returns a paginated list of records.'],
    'show'        => ['label'=>'Single',       'http'=>'GET',    'color'=>'get',    'hasBody'=>false, 'desc'=>'Returns a single record by its ID.'],
    'store'       => ['label'=>'Create',       'http'=>'POST',   'color'=>'post',   'hasBody'=>true,  'desc'=>'Creates a new record with the given fields.'],
    'update'      => ['label'=>'Update',       'http'=>'PUT',    'color'=>'put',    'hasBody'=>true,  'desc'=>'Updates an existing record by ID.'],
    'destroy'     => ['label'=>'Delete',       'http'=>'DELETE', 'color'=>'delete', 'hasBody'=>false, 'desc'=>'Deletes a record by its ID.'],
    'export'      => ['label'=>'Export',       'http'=>'GET',    'color'=>'get',    'hasBody'=>false, 'desc'=>'Streams the filtered result set as CSV or JSON.'],
    'restore'     => ['label'=>'Restore',      'http'=>'POST',   'color'=>'post',   'hasBody'=>false, 'desc'=>'Restores a soft-deleted record by its ID.'],
    'forceDelete' => ['label'=>'Force Delete', 'http'=>'DELETE', 'color'=>'delete', 'hasBody'=>false, 'desc'=>'Permanently deletes a soft-deleted record by its ID.'],
];
$globalDefault  = config('filament-api-forge.rate_limit', 60);
$configVersions = config('filament-api-forge.versions');
$multiVersion   = is_array($configVersions) && ! empty($configVersions);
@endphp

<style>
/* ── Method tabs ─────────────────────────────────────────── */
.ac-method-tabs {
    display: flex; flex-wrap: wrap; gap: 2px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 18px;
}
.dark .ac-method-tabs { border-bottom-color: #374151; }

.ac-method-tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 16px; margin-bottom: -1px;
    font-size: 0.8125rem; font-weight: 600; color: #6b7280;
    background: none; border: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color .15s, border-color .15s;
}
.ac-method-tab:hover { color: #111827; }
.dark .ac-method-tab:hover { color: #f9fafb; }
.ac-method-tab-active { color: #111827; border-bottom-color: #6366f1; }
.dark .ac-method-tab-active { color: #f9fafb; }

.ac-tab-dot { width: 7px; height: 7px; border-radius: 999px; background: #10b981; flex-shrink: 0; }
.ac-tab-dot-off { background: #d1d5db; }
.dark .ac-tab-dot-off { background: #4b5563; }

[x-cloak] { display: none !important; }

/* ── Resource tabs ───────────────────────────────────────── */
.ac-res-tabs {
    display: flex; flex-wrap: wrap; gap: 2px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 20px;
}
.dark .ac-res-tabs { border-bottom-color: #374151; }

.ac-res-tab {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 11px 20px; margin-bottom: -1px;
    font-size: 0.875rem; font-weight: 600; color: #6b7280;
    background: none; border: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color .15s, border-color .15s;
}
.ac-res-tab:hover { color: #111827; }
.dark .ac-res-tab:hover { color: #f9fafb; }
.ac-res-tab-active { color: #111827; border-bottom-color: #6366f1; }
.dark .ac-res-tab-active { color: #f9fafb; }

.ac-ver-chip {
    display: inline-flex; align-items: center;
    font-family: ui-monospace, monospace;
    font-size: 10px; font-weight: 700;
    padding: 1px 7px; border-radius: 999px;
    background: rgba(59,130,246,0.12); color: #2563eb;
}
.dark .ac-ver-chip { background: rgba(96,165,250,0.16); color: #93c5fd; }
.ac-ver-chip--off { background: rgba(148,163,184,0.15); color: #94a3b8; text-decoration: line-through; }

.ac-method-panel { padding: 2px 2px 6px; }
.ac-method-panel-head {
    display: flex; align-items: center; gap: 10px; margin-bottom: 6px;
}
.ac-method-panel-desc { font-size: 0.8125rem; color: #6b7280; margin: 0 0 14px; }
.dark .ac-method-panel-desc { color: #9ca3af; }
.ac-method-panel-off { opacity: 0.55; }
</style>

<div class="ac-page" x-data="{ resTab: 0 }">

    {{-- Resource tab bar --}}
    @if(count($resourceStates))
    <div class="ac-res-tabs">
        @foreach($resourceStates as $rClass => $state)
            <button type="button"
                class="ac-res-tab"
                :class="resTab === {{ $loop->index }} ? 'ac-res-tab-active' : ''"
                x-on:click="resTab = {{ $loop->index }}">
                <span class="ac-tab-dot {{ !$state['enabled'] ? 'ac-tab-dot-off' : '' }}"></span>
                {{ class_basename($rClass) }}
                <span class="ac-resource-tag">{{ $state['tag'] }}</span>
                @if($multiVersion)
                    @foreach($configVersions as $v)
                        @if($state['versions'] === null || in_array($v, $state['versions']))
                            <span class="ac-ver-chip">{{ $v }}</span>
                        @endif
                    @endforeach
                @endif
            </button>
        @endforeach
    </div>
    @endif

    @forelse($resourceStates as $rClass => $state)
        @php
            $isEnabled  = $state['enabled'];
            $safeClass  = addslashes($rClass);
            $shortName  = class_basename($rClass);
            $rateLimit  = $state['rate_limit'];
            $ipsStr     = implode("\n", $state['allowed_ips'] ?? []);
            $tabMethods = array_values(array_intersect(array_keys($methodMeta), $state['allowed_methods']));
        @endphp

        {{-- Alpine state: active method tab + form values for resource + each method --}}
        <div class="ac-card {{ !$isEnabled ? 'ac-card-off' : '' }}"
             x-show="resTab === {{ $loop->index }}" x-cloak
             x-data="{
                tab: '{{ $tabMethods[0] ?? 'index' }}',
                resRateLimit: {{ $rateLimit ?? 'null' }},
                resIps: {{ Js::from($ipsStr) }},
                methods: {
                    @foreach($state['allowed_methods'] as $m)
                    '{{ $m }}': {
                        rateLimit: {{ $state['method_settings'][$m]['rate_limit'] ?? 'null' }},
                        ips: {{ Js::from(implode("\n", $state['method_settings'][$m]['allowed_ips'] ?? [])) }}
                    },
                    @endforeach
                }
             }">

            {{-- ── Header ───────────────────────────────────────── --}}
            <div class="ac-card-header">
                <div class="ac-card-identity">
                    <span class="ac-resource-name">{{ $shortName }}</span>
                    <span class="ac-resource-tag">{{ $state['tag'] }}</span>
                    @if($multiVersion)
                        @foreach($configVersions as $v)
                            @php $inVersion = $state['versions'] === null || in_array($v, $state['versions']); @endphp
                            <span class="ac-ver-chip {{ !$inVersion ? 'ac-ver-chip--off' : '' }}"
                                  title="{{ $inVersion ? 'Available on ' . $v : 'Not exposed on ' . $v }}">{{ $v }}</span>
                        @endforeach
                    @endif
                    @if(!$isEnabled)
                        <span class="ac-badge-off">Disabled</span>
                    @else
                        @if($rateLimit)
                            <span class="ac-badge-info">{{ $rateLimit }} req/min</span>
                        @endif
                    @endif
                </div>
                <div class="ac-header-actions">
                    <button type="button"
                        class="ac-toggle {{ $isEnabled ? 'ac-toggle-on' : 'ac-toggle-off' }}"
                        wire:click="toggleResource('{{ $safeClass }}')"
                        wire:loading.attr="disabled">
                        <span class="ac-toggle-thumb"></span>
                    </button>
                </div>
            </div>

            {{-- ── Body ─────────────────────────────────────────── --}}
            <div>

                @if($isEnabled)

                {{-- ── Resource-level settings ──────────────────── --}}
                <div class="ac-section">
                    <div class="ac-section-title">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="ac-section-icon"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                        Resource Default Settings
                        <span class="ac-section-hint">Applied to all methods unless overridden per-method</span>
                    </div>
                    <div class="ac-settings-row">
                        <div class="ac-field">
                            <label class="ac-label">Rate Limit <span class="ac-label-hint">req/min</span></label>
                            <input type="number" min="1" max="10000" class="ac-input"
                                :placeholder="'Global ({{ $globalDefault }})'"
                                x-model.number="resRateLimit">
                        </div>
                        <div class="ac-field ac-field-grow">
                            <label class="ac-label">Allowed IPs <span class="ac-label-hint">one per line — empty = all IPs allowed</span></label>
                            <textarea class="ac-textarea" rows="2"
                                placeholder="192.168.1.0/24&#10;10.0.0.1"
                                x-model="resIps"></textarea>
                        </div>
                        <div class="ac-field-save">
                            <button type="button" class="ac-save-btn"
                                x-on:click="$wire.saveResourceSettings('{{ $safeClass }}', resRateLimit, resIps)"
                                wire:loading.attr="disabled">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="ac-btn-icon"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Save Resource
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Per-method settings ───────────────────────── --}}
                <div class="ac-section">
                    <div class="ac-section-title">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="ac-section-icon"><path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        Methods
                        <span class="ac-section-hint">Override rate limit and IP rules per method</span>
                    </div>
                    {{-- Tab bar --}}
                    <div class="ac-method-tabs">
                        @foreach($tabMethods as $method)
                            @php
                                $meta = $methodMeta[$method];
                                $isOn = !in_array($method, $state['disabled_methods']);
                            @endphp
                            <button type="button"
                                class="ac-method-tab"
                                :class="tab === '{{ $method }}' ? 'ac-method-tab-active' : ''"
                                x-on:click="tab = '{{ $method }}'">
                                <span class="ac-tab-dot {{ !$isOn ? 'ac-tab-dot-off' : '' }}"></span>
                                <span class="ac-method-badge ac-badge-{{ $meta['color'] }}">{{ $meta['http'] }}</span>
                                {{ $meta['label'] }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Tab panels --}}
                    @foreach($tabMethods as $method)
                        @php
                            $meta    = $methodMeta[$method];
                            $isOn    = !in_array($method, $state['disabled_methods']);
                            $fields  = $state['model_fields'];
                            $filters = $state['api_config']['allowed_filters'] ?? [];
                        @endphp

                        <div class="ac-method-panel {{ !$isOn ? 'ac-method-panel-off' : '' }}"
                             x-show="tab === '{{ $method }}'" x-cloak>

                            {{-- Panel header: label + enable toggle --}}
                            <div class="ac-method-panel-head">
                                <span class="ac-method-badge ac-badge-{{ $meta['color'] }}">{{ $meta['http'] }}</span>
                                <span class="ac-method-name">{{ $meta['label'] }}</span>
                                <button type="button"
                                    class="ac-mini-toggle {{ $isOn ? 'ac-mtoggle-on' : 'ac-mtoggle-off' }}"
                                    wire:click="toggleMethod('{{ $safeClass }}', '{{ $method }}')"
                                    wire:loading.attr="disabled">
                                    <span class="ac-mtoggle-thumb"></span>
                                </button>
                            </div>

                            <p class="ac-method-panel-desc">{{ $meta['desc'] }}</p>

                            {{-- Parameters / Fields info --}}
                            @if($method === 'index')
                                <div class="ac-info-block">
                                    <div class="ac-info-label">Query Params</div>
                                    <div class="ac-chips">
                                        <span class="ac-chip">per_page</span>
                                        <span class="ac-chip">page</span>
                                        @if(!empty($filters))
                                            @foreach($filters as $f)<span class="ac-chip ac-chip-accent">filter[{{ $f }}]</span>@endforeach
                                        @endif
                                        <span class="ac-chip">sort</span>
                                        <span class="ac-chip">include</span>
                                    </div>
                                </div>
                            @elseif($method === 'export')
                                <div class="ac-info-block">
                                    <div class="ac-info-label">Query Params</div>
                                    <div class="ac-chips">
                                        <span class="ac-chip">format=csv|json</span>
                                        @if(!empty($filters))
                                            @foreach($filters as $f)<span class="ac-chip ac-chip-accent">filter[{{ $f }}]</span>@endforeach
                                        @endif
                                        <span class="ac-chip">sort</span>
                                    </div>
                                </div>
                            @elseif(in_array($method, ['show', 'destroy', 'restore', 'forceDelete']))
                                <div class="ac-info-block">
                                    <div class="ac-info-label">Path</div>
                                    <div class="ac-chips">
                                        <span class="ac-chip">{{'{'}}id{{'}'}}@if($method === 'restore')/restore @elseif($method === 'forceDelete')/force @endif</span>
                                    </div>
                                </div>
                            @elseif($meta['hasBody'] && !empty($fields))
                                <div class="ac-info-block">
                                    <div class="ac-info-label">Request Fields</div>
                                    <div class="ac-chips">
                                        @foreach($fields as $f)<span class="ac-chip ac-chip-accent">{{ $f }}</span>@endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Per-method rate limit + IPs — same layout as the resource row --}}
                            <div class="ac-settings-row">
                                <div class="ac-field">
                                    <label class="ac-label">Rate Limit <span class="ac-label-hint">req/min</span></label>
                                    <input type="number" min="1" max="10000" class="ac-input"
                                        :placeholder="resRateLimit ? 'Resource (' + resRateLimit + ')' : 'Global ({{ $globalDefault }})'"
                                        x-model.number="methods['{{ $method }}'].rateLimit">
                                </div>
                                <div class="ac-field ac-field-grow">
                                    <label class="ac-label">Allowed IPs <span class="ac-label-hint">one per line — empty = inherit resource rules</span></label>
                                    <textarea class="ac-textarea" rows="2"
                                        placeholder="Leave empty to inherit resource rules"
                                        x-model="methods['{{ $method }}'].ips"></textarea>
                                </div>
                                <div class="ac-field-save">
                                    <button type="button" class="ac-save-btn"
                                        x-on:click="$wire.saveMethodSettings('{{ $safeClass }}', '{{ $method }}', methods['{{ $method }}'].rateLimit, methods['{{ $method }}'].ips)"
                                        wire:loading.attr="disabled">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="ac-btn-icon"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Save Method
                                    </button>
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>

                @else
                <div class="ac-disabled-msg">
                    Resource is disabled. Enable it to manage settings.
                </div>
                @endif

            </div>
        </div>
    @empty
        <div class="ac-empty">
            No API resources found. Implement the <code>HasApi</code> interface on a Filament Resource.
        </div>
    @endforelse

</div>
