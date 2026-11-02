<div class="mx-auto max-w-3xl space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <x-campo rotulo="Ano letivo" para="ano" class="sm:w-48">
                <x-select id="ano" wire:model.live="ano">
                    @foreach ($anos as $opcao)
                        <option value="{{ $opcao }}" @selected($ano === $opcao)>{{ $opcao }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <p class="text-xs text-slate-500 sm:max-w-sm dark:text-slate-400">
                A semana 1 de cada bimestre é a semana, de segunda a domingo, em que ele começa.
                É por ela que a data de uma aula encontra a semana do escopo-sequência.
            </p>
        </div>
    </x-cartao>

    @if ($podeEditar)
        <form wire:submit="salvar" class="space-y-4">
            <x-cartao titulo="Bimestres de {{ $ano }}"
                      descricao="Deixe um bimestre em branco se as datas ainda não estão definidas.">
                <div class="space-y-4">
                    @foreach ($bimestres as $bimestre)
                        <div wire:key="bimestre-{{ $bimestre->value }}" class="grid grid-cols-1 gap-3 sm:grid-cols-[10rem_1fr_1fr] sm:items-start">
                            <p class="pt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $bimestre->rotulo() }}</p>

                            <x-campo rotulo="Início" para="inicio-{{ $bimestre->value }}"
                                     :erro="$errors->first('datas.'.$bimestre->value.'.inicio')">
                                <x-input id="inicio-{{ $bimestre->value }}" tipo="date"
                                         wire:model="datas.{{ $bimestre->value }}.inicio"
                                         value="{{ $datas[$bimestre->value]['inicio'] }}"/>
                            </x-campo>

                            <x-campo rotulo="Fim" para="fim-{{ $bimestre->value }}"
                                     :erro="$errors->first('datas.'.$bimestre->value.'.fim')">
                                <x-input id="fim-{{ $bimestre->value }}" tipo="date"
                                         wire:model="datas.{{ $bimestre->value }}.fim"
                                         value="{{ $datas[$bimestre->value]['fim'] }}"/>
                            </x-campo>
                        </div>
                    @endforeach
                </div>
            </x-cartao>

            <div class="flex justify-end">
                <x-botao tipo="submit" wire:loading.attr="disabled">Salvar calendário</x-botao>
            </div>
        </form>
    @else
        <x-cartao titulo="Bimestres de {{ $ano }}"
                  descricao="Quem altera o calendário é o PAEET Admin.">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="py-2 pr-4">Bimestre</th>
                        <th class="py-2 pr-4">Início</th>
                        <th class="py-2">Fim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($bimestres as $bimestre)
                        @php($par = $datas[$bimestre->value])
                        <tr wire:key="bimestre-{{ $bimestre->value }}">
                            <td class="py-2 pr-4 font-medium text-slate-700 dark:text-slate-200">{{ $bimestre->rotulo() }}</td>
                            @if ($par['inicio'] === '')
                                <td colspan="2" class="py-2 text-slate-400 dark:text-slate-500">Ainda não cadastrado</td>
                            @else
                                <td class="py-2 pr-4 tabular-nums">{{ \Illuminate\Support\Carbon::parse($par['inicio'])->format('d/m/Y') }}</td>
                                <td class="py-2 tabular-nums">{{ \Illuminate\Support\Carbon::parse($par['fim'])->format('d/m/Y') }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-cartao>
    @endif

    {{-- Com a chave no ano: trocar de ano monta a lista de novo. --}}
    <livewire:calendario.eventos-da-escola :ano="$ano" :key="'eventos-'.$ano"/>

    @if ($comCalendarioProprio->isNotEmpty())
        <x-cartao titulo="Turmas com calendário próprio"
                  descricao="Estas turmas não seguem as datas acima. A exceção é feita em Calendário e intervalos, na tela de cada turma.">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($comCalendarioProprio as $turma)
                    <li class="flex justify-between py-2 text-sm" wire:key="propria-{{ $turma->id }}">
                        @can('update', $turma)
                            <a href="{{ route('turmas.calendario', $turma) }}" wire:navigate
                               class="font-medium text-marca-600 hover:underline dark:text-marca-400">{{ $turma->nome }}</a>
                        @else
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $turma->nome }}</span>
                        @endcan
                        <span class="text-slate-500 dark:text-slate-400">{{ $turma->curso->nome }}</span>
                    </li>
                @endforeach
            </ul>
        </x-cartao>
    @endif
</div>
