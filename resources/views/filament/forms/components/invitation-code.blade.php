@php
    $code = $getRecord()?->code ?? '—';
    $url = $code !== '—' ? config('app.url')."/{$code}" : null;
@endphp

@if ($code === '—')
    <span>{{ $code }}</span>
@else
    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="data.first_level_guests_count">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
            {{ __('Invitation ID') }}
        </span>
    </label>
    <div x-data="{ copied: false }" class="flex items-center space-x-2">
        <span>{{ $code }}</span>
        <button
            type="button"
            class="text-gray-500 hover:text-primary-600 transition"
            x-on:click="navigator.clipboard.writeText('{{ $url }}'); copied = true; setTimeout(() => copied = false, 2000)"
            title="Copiar link"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
        </button>
        <span x-show="copied" x-transition class="text-sm text-green-600">Copiado!</span>
    </div>
@endif
