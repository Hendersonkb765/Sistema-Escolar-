<div class="space-y-4">
    <x-cartao titulo="Recorte">
        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 sm:p-6">
            <x-campo rotulo="Turma" para="filtro-turma">
                <x-select id="filtro-turma" wire:model.live="filtroTurma">
                    <option value="">Todas</option>
                    @foreach ($turmas as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->nome }} — {{ $opcao->curso->nome }} ({{ $opcao->periodo_letivo }})
                        </option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Bimestre" para="filtro-bimestre">
                <x-select id="filtro-bimestre" wire:model.live="filtroBimestre">
                    <option value="">Todos</option>
                    @foreach ($bimestres as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Prova" para="prova">
                <x-select id="prova" wire:model.live="prova_id">
                    <option value="">Todas do recorte</option>
                    @foreach ($provas as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->titulo }} · {{ $opcao->turma->nome }} · {{ $opcao->turma->curso->nome }}
                        </option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>
    </x-cartao>

    {{--
        As disciplinas da lista são as do recorte inteiro: com várias
        provas na tela, uma caixa que some ao rolar a página não é filtro.
    --}}
    @if ($disciplinasDaProva->count() > 1)
        <x-cartao>
            <x-slot:titulo>Disciplinas mostradas</x-slot:titulo>
            <x-slot:descricao>
                {{ $disciplinas->count() }} de {{ $disciplinasDaProva->count() }} marcadas.
                Desmarcar uma só a tira desta tela — a nota continua lá.
            </x-slot:descricao>

            <x-slot:acoes>
                @if ($minhasDisciplinas !== [])
                    <x-botao variante="discreto" wire:click="mostrarSoAsMinhas"
                             title="Deixa marcadas apenas as disciplinas em que você leciona.">
                        Só as minhas
                    </x-botao>
                @endif
                <x-botao variante="discreto" wire:click="mostrarTodasAsDisciplinas">Todas</x-botao>
            </x-slot:acoes>

            <div class="grid gap-2 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                @foreach ($disciplinasDaProva as $id => $nome)
                    <label wire:key="disc-{{ $id }}"
                           class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <input type="checkbox" value="{{ $id }}" wire:model.live="disciplinasEscolhidas"
                               class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                        <span class="min-w-0">
                            <span class="block truncate text-slate-900 dark:text-slate-100">{{ $nome }}</span>
                            @if (in_array((string) $id, $minhasDisciplinas, true))
                                <span class="block text-xs text-marca-600 dark:text-marca-400">você leciona</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </x-cartao>
    @endif

    @if ($secoes === [])
        <x-cartao>
            @if ($disciplinasDaProva->isNotEmpty() && $disciplinas->isEmpty())
                <x-vazio titulo="Nenhuma disciplina marcada"
                         descricao="Marque ao menos uma disciplina acima para ver as notas.">
                    <x-slot:acoes>
                        <x-botao wire:click="mostrarTodasAsDisciplinas">Mostrar todas</x-botao>
                    </x-slot:acoes>
                </x-vazio>
            @else
                <x-vazio titulo="Nenhuma prova com resultado{{ $bimestreEscolhido ? ' no '.$bimestreEscolhido : '' }}"
                         descricao="As notas aparecem depois que a importação dos resultados é confirmada.">
                    <x-slot:acoes>
                        @can('create', App\Models\Importacao::class)
                            <x-botao href="{{ route('importacoes.criar') }}" wire:navigate>Importar resultados</x-botao>
                        @endcan
                    </x-slot:acoes>
                </x-vazio>
            @endif
        </x-cartao>
    @else
        @foreach ($secoes as $secao)
            @php $prova = $secao['prova']; @endphp

            <div wire:key="secao-{{ $prova->id }}" class="space-y-4">
                {{--
                    Cada seção diz de que prova ela é. Com várias na
                    página, uma tabela sem cabeçalho próprio é um bloco de
                    números sem dono — e o bimestre vem da coluna da
                    prova, não do título, que é escrito à mão na montagem.
                --}}
                <section data-identificacao
                         class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                {{ $prova->titulo }}
                            </h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                Turma {{ $prova->turma->nome }} · {{ $prova->turma->curso->nome }}
                                @if ($prova->data_aplicacao)
                                    · Aplicada em {{ $prova->data_aplicacao->format('d/m/Y') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <x-badge cor="azul" :rotulo="$prova->bimestre->rotulo()"/>
                            <x-badge :cor="$prova->status->cor()" :rotulo="$prova->status->rotulo()"/>
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                {{ $secao['resultados']->count() }}
                                {{ $secao['resultados']->count() === 1 ? 'aluno' : 'alunos' }}
                            </span>
                        </div>
                    </div>
                </section>

                <x-cartao>
                    @if ($secao['resultados']->isEmpty() || $secao['disciplinas']->isEmpty())
                        <x-vazio titulo="Sem notas nesta prova"
                                 :descricao="$secao['disciplinas']->isEmpty()
                                    ? 'Nenhuma das disciplinas marcadas foi avaliada nesta prova.'
                                    : 'Importe a planilha do leitor de folhas para trazer os acertos.'"/>
                    @else
                        <div class="-mx-4 overflow-x-auto sm:-mx-6">
                            <table data-notas class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                        <th class="px-4 py-2 sm:px-6">Aluno</th>
                                        @foreach ($secao['disciplinas'] as $nome)
                                            <th class="px-4 py-2 text-center">{{ $nome }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($secao['resultados'] as $resultado)
                                        <tr wire:key="nota-{{ $resultado->id }}">
                                            <td class="px-4 py-3 sm:px-6">
                                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $resultado->aluno->nome }}</span>
                                                <span class="block text-xs text-slate-400">RA {{ $resultado->aluno->ra }}</span>
                                            </td>
                                            @foreach ($secao['disciplinas'] as $disciplinaId => $nome)
                                                @php $nota = $resultado->notas->firstWhere('disciplina_id', $disciplinaId); @endphp
                                                <td class="px-4 py-3 text-center">
                                                    @if ($nota)
                                                        <span @class([
                                                            'text-base font-semibold tabular-nums',
                                                            'text-emerald-700 dark:text-emerald-400' => (float) $nota->nota >= 6,
                                                            'text-rose-700 dark:text-rose-400' => (float) $nota->nota < 6,
                                                        ])>
                                                            {{ number_format((float) $nota->nota, 2, ',', '.') }}
                                                        </span>
                                                        <span class="block text-xs text-slate-400 tabular-nums">
                                                            {{ number_format((float) $nota->soma_pesos_acertos, 2, ',', '.') }}
                                                            de {{ number_format((float) $nota->soma_pesos_total, 2, ',', '.') }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-400">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-cartao>
            </div>
        @endforeach
    @endif
</div>
