@use('Illuminate\Support\Str')
<x-filament-panels::page>

@include('filament-api-forge::partials.docs-styles')
@include('filament-api-forge::partials.docs-scripts')

<div class="docs-wrap">
    @include('filament-api-forge::partials.docs-sidebar')
    @include('filament-api-forge::partials.docs-content')
    @include('filament-api-forge::partials.docs-try-it')
</div>

</x-filament-panels::page>
