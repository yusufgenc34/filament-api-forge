@use('Illuminate\Support\Str')
<x-filament-panels::page>

@include('filament-api-forge::partials.docs-styles')
@include('filament-api-forge::partials.docs-scripts')

<div x-data="{
        activeTab: localStorage.getItem('api_forge_tab') || 'docs',
        setTab(tab) { this.activeTab = tab; localStorage.setItem('api_forge_tab', tab); }
    }">

    {{-- Tab Navigation --}}
    <div class="af-tab-nav">
        <button type="button"
            x-on:click="setTab('docs')"
            :class="activeTab === 'docs' ? 'af-tab af-tab-active' : 'af-tab'">
            API Docs
        </button>
        <button type="button"
            x-on:click="setTab('settings')"
            :class="activeTab === 'settings' ? 'af-tab af-tab-active' : 'af-tab'">
            Access Control
        </button>
    </div>

    {{-- API Docs Tab --}}
    <div x-show="activeTab === 'docs'" x-cloak>
        <div class="docs-wrap">
            @include('filament-api-forge::partials.docs-sidebar')
            @include('filament-api-forge::partials.docs-content')
            @include('filament-api-forge::partials.docs-try-it')
        </div>
    </div>

    {{-- Access Control Tab --}}
    <div x-show="activeTab === 'settings'" x-cloak>
        @include('filament-api-forge::partials.docs-settings')
    </div>

</div>

</x-filament-panels::page>
