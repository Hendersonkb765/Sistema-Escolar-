@props([
    'titulo' => 'Painel',
    'subtitulo' => null,
])

@php
    $usuario = auth()->user();
    $navegacao = \App\Support\Navegacao::paraUsuario($usuario);
@endphp
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
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans antialiased dark:bg-slate-950">
<div x-data="{ menuAberto: false }" class="min-h-full">

    {{-- Sidebar (desktop) --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex dark:border-slate-800 dark:bg-slate-900">
        @include('layouts.partials.sidebar', ['navegacao' => $navegacao, 'usuario' => $usuario])
    </aside>

    {{-- Sidebar (mobile) --}}
    <div x-show="menuAberto" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="menuAberto" x-transition.opacity class="fixed inset-0 bg-slate-900/60" @click="menuAberto = false"></div>
        <div x-show="menuAberto"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 flex w-72 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
             @keydown.escape.window="menuAberto = false">
            @include('layouts.partials.sidebar', ['navegacao' => $navegacao, 'usuario' => $usuario])
        </div>
    </div>

    <div class="lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90">
            <button type="button" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800"
                    @click="menuAberto = true" aria-label="Abrir menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold text-slate-900 dark:text-slate-100">{{ $titulo }}</h1>
                @if ($subtitulo)
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $subtitulo }}</p>
                @endif
            </div>

            <div x-data="{ aberto: false }" class="relative">
                <button type="button" @click="aberto = ! aberto" @click.outside="aberto = false"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-marca-600 text-xs font-semibold text-white">
                        {{ Str::upper(Str::substr($usuario->nome, 0, 2)) }}
                    </span>
                    <span class="hidden text-left sm:block">
                        <span class="block max-w-[10rem] truncate font-medium text-slate-800 dark:text-slate-100">{{ $usuario->nome }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $usuario->perfil->rotulo() }}</span>
                    </span>
                </button>

                <div x-show="aberto" x-cloak x-transition
                     class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $usuario->nome }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $usuario->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if (session('sucesso'))
                <x-alerta tipo="sucesso" class="mb-4">{{ session('sucesso') }}</x-alerta>
            @endif
            @if (session('erro'))
                <x-alerta tipo="erro" class="mb-4">{{ session('erro') }}</x-alerta>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
