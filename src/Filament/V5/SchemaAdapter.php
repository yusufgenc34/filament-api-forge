<?php

namespace YusufGenc34\FilamentApiForge\Filament\V5;

use YusufGenc34\FilamentApiForge\Contracts\FilamentSchemaAdapter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SchemaAdapter implements FilamentSchemaAdapter
{
    public function buildApiKeyForm(mixed $schema): mixed
    {
        /** @var Schema $schema */
        return $schema->components([

            TextInput::make('name')
                ->label('Token Name')
                ->placeholder('e.g. Mobile App, IoT Sensor #1, CI/CD Pipeline')
                ->prefixIcon('heroicon-o-key')
                ->required()
                ->maxLength(255),

            Toggle::make('full_access')
                ->label('Full Access')
                ->helperText('Grants all permissions — Read, Write and Delete.')
                ->onColor('danger')
                ->onIcon('heroicon-m-lock-open')
                ->offIcon('heroicon-m-lock-closed')
                ->live()
                ->afterStateUpdated(function (bool $state, Set $set) {
                    $set('scopes', $state ? ['read', 'write', 'delete'] : ['read']);
                }),

            ToggleButtons::make('scopes')
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

            DateTimePicker::make('expires_at')
                ->label('Expires At')
                ->placeholder('Never')
                ->prefixIcon('heroicon-o-calendar-days')
                ->nullable()
                ->native(false)
                ->minDate(now()->addDay())
                ->helperText('Leave empty for a non-expiring token.'),

        ]);
    }
}
