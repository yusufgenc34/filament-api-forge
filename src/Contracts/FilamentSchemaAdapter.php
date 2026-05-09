<?php

namespace YusufGenc34\FilamentApiForge\Contracts;

interface FilamentSchemaAdapter
{
    /**
     * API Key formunu verilen schema/form nesnesine uygular.
     *
     * v3/v4: Filament\Forms\Form $schema
     * v5:    Filament\Schemas\Schema $schema
     */
    public function buildApiKeyForm(mixed $schema): mixed;
}
