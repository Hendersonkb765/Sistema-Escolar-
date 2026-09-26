@use('App\Support\ConciliadorDeAlunos')

<div class="mx-auto max-w-5xl space-y-4">
    @if ($importacao === null)
        <form wire:submit="conferir" class="space-y-4">
            <x-cartao titulo="Planilha do leitor de folhas"
                      descricao="O primeiro passo apenas confere: nada é gravado até você confirmar.">
                <div class="space-y-4">
                    <x-campo rotulo="Prova" para="prova_id" obrigatorio :erro="$errors->first('prova_id')">
                        @if ($provas->isEmpty())
                            <x-alerta tipo="atencao">
                                Nenhuma prova gerada ainda. Monte a prova antes de importar os resultados.
                            </x-alerta>
                        @else
                            <x-select id="prova_id" wire:model="prova_id" required>
                                <option value="">Selecione…</option>
                                @foreach ($provas as $opcao)
                                    <option value="{{ $opcao->id }}">
                                        {{ $opcao->titulo }} · {{ $opcao->turma->nome }} · {{ $opcao->turma->curso->nome }}
                                    </option>
                                @endforeach
                            </x-select>
                        @endif
                    </x-campo>

                    <x-campo rotulo="Arquivo" para="planilha" obrigatorio :erro="$errors->first('planilha')"
                             ajuda="XLSX, XLS ou CSV de até 5 MB, com as colunas First Name, Last Name e Q1, Q2, Q3…">
                        <input type="file" id="planilha" wire:model="planilha"
                               accept=".xlsx,.xls,.csv"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                    </x-campo>

                    <x-alerta tipo="info" titulo="O cadastro dos alunos não é alterado">
                        A planilha serve só para dizer de quem é cada resultado. O nome que está
                        no sistema continua como está — se a conferência não reconhecer alguém,
                        ela recusa a linha e diz por quê.
                    </x-alerta>
                </div>
            </x-cartao>

            <div class="flex items-center justify-end gap-2">
                <x-botao variante="secundario" href="{{ route('importacoes.index') }}" wire:navigate>Cancelar</x-botao>
                <x-botao tipo="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="conferir,planilha">Conferir planilha</span>
                    <span wire:loading wire:target="conferir,planilha">Lendo…</span>
                </x-botao>
            </div>
        </form>
    @else
        @php
            $comErro = $linhas->whereNotNull('erro');
            $aprovadas = $linhas->whereNull('erro');
        @endphp

        <x-cartao :titulo="'Conferência — '.$importacao->nome_original"
                  :descricao="$importacao->prova->titulo.' · Turma '.$importacao->prova->turma->nome">
            <x-slot:acoes>
                <x-botao variante="secundario" wire:click="descartar"
                         wire:confirm="Descartar esta conferência? Nada foi gravado.">
                    Descartar
                </x-botao>

                <x-botao wire:click="confirmar" :desabilitado="$aprovadas->isEmpty()"
                         title="{{ $aprovadas->isEmpty()
                            ? 'Nenhuma linha passou na conferência.'
                            : 'Grava '.$aprovadas->count().' resultado(s) e calcula as notas.' }}">
                    Confirmar {{ $aprovadas->count() }} resultado(s)
                </x-botao>
            </x-slot:acoes>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Linhas lidas</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $linhas->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Reconhecidas</dt>
                    <dd class="mt-0.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">{{ $aprovadas->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Recusadas</dt>
                    <dd class="mt-0.5 text-sm font-medium {{ $comErro->isEmpty() ? 'text-slate-400' : 'text-rose-700 dark:text-rose-400' }}">
                        {{ $comErro->count() }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Questões</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ $importacao->relatorio['questoes_na_prova'] ?? 0 }} na prova ·
                        {{ $importacao->relatorio['questoes_na_planilha'] ?? 0 }} na planilha
                    </dd>
                </div>
            </div>

            @if ($comErro->isNotEmpty())
                <x-alerta tipo="atencao" class="mt-4" titulo="Linhas que ficarão de fora">
                    Confirmar grava só as reconhecidas. Corrija a planilha e envie de novo para
                    trazer as demais.
                </x-alerta>
            @endif
        </x-cartao>

        <x-cartao titulo="Linha a linha">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Linha</th>
                            <th class="px-4 py-2">Na planilha</th>
                            <th class="px-4 py-2">Aluno no sistema</th>
                            <th class="hidden px-4 py-2 md:table-cell">Acertos</th>
                            <th class="px-4 py-2">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($linhas as $linha)
                            <tr wire:key="linha-{{ $linha['linha'] }}">
                                <td class="px-4 py-3 tabular-nums text-slate-500 sm:px-6">{{ $linha['linha'] }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                                    {{ $linha['nome_na_planilha'] }}
                                    @if ($linha['identificacao'])
                                        <span class="block text-xs text-slate-400">id {{ $linha['identificacao'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($linha['aluno'])
                                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $linha['aluno'] }}</span>
                                        <span class="block text-xs text-slate-400">
                                            @switch($linha['criterio'])
                                                @case(ConciliadorDeAlunos::POR_MATRICULA) reconhecido pela matrícula @break
                                                @case(ConciliadorDeAlunos::POR_NOME) nome idêntico @break
                                                @default primeiro e último nome
                                            @endswitch
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ $linha['acertos'] }}/{{ $linha['total'] }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($linha['erro'])
                                        <x-badge cor="vermelho" rotulo="Recusada"/>
                                        <span class="mt-1 block text-xs text-rose-700 dark:text-rose-400">{{ $linha['erro'] }}</span>
                                        @foreach ($linha['candidatos'] ?? [] as $candidato)
                                            <span class="block text-xs text-slate-500">· {{ $candidato }}</span>
                                        @endforeach
                                    @else
                                        <x-badge cor="verde" rotulo="Reconhecida"/>
                                        @foreach ($linha['avisos'] ?? [] as $aviso)
                                            <span class="mt-1 block text-xs text-amber-700 dark:text-amber-400">{{ $aviso }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>
    @endif
</div>
