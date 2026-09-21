<x-layouts.app titulo="Auditoria" subtitulo="Registro de alterações do sistema">
    <x-cartao descricao="Filtros por entidade, autor e período chegam no milestone 8.">
        @if ($registros->isEmpty())
            <x-vazio titulo="Nenhum registro ainda"
                     descricao="As ações realizadas no sistema aparecem aqui."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Quando</th>
                            <th class="px-4 py-2">Autor</th>
                            <th class="px-4 py-2">Registro</th>
                            <th class="px-4 py-2">Descrição</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($registros as $registro)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500 sm:px-6 dark:text-slate-400">
                                    {{ $registro->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                                    {{ $registro->causer?->nome ?? 'Sistema' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge cor="cinza">{{ $registro->log_name ?? 'geral' }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $registro->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $registros->links() }}</div>
        @endif
    </x-cartao>
</x-layouts.app>
