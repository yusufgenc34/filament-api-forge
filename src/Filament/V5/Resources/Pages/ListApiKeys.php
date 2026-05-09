<?php

namespace YusufGenc34\FilamentApiForge\Filament\V5\Resources\Pages;

use YusufGenc34\FilamentApiForge\Filament\V5\Resources\ApiKeyResource;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        $resourceOptions = app(ResourceDiscoveryService::class)
            ->discover()
            ->pluck('plural_label', 'slug')
            ->toArray();

        return [
            Actions\CreateAction::make()
                ->label('New API Key')
                ->icon('heroicon-o-plus')
                ->model(ApiForgeToken::class)
                ->modalWidth('4xl')
                ->modalHeading('Create API Key')
                ->modalDescription('Generate a token to authenticate API requests.')
                ->createAnother(false)
                ->form(fn (Schema $schema) => $schema->components([

                    Forms\Components\TextInput::make('name')
                        ->label('Token Name')
                        ->placeholder('e.g. Mobile App, IoT Sensor #1, CI/CD Pipeline')
                        ->prefixIcon('heroicon-o-key')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Toggle::make('full_access')
                        ->label('Full Access')
                        ->helperText('Grants all permissions — Read, Write and Delete.')
                        ->onColor('danger')
                        ->onIcon('heroicon-m-lock-open')
                        ->offIcon('heroicon-m-lock-closed')
                        ->live()
                        ->afterStateUpdated(function (bool $state, Set $set) {
                            $set('scopes', $state ? ['read', 'write', 'delete'] : ['read']);
                        }),

                    Forms\Components\ToggleButtons::make('scopes')
                        ->label('Permissions')
                        ->options([
                            'read'   => 'Read',
                            'write'  => 'Write',
                            'delete' => 'Delete',
                        ])
                        ->icons([
                            'read'   => 'heroicon-o-eye',
                            'write'  => 'heroicon-o-pencil-square',
                            'delete' => 'heroicon-o-trash',
                        ])
                        ->colors([
                            'read'   => 'info',
                            'write'  => 'warning',
                            'delete' => 'danger',
                        ])
                        ->multiple()
                        ->grouped()
                        ->default(['read'])
                        ->required()
                        ->disabled(fn (Get $get) => (bool) $get('full_access'))
                        ->live(),

                    Forms\Components\CheckboxList::make('allowed_resources')
                        ->label('Resource Access')
                        ->options($resourceOptions)
                        ->columns(3)
                        ->helperText('Leave empty to allow access to all resources.')
                        ->visible(fn () => ! empty($resourceOptions)),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->placeholder('Never')
                        ->prefixIcon('heroicon-o-calendar-days')
                        ->nullable()
                        ->native(false)
                        ->minDate(now()->addDay())
                        ->helperText('Leave empty for a non-expiring token.'),

                ]))
                ->using(function (array $data, Actions\CreateAction $action): ApiForgeToken {

                    $scopes = ($data['full_access'] ?? false)
                        ? ['*']
                        : ($data['scopes'] ?? ['read']);

                    $result = app(ApiForgeTokenService::class)->create(Auth::user(), [
                        'name'              => $data['name'],
                        'scopes'            => $scopes,
                        'allowed_resources' => ! empty($data['allowed_resources']) ? $data['allowed_resources'] : null,
                        'expires_at'        => $data['expires_at'] ?? null,
                    ]);

                    $action->modalHeading('Token Generated');
                    $action->modalDescription(null);
                    $action->modalSubmitAction(false);
                    $action->modalCancelActionLabel('Close');
                    $action->modalFooterActions([]);
                    $action->modalContent(view('filament-api-forge::partials.token-display', [
                        'token' => $result['plain_text_token'],
                    ]));
                    $action->halt();

                    return $result['record'];
                }),
        ];
    }
}
