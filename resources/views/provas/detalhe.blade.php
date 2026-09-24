<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeVerOGabarito)
                <x-botao variante="secundario" href="{{ route('provas.gabarito', $prova) }}"
                         title="Baixa o CSV de chaves de respostas para importar no leitor de folhas.">
                    Gerar gabarito
                </x-botao>
            @endif

            <x-botao variante="secundario" href="{{ route('provas.pdf', $prova) }}">
                Baixar PDF
            </x-botao>

            <x-botao variante="secundario" href="{{ route('provas.docx', $prova) }}">
                Baixar Word
            </x-botao>

            @can('aplicar', $prova)
                @if ($prova->podeSerAplicada())
                    <x-botao wire:click="marcarComoAplicada"
                             wire:confirm="Marcar a prova como aplicada? Ela passa a aceitar a importação de resultados.">
                        Marcar como aplicada
                    </x-botao>
                @elseif ($motivo = $prova->motivoParaNaoAplicar())
                    <span class="text-xs text-slate-500 dark:text-slate-400" title="{{ $motivo }}">{{ $motivo }}</span>
                @endif
            @endcan
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Turma</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $prova->turma->nome }}
                    <span class="block text-xs font-normal text-slate-400">{{ $prova->turma->curso->nome }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Questões</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $prova->totalDeQuestoes() }}
                    <span class="block text-xs font-normal text-slate-400">
                        soma dos pesos {{ number_format($prova->somaDosPesos(), 2, ',', '.') }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Aplicação</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $prova->data_aplicacao?->format('d/m/Y') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Layout</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $prova->colunas() === 2 ? 'Duas colunas' : 'Coluna única' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Situação</dt>
                <dd class="mt-0.5">
                    <x-badge :cor="$prova->status->cor()" :rotulo="$prova->status->rotulo()"/>
                </dd>
            </div>
        </dl>

        @if ($avisosDoGabarito !== [])
            <x-alerta tipo="atencao" class="mt-4" titulo="Confira antes de importar o gabarito">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($avisosDoGabarito as $aviso)
                        <li>{{ $aviso }}</li>
                    @endforeach
                </ul>
            </x-alerta>
        @endif

        <x-alerta tipo="info" class="mt-4" titulo="Esta prova é um registro congelado">
            Enunciados, alternativas e pesos foram copiados na montagem
            ({{ $prova->gerada_em?->format('d/m/Y H:i') }}, por {{ $prova->geradaPor?->nome }}).
            Alterar as questões originais depois disso não muda o que está aqui.
        </x-alerta>
    </x-cartao>

    <x-cartao titulo="Composição por disciplina"
              descricao="O aluno vê apenas o número; o sistema guarda a origem de cada questão.">
        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-2 sm:px-6">Disciplina</th>
                        <th class="px-4 py-2">Questões</th>
                        <th class="hidden px-4 py-2 sm:table-cell">Professor</th>
                        <th class="px-4 py-2">Soma dos pesos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($porDisciplina as $disciplina => $questoes)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $disciplina }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $questoes->min('numero') }} a {{ $questoes->max('numero') }}
                                <span class="text-xs text-slate-400">({{ $questoes->count() }})</span>
                            </td>
                            <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                {{ $questoes->first()->professor->nome ?? '—' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                {{ number_format((float) $questoes->sum('peso'), 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-cartao>

    <x-cartao titulo="Pré-visualização"
              descricao="É exatamente o que sai no PDF e no Word.">
        {{-- A folha vai num iframe: o CSS dela é de impressão e não pode
             vazar para o resto do sistema. --}}
        <iframe srcdoc="{{ $folha }}"
                title="Pré-visualização da prova"
                class="h-[80vh] w-full rounded-lg border border-slate-300 bg-white dark:border-slate-700"></iframe>
    </x-cartao>
</div>
