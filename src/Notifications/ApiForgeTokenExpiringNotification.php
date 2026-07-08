<?php

namespace YusufGenc34\FilamentApiForge\Notifications;

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiForgeTokenExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ApiForgeToken $token,
    ) {}

    public function via(object $notifiable): array
    {
        return config('filament-api-forge.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = $this->token->expires_at;

        return (new MailMessage())
            ->subject("API token '{$this->token->name}' is expiring soon")
            ->line("Your API token '{$this->token->name}' ({$this->token->token_prefix}…) expires on {$expiresAt->toDayDateTimeString()}.")
            ->line('Rotate or renew it before then to avoid interrupted API access.')
            ->line('You can manage your tokens from the Developer Center → API Keys page.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'token_id'     => $this->token->id,
            'token_name'   => $this->token->name,
            'token_prefix' => $this->token->token_prefix,
            'expires_at'   => $this->token->expires_at?->toIso8601String(),
            'message'      => "API token '{$this->token->name}' expires on {$this->token->expires_at?->toDayDateTimeString()}.",
        ];
    }
}
