<div class="mx-auto max-w-3xl space-y-4">
    {{-- Calendário ------------------------------------------------------------ --}}
    <x-cartao titulo="Calendário dos bimestres" :icone="App\Support\Navegacao::icone('calendario')"
              :descricao="$bimestresProprios->isEmpty()
                  ? 'Segue o calendário letivo da escola de '.$turma->periodo_letivo.'.'
                  : 'Calendário próprio: esta turma não segue o da escola.'">
        <x-slot:acoes>
            @if ($bimestresProprios->isEmpty())
                <x-badge cor="cinza">Padrão da escola</x-badge>
            @else
                <x-badge cor="roxo">Próprio da turma</x-badge>
            @endif
        </x-slot:acoes>

        @if ($editandoCalendario)
            <form wire:submit="salvarCalendario" class="space-y-4">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Ajuste só o que for diferente. Bimestre em branco fica sem data para esta turma.
                </p>

                @foreach (App\Enums\Bimestre::cases() as $bimestre)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-start" wire:key="bimestre-{{ $bimestre->value }}">
                        <p class="pt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $bimestre->rotulo() }}</p>
                        <x-campo rotulo="Início" para="inicio-{{ $bimestre->value }}" :erro="$errors->first('datas.'.$bimestre->value.'.inicio')">
                            <x-input id="inicio-{{ $bimestre->value }}" tipo="date" wire:model="datas.{{ $bimestre->value }}.inicio"
                                     value="{{ $datas[$bimestre->value]['inicio'] ?? '' }}"/>
                        </x-campo>
                        <x-campo rotulo="Fim" para="fim-{{ $bimestre->value }}" :erro="$errors->first('datas.'.$bimestre->value.'.fim')">
                            <x-input id="fim-{{ $bimestre->value }}" tipo="date" wire:model="datas.{{ $bimestre->value }}.fim"
                                     value="{{ $datas[$bimestre->value]['fim'] ?? '' }}"/>
                        </x-campo>
                    </div>
                @endforeach

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="cancelarCalendario">Cancelar</x-botao>
                    <x-botao tipo="submit" wire:loading.attr="disabled">Salvar calendário da turma</x-botao>
                </div>
            </form>
        @else
            @php($emUso = $bimestresProprios->isEmpty() ? $bimestresDaEscola : $bimestresProprios)

            @if ($emUso->isEmpty())
                <x-vazio titulo="O calendário letivo de {{ $turma->periodo_letivo }} ainda não foi cadastrado"
                         descricao="Quem cadastra o calendário da escola é o PAEET Admin, em Calendário letivo."/>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($emUso as $bimestre)
                        <li class="flex justify-between py-2 text-sm" wire:key="em-uso-{{ $bimestre->bimestre->value }}">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $bimestre->bimestre->rotulo() }}</span>
                            <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ $bimestre->inicio->format('d/m/Y') }} a {{ $bimestre->fim->format('d/m/Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                @if ($bimestresProprios->isNotEmpty())
                    <x-botao variante="secundario" wire:click="voltarAoCalendarioDaEscola"
                             wire:confirm="Apagar o calendário próprio e voltar ao da escola?">Voltar ao calendário da escola</x-botao>
                    <x-botao wire:click="definirCalendario">Alterar calendário da turma</x-botao>
                @else
                    <x-botao variante="secundario" wire:click="definirCalendario">Definir calendário próprio</x-botao>
                @endif
            </div>
        @endif
    </x-cartao>

    {{-- Intervalos ------------------------------------------------------------ --}}
    <x-cartao titulo="Intervalos do dia" :icone="App\Support\Navegacao::icone('relogio')"
              descricao="Definidos pelo PAEET Admin em Configuração da escola, onde cada intervalo diz a quais turmas se aplica.">
        @if ($intervalos->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">Nenhum intervalo se aplica a esta turma.</p>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($intervalos as $intervalo)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm" wire:key="intervalo-{{ $loop->index }}">
                        <span class="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
                            {{ $intervalo->nome }}
                            @if ($intervalo->alcance === App\Support\IntervaloDoDia::SOMENTE)
                                <x-badge cor="roxo">Só de turmas escolhidas</x-badge>
                            @else
                                <x-badge cor="cinza">Padrão da escola</x-badge>
                            @endif
                        </span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ $intervalo->intervalo() }} · {{ $intervalo->minutos() }} min</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @can('configurarEscola')
            <div class="mt-4 flex justify-end">
                <x-botao variante="secundario" href="{{ route('configuracao.escola') }}" wire:navigate>Alterar em Configuração da escola</x-botao>
            </div>
        @endcan
    </x-cartao>

    <div class="flex justify-end">
        <x-botao variante="secundario" href="{{ route('turmas.show', $turma) }}" wire:navigate>Voltar à turma</x-botao>
    </div>
</div>
