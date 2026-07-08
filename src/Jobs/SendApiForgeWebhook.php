<?php

namespace YusufGenc34\FilamentApiForge\Jobs;

use YusufGenc34\FilamentApiForge\Models\ApiForgeWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendApiForgeWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $webhookId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = ApiForgeWebhook::find($this->webhookId);

        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        $body = json_encode($this->payload);

        $headers = [
            'Content-Type'      => 'application/json',
            'User-Agent'        => 'FilamentApiForge-Webhook/1.0',
            'X-ApiForge-Event'  => $this->payload['event'] ?? 'unknown',
        ];

        if ($webhook->secret) {
            $headers['X-ApiForge-Signature'] = static::sign($body, $webhook->secret);
        }

        $timeout = (int) config('filament-api-forge.webhooks.timeout', 10);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $webhook->forceFill(['last_triggered_at' => now()]);

            if ($response->failed()) {
                $webhook->increment('failure_count');
                $webhook->save();
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);

                return;
            }

            $webhook->save();
        } catch (\Throwable $e) {
            $webhook->increment('failure_count');
            $webhook->forceFill(['last_triggered_at' => now()])->save();

            throw $e; // let the queue retry with backoff
        }
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }
}
