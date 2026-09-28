@props([
    'titulo' => 'Acesso',
    'subtitulo' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- A tela de primeira senha é Livewire; as do Fortify ignoram. --}}
    @livewireStyles

    @include('layouts.partials.tema')
</head>
<body class="relative h-full bg-slate-100 font-sans antialiased dark:bg-slate-950">
    <div class="absolute right-4 top-4">
        @include('layouts.partials.seletor-de-tema')
    </div>

    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 text-center">
                <x-emblema class="mx-auto h-12 w-12 rounded-xl text-lg"/>
                <h1 class="mt-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ config('app.name') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $subtitulo ?? 'Gestão acadêmica e avaliações' }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600">
                O acesso é concedido pela coordenação PAEET. Não há autocadastro.
            </p>
        </div>
    </div>
@livewireScripts
</body>
</html>
