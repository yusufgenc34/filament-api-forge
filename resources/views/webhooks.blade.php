<x-filament-panels::page>

@include('filament-api-forge::partials.docs-styles')

<style>
.wh-wrap { display: grid; grid-template-columns: 380px 1fr; gap: 24px; padding-top: 8px; }
@media (max-width: 1000px) { .wh-wrap { grid-template-columns: 1fr; } }

.wh-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 24px;
}
.dark .wh-card { background: #1f2937; border-color: #374151; }

.wh-title { font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 4px; }
.dark .wh-title { color: #f9fafb; }
.wh-desc { font-size: 12px; color: #64748b; margin-bottom: 20px; }

.wh-label { display:block; font-size: 12px; font-weight: 500; color: #475569; margin: 12px 0 4px; }
.dark .wh-label { color: #94a3b8; }

.wh-input, .wh-select {
    width: 100%; padding: 8px 12px; border-radius: 8px; font-size: 13px;
    border: 1px solid #e5e7eb; background: transparent; color: inherit;
}
.wh-checks { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 12.5px; }
.wh-checks label { display: flex; align-items: center; gap: 6px; }

.wh-btn {
    margin-top: 18px; width: 100%; padding: 9px 0; border-radius: 8px;
    background: #6366f1; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.wh-btn:hover { background: #4f46e5; }

.wh-table { width: 100%; font-size: 13px; border-collapse: collapse; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    color: #9ca3af; padding: 8px 10px; border-bottom: 1px solid #f3f4f6; }
.dark .wh-table th { border-bottom-color: #374151; }
.wh-table td { padding: 10px; border-bottom: 1px solid #f9fafb; vertical-align: top; }
.dark .wh-table td { border-bottom-color: #374151; }

.wh-pill { display:inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;
    background: rgba(99,102,241,0.12); color: #6366f1; margin: 1px 2px 1px 0; }
.wh-pill--ok   { background: rgba(16,185,129,0.12); color: #10b981; }
.wh-pill--off  { background: rgba(148,163,184,0.18); color: #64748b; }
.wh-pill--err  { background: rgba(244,63,94,0.12); color: #f43f5e; }

.wh-action { font-size: 12px; font-weight: 600; cursor: pointer; margin-right: 10px; background: none; border: none; }
.wh-action--toggle { color: #6366f1; }
.wh-action--delete { color: #f43f5e; }

.wh-url { font-family: ui-monospace, monospace; font-size: 12px; color: #64748b; word-break: break-all; }
.wh-empty { padding: 32px; text-align: center; color: #64748b; font-size: 13px; }
</style>

<div class="wh-wrap">

    {{-- New webhook form --}}
    <div class="wh-card">
        <div class="wh-title">Add Webhook</div>
        <div class="wh-desc">
            Receive signed HTTP callbacks whenever records change through the API.
            Payloads are signed with <code>X-ApiForge-Signature</code> when a secret is set.
        </div>

        <label class="wh-label">Name</label>
        <input class="wh-input" type="text" wire:model="name" placeholder="Production sync" />
        @error('name') <div class="wh-pill wh-pill--err" style="margin-top:4px">{{ $message }}</div> @enderror

        <label class="wh-label">Payload URL</label>
        <input class="wh-input" type="url" wire:model="url" placeholder="https://example.com/webhooks/api-forge" />
        @error('url') <div class="wh-pill wh-pill--err" style="margin-top:4px">{{ $message }}</div> @enderror

        <label class="wh-label">Secret (optional)</label>
        <input class="wh-input" type="text" wire:model="secret" placeholder="Used for HMAC-SHA256 signature" />

        <label class="wh-label">Events</label>
        <div class="wh-checks">
            @foreach (\YusufGenc34\FilamentApiForge\Pages\Webhooks::EVENT_OPTIONS as $value => $label)
                <label>
                    <input type="checkbox" wire:model="events" value="{{ $value }}" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
        @error('events') <div class="wh-pill wh-pill--err" style="margin-top:4px">{{ $message }}</div> @enderror

        <label class="wh-label">Resource (optional — empty = all resources)</label>
        <select class="wh-select" wire:model="resourceClass">
            <option value="">All resources</option>
            @foreach ($resourceOptions as $option)
                <option value="{{ $option }}">{{ class_basename($option) }}</option>
            @endforeach
        </select>

        <button type="button" class="wh-btn" wire:click="addWebhook">Create Webhook</button>
    </div>

    {{-- Webhook list --}}
    <div class="wh-card">
        <div class="wh-title">Registered Webhooks</div>
        <div class="wh-desc">Deliveries are queued with automatic retries (3 attempts with backoff).</div>

        @if (empty($webhooks))
            <div class="wh-empty">No webhooks yet — add one on the left to start receiving events.</div>
        @else
            <table class="wh-table">
                <thead>
                    <tr>
                        <th>Webhook</th>
                        <th>Events</th>
                        <th>Status</th>
                        <th>Last Delivery</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($webhooks as $hook)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $hook['name'] }}</div>
                                <div class="wh-url">{{ $hook['url'] }}</div>
                                @if ($hook['resource_class'])
                                    <span class="wh-pill">{{ class_basename($hook['resource_class']) }}</span>
                                @endif
                                @if ($hook['has_secret'])
                                    <span class="wh-pill wh-pill--ok">signed</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($hook['events'] as $event)
                                    <span class="wh-pill">{{ $event }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($hook['is_active'])
                                    <span class="wh-pill wh-pill--ok">active</span>
                                @else
                                    <span class="wh-pill wh-pill--off">paused</span>
                                @endif
                                @if ($hook['failure_count'] > 0)
                                    <span class="wh-pill wh-pill--err">{{ $hook['failure_count'] }} failed</span>
                                @endif
                            </td>
                            <td style="font-size:12px; color:#64748b">
                                {{ $hook['last_triggered_at'] ?? 'never' }}
                            </td>
                            <td style="white-space:nowrap; text-align:right">
                                <button type="button" class="wh-action wh-action--toggle" wire:click="toggleWebhook({{ $hook['id'] }})">
                                    {{ $hook['is_active'] ? 'Pause' : 'Resume' }}
                                </button>
                                <button type="button" class="wh-action wh-action--delete"
                                        wire:click="deleteWebhook({{ $hook['id'] }})"
                                        wire:confirm="Delete this webhook?">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

</x-filament-panels::page>
