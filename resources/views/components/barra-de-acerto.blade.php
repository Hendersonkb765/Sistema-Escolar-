@props([
    'percentual' => 0,
    'atencao' => false,
])

{{-- O número vem junto: uma barra sozinha não diz quanto é. --}}
<div class="flex items-center gap-2">
    <div class="h-2 w-24 shrink-0 rounded-full bg-slate-200 dark:bg-slate-800">
        <div @class([
                'h-2 rounded-full',
                'bg-rose-500' => $atencao,
                'bg-emerald-500' => ! $atencao,
            ])
            style="width: {{ max(2, min(100, $percentual)) }}%"></div>
    </div>
    <span @class([
        'text-sm font-medium tabular-nums',
        'text-rose-700 dark:text-rose-400' => $atencao,
        'text-emerald-700 dark:text-emerald-400' => ! $atencao,
    ])>{{ number_format($percentual, 1, ',', '.') }}%</span>
</div>
