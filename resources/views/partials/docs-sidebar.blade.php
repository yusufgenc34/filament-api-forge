<nav class="docs-sb">
    <div class="docs-sb-head">
        <div style="font-weight:700;font-size:.875rem;color:#f1f5f9;line-height:1.25;">{{ $apiTitle }}</div>
        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.3rem;">
            @if(count($availableVersions) > 1)
                <span style="display:inline-flex;gap:2px;background:#0f172a;border:1px solid #334155;border-radius:.3rem;padding:2px;">
                    @foreach($availableVersions as $v)
                        <button type="button"
                            wire:click="selectVersion('{{ $v }}')"
                            style="font-size:.575rem;font-weight:700;font-family:monospace;padding:.15rem .45rem;border:none;border-radius:.2rem;cursor:pointer;
                                   background: {{ $docsVersion === $v ? '#1e3a8a' : 'transparent' }};
                                   color: {{ $docsVersion === $v ? '#93c5fd' : '#64748b' }};">
                            {{ $v }}
                        </button>
                    @endforeach
                </span>
            @else
                <span style="font-size:.575rem;padding:.1rem .375rem;border-radius:.2rem;background:#1e3a8a;color:#93c5fd;font-weight:700;font-family:monospace;">{{ $version }}</span>
            @endif
            <span style="font-size:.6rem;color:#64748b;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:155px;" title="{{ $baseUrl }}">{{ $baseUrl }}</span>
        </div>
        <a href="{{ $openApiUrl }}" download="openapi.json" class="export-btn">
            <svg class="export-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
            Export OpenAPI JSON
        </a>
    </div>

    <div class="docs-sb-scroll">
        @foreach($groupedEndpoints as $tag => $endpoints)
            <div>
                <div class="sg-head" wire:click="toggleGroup('{{ $tag }}')">
                    <span class="sg-label">{{ $tag }}</span>
                    <svg class="sg-chevron {{ in_array($tag, $collapsedGroups) ? 'is-collapsed' : '' }}" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </div>
                @if(!in_array($tag, $collapsedGroups))
                    @foreach($endpoints as $ep)
                        <div class="ep-row {{ $selectedEndpointId === $ep['id'] ? 'is-active' : '' }}"
                             wire:click="selectEndpoint('{{ $ep['id'] }}')">
                            <span class="mb mb-{{ strtolower($ep['method']) }}">{{ $ep['method'] }}</span>
                            <span class="ep-path" title="{{ $ep['path'] }}">{{ $ep['path'] }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach

        @if(!empty($guides))
            <div style="margin-top:.625rem;padding-top:.5rem;border-top:1px solid #334155;">
                <div class="sg-head"><span class="sg-label">Guides</span></div>
                @foreach($guides as $key => $guide)
                    <div class="schema-row {{ $selectedGuideKey === $key ? 'is-active' : '' }}"
                         wire:click="selectGuide('{{ $key }}')">
                        <svg class="schema-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.75 16.82A7.462 7.462 0 0115 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0018 15.06v-11a.75.75 0 00-.546-.721A9.006 9.006 0 0015 3a8.963 8.963 0 00-4.25 1.065V16.82zM9.25 4.065A8.963 8.963 0 005 3c-.85 0-1.673.118-2.454.339A.75.75 0 002 4.06v11a.75.75 0 00.954.721A7.506 7.506 0 015 15.5c1.579 0 3.042.487 4.25 1.32V4.065z"/>
                        </svg>
                        <span class="sr-name">{{ $guide['title'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if(!empty($schemas))
            <div style="margin-top:.625rem;padding-top:.5rem;border-top:1px solid #334155;">
                <div class="sg-head"><span class="sg-label">Schemas</span></div>
                @foreach($schemas as $name => $schema)
                    <div class="schema-row {{ $selectedSchemaName === $name && !$selectedEndpoint ? 'is-active' : '' }}"
                         wire:click="selectSchema('{{ $name }}')">
                        <svg class="schema-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm11 1H6v8h8V6z" clip-rule="evenodd"/>
                        </svg>
                        <span class="sr-name">{{ $name }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</nav>
