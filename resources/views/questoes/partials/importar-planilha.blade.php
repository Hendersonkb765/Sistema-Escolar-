{{-- Importação das questões de uma parte por planilha. Variáveis: $parte, e o estado do componente. --}}
<div class="space-y-4 rounded-lg border border-marca-200 bg-marca-50/40 p-4 dark:border-marca-500/30 dark:bg-marca-500/5" data-importacao-de-questoes>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Preencher {{ $parte->disciplina->nome }} por planilha</h3>
            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                Baixe o modelo — já numerado com as {{ $parte->quantidade_questoes }} questões e
                {{ $parte->solicitacao->quantidade_alternativas }} alternativas —, preencha uma questão por linha e envie.
                As questões entram como rascunho; o envio para análise continua sendo o botão de sempre.
            </p>
        </div>
        <div class="flex shrink-0 gap-2">
            <x-botao variante="secundario" href="{{ route('solicitacoes.partes.modelo', $parte) }}">Modelo XLSX</x-botao>
            <x-botao variante="secundario" href="{{ route('solicitacoes.partes.modelo', ['parte' => $parte, 'formato' => 'csv']) }}">Modelo CSV</x-botao>
        </div>
    </div>

    @if ($relatorioDeQuestoes === null)
        <form wire:submit="conferirPlanilha" class="space-y-3">
            <x-campo rotulo="Planilha preenchida" para="planilha-questoes-{{ $parte->id }}" :erro="$errors->first('planilhaDeQuestoes')"
                     ajuda="XLSX, ODS ou CSV. Nada é gravado antes de você conferir.">
                <input type="file" id="planilha-questoes-{{ $parte->id }}" wire:model="planilhaDeQuestoes" accept=".xlsx,.xls,.ods,.csv"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
            </x-campo>
            <div wire:loading wire:target="planilhaDeQuestoes" class="text-sm text-slate-500">Enviando o arquivo…</div>
            <div class="flex justify-end gap-2">
                <x-botao variante="secundario" wire:click="fecharImportacao">Cancelar</x-botao>
                <x-botao tipo="submit" wire:loading.attr="disabled">Conferir planilha</x-botao>
            </div>
        </form>
    @else
        @php
            $comErro = $relatorioDeQuestoes['com_erro'];
            $substituidas = collect($relatorioDeQuestoes['linhas'])->where('substitui', true)->pluck('questao');
        @endphp

        @if ($comErro > 0)
            <x-alerta tipo="erro" titulo="{{ $comErro }} linha(s) precisam de correção">
                Corrija as linhas marcadas abaixo na planilha e envie de novo. Nada foi gravado.
            </x-alerta>
        @else
            <x-alerta tipo="sucesso" titulo="Planilha sem erros">
                {{ count($relatorioDeQuestoes['linhas']) }} questão(ões) prontas para entrar como rascunho.
                @if ($substituidas->isNotEmpty())
                    O rascunho atual da(s) questão(ões) {{ $substituidas->join(', ', ' e ') }} será substituído.
                @endif
            </x-alerta>
        @endif

        <div class="-mx-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-2">Linha</th>
                        <th class="px-2 py-2">Questão</th>
                        <th class="px-2 py-2">Enunciado</th>
                        <th class="px-2 py-2">Pontuação</th>
                        <th class="px-2 py-2">Correta</th>
                        <th class="px-4 py-2">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($relatorioDeQuestoes['linhas'] as $linha)
                        <tr wire:key="linha-planilha-{{ $linha['linha'] }}" @if ($linha['erros'] !== []) data-linha-com-erro @endif
                            @class(['align-top', 'bg-rose-50/60 dark:bg-rose-500/5' => $linha['erros'] !== []])>
                            <td class="px-4 py-2 tabular-nums">{{ $linha['linha'] }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ $linha['questao'] ?? '—' }}</td>
                            <td class="px-2 py-2 text-slate-700 dark:text-slate-200">{{ $linha['resumo'] ?? '—' }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ $linha['pontuacao'] !== null ? number_format($linha['pontuacao'], 2, ',', '.') : '—' }}</td>
                            <td class="px-2 py-2">{{ $linha['correta'] ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($linha['erros'] !== [])
                                    <span class="text-rose-700 dark:text-rose-300">{{ ucfirst(implode('; ', $linha['erros'])) }}.</span>
                                @elseif ($linha['substitui'])
                                    <span class="text-amber-700 dark:text-amber-300">Substitui o rascunho atual</span>
                                @else
                                    <span class="text-emerald-700 dark:text-emerald-300">Pronta</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($relatorioDeQuestoes['nao_preenchidas'] !== [])
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Sem preenchimento na planilha: questão(ões) {{ implode(', ', $relatorioDeQuestoes['nao_preenchidas']) }}. Continuam como estão.
            </p>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2">
            <p class="mr-auto text-xs text-slate-500 dark:text-slate-400">O que estiver na tela sem salvar nessas questões é substituído pela planilha.</p>
            <x-botao variante="secundario" wire:click="fecharImportacao">Cancelar</x-botao>
            <x-botao variante="secundario" wire:click="$set('relatorioDeQuestoes', null)">Enviar outro arquivo</x-botao>
            <x-botao wire:click="importarPlanilha" :desabilitado="$comErro > 0"
                     title="{{ $comErro > 0 ? 'Corrija as '.$comErro.' linha(s) com erro antes de importar.' : 'Gravar as questões como rascunho' }}">
                Importar {{ count($relatorioDeQuestoes['linhas']) }} questão(ões)
            </x-botao>
        </div>
    @endif
</div>
