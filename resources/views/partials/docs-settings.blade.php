@php
$methodMeta = [
    'index'   => ['label'=>'GET List',    'http'=>'GET',    'color'=>'get',    'hasBody'=>false, 'desc'=>'Returns a paginated list of records.'],
    'show'    => ['label'=>'GET Single',  'http'=>'GET',    'color'=>'get',    'hasBody'=>false, 'desc'=>'Returns a single record by its ID.'],
    'store'   => ['label'=>'POST',        'http'=>'POST',   'color'=>'post',   'hasBody'=>true,  'desc'=>'Creates a new record with the given fields.'],
    'update'  => ['label'=>'PUT / PATCH', 'http'=>'PUT',    'color'=>'put',    'hasBody'=>true,  'desc'=>'Updates an existing record by ID.'],
    'destroy' => ['label'=>'DELETE',      'http'=>'DELETE', 'color'=>'delete', 'hasBody'=>false, 'desc'=>'Permanently deletes a record by its ID.'],
];
$globalDefault = config('filament-api-forge.rate_limit', 60);
@endphp

<div class="ac-page">

    @forelse($resourceStates as $rClass => $state)
        @php
            $isEnabled = $state['enabled'];
            $safeClass = addslashes($rClass);
            $shortName = class_basename($rClass);
            $rateLimit = $state['rate_limit'];
            $ipsStr    = implode("\n", $state['allowed_ips'] ?? []);
        @endphp

        {{-- Alpine state: open + form values for resource + each method --}}
        <div class="ac-card {{ !$isEnabled ? 'ac-card-off' : '' }}"
             x-data="{
                open: false,
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

            {{-- ── Header (always visible, click to collapse) ──── --}}
            <div class="ac-card-header" x-on:click="open = !open" style="cursor:pointer;">
                <div class="ac-card-identity">
                    <span class="ac-resource-name">{{ $shortName }}</span>
                    <span class="ac-resource-tag">{{ $state['tag'] }}</span>
                    @if(!$isEnabled)
                        <span class="ac-badge-off">Disabled</span>
                    @else
                        @if($rateLimit)
                            <span class="ac-badge-info">{{ $rateLimit }} req/min</span>
                        @endif
                    @endif
                </div>
                <div class="ac-header-actions" x-on:click.stop>
                    <button type="button"
                        class="ac-toggle {{ $isEnabled ? 'ac-toggle-on' : 'ac-toggle-off' }}"
                        wire:click="toggleResource('{{ $safeClass }}')"
                        wire:loading.attr="disabled">
                        <span class="ac-toggle-thumb"></span>
                    </button>
                    <span class="ac-chevron" :class="open ? 'ac-chevron-open' : ''">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </span>
                </div>
            </div>

            {{-- ── Collapsible body ─────────────────────────────── --}}
            <div x-show="open" x-collapse>

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
                    <div class="ac-methods-grid">
                        @foreach($state['allowed_methods'] as $method)
                            @if(!isset($methodMeta[$method])) @continue @endif
                            @php
                                $meta   = $methodMeta[$method];
                                $isOn   = !in_array($method, $state['disabled_methods']);
                                $fields = $state['model_fields'];
                                $filters = $state['api_config']['allowed_filters'] ?? [];
                            @endphp

                            <div class="ac-method-card {{ !$isOn ? 'ac-method-card-off' : '' }}">

                                {{-- Method header --}}
                                <div class="ac-method-header">
                                    <span class="ac-method-badge ac-badge-{{ $meta['color'] }}">{{ $meta['http'] }}</span>
                                    <span class="ac-method-name">{{ $meta['label'] }}</span>
                                    <button type="button"
                                        class="ac-mini-toggle {{ $isOn ? 'ac-mtoggle-on' : 'ac-mtoggle-off' }}"
                                        wire:click="toggleMethod('{{ $safeClass }}', '{{ $method }}')"
                                        wire:loading.attr="disabled">
                                        <span class="ac-mtoggle-thumb"></span>
                                    </button>
                                </div>

                                <p class="ac-method-desc">{{ $meta['desc'] }}</p>

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
                                @elseif(in_array($method, ['show', 'destroy']))
                                    <div class="ac-info-block">
                                        <div class="ac-info-label">Path</div>
                                        <div class="ac-chips"><span class="ac-chip">{{'{'}}id{{'}'}}</span></div>
                                    </div>
                                @elseif($meta['hasBody'] && !empty($fields))
                                    <div class="ac-info-block">
                                        <div class="ac-info-label">Request Fields</div>
                                        <div class="ac-chips">
                                            @foreach($fields as $f)<span class="ac-chip ac-chip-accent">{{ $f }}</span>@endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Per-method rate limit + IPs --}}
                                <div class="ac-method-settings">
                                    <div>
                                        <label class="ac-label">Rate Limit <span class="ac-label-hint">req/min</span></label>
                                        <input type="number" min="1" max="10000" class="ac-input ac-input-sm"
                                            :placeholder="resRateLimit ? 'Resource (' + resRateLimit + ')' : 'Global ({{ $globalDefault }})'"
                                            x-model.number="methods['{{ $method }}'].rateLimit">
                                    </div>
                                    <div class="ac-method-ips">
                                        <label class="ac-label">Allowed IPs <span class="ac-label-hint">empty = inherit</span></label>
                                        <textarea class="ac-textarea ac-textarea-sm" rows="2"
                                            placeholder="Leave empty to inherit resource rules"
                                            x-model="methods['{{ $method }}'].ips"></textarea>
                                    </div>
                                </div>

                                <button type="button" class="ac-method-save-btn"
                                    x-on:click="$wire.saveMethodSettings('{{ $safeClass }}', '{{ $method }}', methods['{{ $method }}'].rateLimit, methods['{{ $method }}'].ips)"
                                    wire:loading.attr="disabled">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="ac-btn-icon"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Save Method
                                </button>

                            </div>
                        @endforeach
                    </div>
                </div>

                @else
                <div class="ac-disabled-msg">
                    Resource is disabled. Enable it to manage settings.
                </div>
                @endif

            </div>{{-- /x-show --}}
        </div>
    @empty
        <div class="ac-empty">
            No API resources found. Implement the <code>HasApi</code> interface on a Filament Resource.
        </div>
    @endforelse

</div>
