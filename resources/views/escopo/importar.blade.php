<div class="space-y-4">
    <x-cartao titulo="O arquivo">
        <x-slot:descricao>
            O escopo-sequência como a rede o publica, em XLSX ou CSV. Conferir não grava nada.
        </x-slot:descricao>

        <div class="space-y-4 p-4 sm:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo rotulo="Curso" para="curso" :erro="$errors->first('curso_id')">
                    <x-select id="curso" wire:model.live="curso_id">
                        <option value="">Escolha o curso</option>
                        @foreach ($cursos as $curso)
                            <option value="{{ $curso->id }}" @selected($curso->id === $curso_id)>{{ $curso->nome }} ({{ $curso->eixo->nome }})</option>
                        @endforeach
                    </x-select>
                </x-campo>

                {{--
                    O período não vem na planilha: ela traz bimestre e
                    semana, e o mesmo bimestre 1 existe em todo período.
                    Quem importa é quem sabe de qual ano é o arquivo.
                --}}
                <x-campo rotulo="Período do curso" para="periodo"
                         ajuda="Não vem na planilha — os bimestres se repetem a cada período."
                         :erro="$errors->first('periodo')">
                    <x-select id="periodo" wire:model.live="periodo" :desabilitado="$periodos === []">
                        <option value="">{{ $cursoEscolhido ? 'Escolha o período' : 'Escolha o curso primeiro' }}</option>
                        @foreach ($periodos as $opcao)
                            <option value="{{ $opcao }}" @selected($opcao === $periodo)>{{ $opcao }}º período</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            <x-campo rotulo="Planilha do escopo" para="planilha" :erro="$errors->first('planilha')"
                     ajuda="XLSX, XLS ou CSV, até 10 MB.">
                <input type="file" id="planilha" wire:model="planilha"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
            </x-campo>

            <div wire:loading wire:target="planilha" class="text-sm text-slate-500">Enviando o arquivo…</div>

            <div class="flex justify-end gap-2">
                @if ($relatorio !== null)
                    <x-botao variante="secundario" wire:click="descartar">Descartar</x-botao>
                @endif
                <x-botao wire:click="conferir" wire:loading.attr="disabled" wire:target="conferir,planilha">
                    Conferir
                </x-botao>
            </div>
        </div>
    </x-cartao>

    @if ($relatorio !== null)
        <x-cartao titulo="O que será gravado">
            <x-slot:descricao>
                Nada foi alterado ainda. Confirmar substitui o escopo deste período.
            </x-slot:descricao>

            <div class="space-y-4 p-4 sm:p-6">
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ([
                        ['Aulas na planilha', $relatorio['aulas']],
                        ['Componentes', count($relatorio['componentes'])],
                        ['Linhas recusadas', count($relatorio['recusadas'])],
                        ['Aulas substituídas', $relatorio['substituira']],
                    ] as [$rotulo, $valor])
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $rotulo }}</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $valor }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($relatorio['substituira'] > 0)
                    <x-alerta tipo="atencao" titulo="Este período já tem escopo">
                        As {{ $relatorio['substituira'] }} aula(s) que estão lá serão apagadas e trocadas
                        pelas desta planilha. O escopo é publicado por ano inteiro: misturar duas versões
                        deixaria no banco um percurso que ninguém saberia desfazer.
                    </x-alerta>
                @endif

                {{--
                    Componente sem disciplina não impede a importação: o
                    escopo chega da rede antes de a escola cadastrar tudo.
                    Mas quem importa precisa saber, porque é isso que tira
                    a aula do filtro "só os meus" do professor.
                --}}
                @if ($relatorio['sem_disciplina'] !== [])
                    <x-alerta tipo="info" titulo="{{ count($relatorio['sem_disciplina']) }} componente(s) sem disciplina correspondente">
                        O código não casou com nenhuma disciplina deste curso. As aulas entram do mesmo
                        jeito e todo mundo as vê — o que elas não fazem é aparecer no filtro
                        “só os componentes que eu leciono” do professor.
                        <span class="mt-1 block text-xs">{{ implode(' · ', $relatorio['sem_disciplina']) }}</span>
                    </x-alerta>
                @endif

                <div class="-mx-4 overflow-x-auto sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <th class="px-4 py-2 sm:px-6">Componente</th>
                                <th class="px-4 py-2">Disciplina no sistema</th>
                                <th class="px-4 py-2 text-right sm:px-6">Aulas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($relatorio['componentes'] as $componente)
                                <tr wire:key="comp-{{ $loop->index }}"
                                    class="border-b border-slate-100 odd:bg-slate-50/60 dark:border-slate-800 dark:odd:bg-slate-950/30">
                                    <td class="px-4 py-2 sm:px-6">
                                        <span class="text-slate-900 dark:text-slate-100">{{ $componente['nome'] }}</span>
                                        @if ($componente['codigo'])
                                            <span class="ml-2 text-xs text-slate-400">{{ $componente['codigo'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($componente['disciplina'])
                                            <x-badge cor="verde" :rotulo="$componente['disciplina']"/>
                                        @else
                                            <span class="text-xs text-slate-400">sem correspondência</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-600 sm:px-6 dark:text-slate-300">
                                        {{ $componente['aulas'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($relatorio['recusadas'] !== [])
                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Linhas que ficam de fora
                        </p>
                        <ul class="space-y-1 text-sm text-slate-600 dark:text-slate-300">
                            @foreach (array_slice($relatorio['recusadas'], 0, 10) as $recusada)
                                <li>
                                    <span class="tabular-nums text-slate-400">Linha {{ $recusada['linha'] }}:</span>
                                    {{ $recusada['erro'] }}
                                </li>
                            @endforeach
                        </ul>
                        @if (count($relatorio['recusadas']) > 10)
                            <p class="mt-1 text-xs text-slate-400">
                                e mais {{ count($relatorio['recusadas']) - 10 }}.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($relatorio['colunas_ignoradas'] !== [])
                    <p class="text-xs text-slate-400">
                        Colunas da planilha que o sistema não usa:
                        {{ implode(', ', $relatorio['colunas_ignoradas']) }}.
                    </p>
                @endif

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <x-botao variante="secundario" wire:click="descartar">Descartar</x-botao>
                    <x-botao wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar">
                        Confirmar e substituir
                    </x-botao>
                </div>
            </div>
        </x-cartao>
    @else
        <x-cartao titulo="As colunas que o sistema lê">
            <x-slot:descricao>
                Procuradas pelo nome, sem depender da ordem, da caixa nem do acento.
                Coluna a mais na planilha não atrapalha.
            </x-slot:descricao>

            <div class="flex flex-wrap gap-2 p-4 sm:p-6">
                @foreach ($colunas as $campo => $nome)
                    <span @class([
                        'rounded-lg border px-2 py-1 text-xs',
                        'border-marca-300 bg-marca-50 text-marca-800 dark:border-marca-500/40 dark:bg-marca-950/40 dark:text-marca-200'
                            => in_array($campo, App\Support\PlanilhaDeEscopo::OBRIGATORIAS, true),
                        'border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300'
                            => ! in_array($campo, App\Support\PlanilhaDeEscopo::OBRIGATORIAS, true),
                    ])>{{ $nome }}</span>
                @endforeach
            </div>

            <p class="px-4 pb-4 text-xs text-slate-400 sm:px-6">
                As destacadas são obrigatórias: sem bimestre, semana, componente e título,
                a aula não tem lugar no percurso.
            </p>
        </x-cartao>
    @endif
</div>
