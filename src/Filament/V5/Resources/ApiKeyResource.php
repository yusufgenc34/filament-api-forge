<?php

namespace YusufGenc34\FilamentApiForge\Filament\V5\Resources;

use YusufGenc34\FilamentApiForge\Contracts\FilamentSchemaAdapter;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiForgeToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Developer Center';

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static ?string $pluralModelLabel = 'API Keys';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'developer/api-keys';

    public static function form(Schema $schema): Schema
    {
        return app(FilamentSchemaAdapter::class)->buildApiKeyForm($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Token Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-key'),

                Tables\Columns\TextColumn::make('token_prefix')
                    ->label('Token')
                    ->formatStateUsing(fn (?string $state) => $state ? "{$state}••••••••••••••••••••••••••••••" : '—')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        '*'      => 'Full Access',
                        'read'   => 'Read',
                        'write'  => 'Write',
                        'delete' => 'Delete',
                        default  => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        '*'      => 'danger',
                        'read'   => 'info',
                        'write'  => 'warning',
                        'delete' => 'danger',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('request_count')
                    ->label('Requests')
                    ->numeric()
                    ->sortable()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->since(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->color(fn (?string $state) => $state && now()->isAfter($state) ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn ($query) => $query->where('expires_at', '<', now())),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this token?')
                    ->modalDescription('This will immediately deactivate the token. Any application using it will lose access.')
                    ->modalSubmitActionLabel('Yes, revoke it')
                    ->action(fn (ApiForgeToken $record) => app(ApiForgeTokenService::class)->revoke($record)),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No API Keys Yet')
            ->emptyStateDescription('Create your first API key to start accessing the API.')
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \YusufGenc34\FilamentApiForge\Filament\V5\Resources\Pages\ListApiKeys::route('/'),
            // no edit page → EditAction automatically opens a modal
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }
}
