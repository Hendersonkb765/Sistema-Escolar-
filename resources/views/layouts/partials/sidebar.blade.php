<div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-4 dark:border-slate-800">
    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-marca-600 text-sm font-bold text-white">
        {{ Str::substr(config('app.name'), 0, 2) }}
    </span>
    <span class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ config('app.name') }}</span>
</div>

<nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
    @foreach ($navegacao as $grupo)
        <div>
            @if ($grupo['titulo'])
                <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    {{ $grupo['titulo'] }}
                </p>
            @endif
            <ul class="space-y-0.5">
                @foreach ($grupo['itens'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                           @class([
                               'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition',
                               'bg-marca-50 text-marca-700 dark:bg-marca-500/10 dark:text-marca-300' => $item['ativo'],
                               'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $item['ativo'],
                           ])
                           @if ($item['ativo']) aria-current="page" @endif>
                            {!! $item['icone'] !!}
                            <span class="truncate">{{ $item['rotulo'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>

<div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $usuario->perfil->rotulo() }}</p>
    <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $usuario->nome }}</p>
</div>
