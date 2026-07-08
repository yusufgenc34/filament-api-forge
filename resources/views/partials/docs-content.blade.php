<div class="docs-content">
@if($selectedEndpoint)

    <div style="display:flex;align-items:flex-start;gap:.75rem;flex-wrap:wrap;">
        <span class="mbc mbc-{{ strtolower($selectedEndpoint['method']) }}" style="margin-top:.2rem;">{{ $selectedEndpoint['method'] }}</span>
        <span class="ep-title">{{ $selectedEndpoint['path'] }}</span>
    </div>
    @if($selectedEndpoint['summary'])
        <p class="ep-summary">{{ $selectedEndpoint['summary'] }}</p>
    @endif
    @if(!empty($selectedEndpoint['operation']['security']))
        <div class="auth-badge">
            <svg class="auth-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            Requires Bearer Token (Sanctum)
        </div>
    @endif

    <hr class="divider">

    @php
        $pathParams  = array_values(array_filter($selectedEndpoint['operation']['parameters'] ?? [], fn($p) => ($p['in'] ?? '') === 'path'));
        $queryParams = array_values(array_filter($selectedEndpoint['operation']['parameters'] ?? [], fn($p) => ($p['in'] ?? '') === 'query'));
    @endphp

    @if(!empty($pathParams))
        <span class="sec-label">Path Parameters</span>
        <table class="ptbl">
            <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
            <tbody>
                @foreach($pathParams as $p)
                    <tr>
                        <td><span class="pname">{{ $p['name'] }}</span><span class="req-tag">required</span></td>
                        <td><span class="ptype">{{ $p['schema']['type'] ?? 'string' }}</span></td>
                        <td style="color:var(--dv-muted);font-size:.75rem;">{{ $p['description'] ?? 'Record ID' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($queryParams))
        <span class="sec-label">Query Parameters</span>
        <table class="ptbl">
            <thead><tr><th>Name</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
            <tbody>
                @foreach($queryParams as $p)
                    <tr>
                        <td><span class="pname">{{ $p['name'] }}</span>@if($p['required'] ?? false)<span class="req-tag">required</span>@endif</td>
                        <td><span class="ptype">{{ $p['schema']['type'] ?? 'string' }}</span></td>
                        <td><span class="ptype">{{ $p['schema']['default'] ?? '—' }}</span></td>
                        <td style="color:var(--dv-muted);font-size:.75rem;">{{ $p['description'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($selectedEndpoint['operation']['requestBody']))
        @php
            $reqContent = $selectedEndpoint['operation']['requestBody']['content'] ?? [];
            $isMultipart = isset($reqContent['multipart/form-data']);
            $isJson      = isset($reqContent['application/json']);

            if ($isMultipart) {
                $bodySchema = $reqContent['multipart/form-data']['schema'] ?? [];
            } elseif ($isJson) {
                $bodyRef    = $reqContent['application/json']['schema']['$ref'] ?? '';
                $bodyName   = str_replace('#/components/schemas/', '', $bodyRef);
                $bodySchema = $schemas[$bodyName] ?? null;
            }
        @endphp

        @if($isMultipart)
            @php $bodyTypeLabel = 'multipart/form-data'; @endphp
        @elseif($isJson && $bodyName)
            @php $bodyTypeLabel = 'application/json · ' . $bodyName; @endphp
        @else
            @php $bodyTypeLabel = ''; @endphp
        @endif

        <span class="sec-label">Request Body <span style="font-family:monospace;font-size:.7rem;color:var(--dv-accent);text-transform:none;letter-spacing:0;font-weight:400;margin-left:.3rem;">{{ $bodyTypeLabel }}</span></span>

        @if(!empty($bodySchema['properties']))
            <table class="ptbl">
                <thead><tr><th>Field</th><th>Type</th><th>Notes</th></tr></thead>
                <tbody>
                    @foreach($bodySchema['properties'] as $prop => $def)
                        @php $isFile = ($def['format'] ?? '') === 'binary'; @endphp
                        <tr>
                            <td><span class="pname">{{ $prop }}</span>@if($isFile)<span class="req-tag" style="background:var(--dv-accent);">file</span>@endif</td>
                            <td>
                                <span class="ptype">
                                    @if($isFile)
                                        📁 {{ $def['type'] === 'array' ? 'file[]' : 'file' }}
                                    @else
                                        {{ $def['type'] ?? 'string' }}@if(isset($def['format']) && !$isFile) · {{ $def['format'] }}@endif
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if($def['readOnly'] ?? false)<span style="color:#ef4444;font-size:.7rem;">read-only</span>@endif
                                @if(isset($def['description']))<span style="color:var(--dv-muted);font-size:.7rem;display:block;">{{ $def['description'] }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @php
                $exBody = collect($bodySchema['properties'] ?? [])
                    ->filter(fn($d) => !($d['readOnly'] ?? false))
                    ->mapWithKeys(function($d, $k) {
                        $isFile = ($d['format'] ?? '') === 'binary';
                        return [$k => $isFile ? ($d['type'] === 'array' ? ['<file1>','<file2>'] : '<file>') : exVal($d)];
                    })
                    ->toArray();
            @endphp
            <div style="margin-top:.75rem;">
                <span style="font-size:.6rem;color:var(--dv-faint);font-weight:700;text-transform:uppercase;letter-spacing:.1em;">
                    {{ $isMultipart ? 'Form Fields Example' : 'Example JSON' }}
                </span>
                <pre class="codeblock" style="margin-top:.35rem;">{!! jt($exBody) !!}</pre>
            </div>
        @endif
    @endif

    @php $firstCode = (string) array_key_first($selectedEndpoint['operation']['responses'] ?? []); @endphp
    @if(!empty($selectedEndpoint['operation']['responses']))
        <span class="sec-label">Responses</span>
        <div x-data="{ tab: '{{ $firstCode }}' }">
            <div class="tabs-row">
                @foreach($selectedEndpoint['operation']['responses'] as $code => $resp)
                    @php $cs = (string)$code; $ac = str_starts_with($cs,'2') ? 'resp-tab-on-2xx' : (str_starts_with($cs,'4') ? 'resp-tab-on-4xx' : 'resp-tab-on-5xx'); @endphp
                    <button class="tab-btn" :class="tab==='{{ $cs }}' ? '{{ $ac }}' : ''" x-on:click="tab='{{ $cs }}'">{{ $cs }}</button>
                @endforeach
            </div>
            @foreach($selectedEndpoint['operation']['responses'] as $code => $resp)
                @php $cs = (string)$code; $ex = $resp['_example'] ?? null; @endphp
                <div x-show="tab==='{{ $cs }}'" x-cloak>
                    <p style="font-size:.8rem;color:var(--dv-muted);margin:0 0 .5rem;">{{ $resp['description'] ?? '' }}</p>
                    @if($ex !== null)<pre class="codeblock">{!! jt($ex) !!}</pre>
                    @else<pre class="codeblock"><span class="jnl">// No schema defined for {{ $cs }}</span></pre>@endif
                </div>
            @endforeach
        </div>
    @endif

    <span class="sec-label" style="margin-top:1.75rem;">Code Examples</span>
    <div x-data="{ lang: 'curl' }">
        <div class="tabs-row">
            @foreach(['curl'=>'cURL','js'=>'JavaScript','php'=>'PHP','python'=>'Python'] as $l => $lbl)
                <button class="tab-btn" :class="lang==='{{ $l }}' ? 'tab-on' : ''" x-on:click="lang='{{ $l }}'">{{ $lbl }}</button>
            @endforeach
        </div>
        @foreach(['curl','js','php','python'] as $l)
            <div x-show="lang==='{{ $l }}'" x-cloak>
                <pre class="codeblock" x-text="buildSnippet('{{ $l }}', $wire.selectedEndpoint?.method, $wire.tryUrl, $wire.tryToken, $wire.tryBody)" style="white-space:pre-wrap;word-break:break-all;"></pre>
            </div>
        @endforeach
    </div>

@elseif($selectedGuideKey && isset($guides[$selectedGuideKey]))

    @php $guide = $guides[$selectedGuideKey]; @endphp

    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
        <span class="ep-title">{{ $guide['title'] }}</span>
        <span style="font-size:.6rem;font-weight:700;font-family:ui-monospace,monospace;text-transform:uppercase;letter-spacing:.06em;padding:.2rem .5rem;border-radius:.25rem;background:{{ $guide['badge'] === 'disabled' ? 'rgba(148,163,184,.18)' : 'rgba(99,102,241,.12)' }};color:{{ $guide['badge'] === 'disabled' ? '#64748b' : 'var(--dv-accent)' }};">
            {{ $guide['badge'] }}
        </span>
    </div>
    <p class="ep-summary">{{ $guide['desc'] }}</p>

    <hr class="divider">

    @foreach($guide['blocks'] as $block)
        <span class="sec-label">{{ $block['label'] }}</span>
        <pre class="codeblock" style="white-space:pre-wrap;word-break:break-word;">{{ $block['code'] }}</pre>
    @endforeach

@elseif($selectedSchemaName && isset($schemas[$selectedSchemaName]))

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:1.25rem;">
        <span style="font-size:.75rem;color:var(--dv-faint);font-weight:500;">Schema</span>
        <span style="font-family:ui-monospace,monospace;font-size:1rem;font-weight:700;color:var(--dv-text);">{{ $selectedSchemaName }}</span>
    </div>
    <table class="ptbl">
        <thead><tr><th>Property</th><th>Type</th><th>Format</th><th>Notes</th></tr></thead>
        <tbody>
            @foreach($schemas[$selectedSchemaName]['properties'] ?? [] as $prop => $def)
                <tr>
                    <td><span class="pname">{{ $prop }}</span></td>
                    <td><span class="ptype">{{ $def['type'] ?? 'mixed' }}</span></td>
                    <td><span class="ptype">{{ $def['format'] ?? '—' }}</span></td>
                    <td>@if($def['readOnly'] ?? false)<span style="color:#ef4444;font-size:.7rem;">read-only</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @php $exObj = collect($schemas[$selectedSchemaName]['properties'] ?? [])->mapWithKeys(fn($d, $k) => [$k => exVal($d)])->toArray(); @endphp
    <div style="margin-top:1.125rem;">
        <span style="font-size:.6rem;color:var(--dv-faint);font-weight:700;text-transform:uppercase;letter-spacing:.1em;">Example Object</span>
        <pre class="codeblock" style="margin-top:.35rem;">{!! jt($exObj) !!}</pre>
    </div>

@else
    <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--dv-faint);font-size:.875rem;">
        Select an endpoint or schema from the sidebar
    </div>
@endif
</div>
