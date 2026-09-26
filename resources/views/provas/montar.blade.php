<div class="mx-auto max-w-4xl">
    <form wire:submit="montar" class="space-y-4">
        <x-cartao titulo="A prova">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Turma" para="turma_id" obrigatorio :erro="$errors->first('turma_id')">
                        <x-select id="turma_id" wire:model.live="turma_id" required>
                            <option value="">Selecione…</option>
                            @foreach ($turmas as $turma)
                                <option value="{{ $turma->id }}">
                                    {{ $turma->nome }} · {{ $turma->curso->nome }} · {{ $turma->periodo }}º período
                                </option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Modelo de prova" para="modelo_prova_id" obrigatorio
                             :erro="$errors->first('modelo_prova_id')"
                             ajuda="Define cabeçalho, logo e rodapé.">
                        @if ($modelos->isEmpty())
                            <x-alerta tipo="atencao">
                                Nenhum modelo cadastrado.
                                <a href="{{ route('modelos-prova.index') }}" wire:navigate class="font-medium underline">
                                    Cadastre um modelo
                                </a> antes de montar a prova.
                            </x-alerta>
                        @else
                            <x-select id="modelo_prova_id" wire:model="modelo_prova_id" required>
                                @foreach ($modelos as $modelo)
                                    <option value="{{ $modelo->id }}">{{ $modelo->nome }}</option>
                                @endforeach
                            </x-select>
                        @endif
                    </x-campo>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-campo rotulo="Título" para="titulo" obrigatorio :erro="$errors->first('titulo')">
                        <x-input id="titulo" wire:model="titulo" required placeholder="Avaliação do 2º bimestre"/>
                    </x-campo>

                    <x-campo rotulo="Bimestre" para="bimestre" obrigatorio :erro="$errors->first('bimestre')"
                             ajuda="Sugerido pelas solicitações que geraram as questões.">
                        <x-select id="bimestre" wire:model="bimestre" required>
                            @foreach ($bimestres as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Data de aplicação" para="data_aplicacao"
                             :erro="$errors->first('data_aplicacao')">
                        <x-input tipo="date" id="data_aplicacao" wire:model="data_aplicacao"/>
                    </x-campo>
                </div>

                <x-campo rotulo="Instruções ao aluno" para="instrucoes" :erro="$errors->first('instrucoes')"
                         ajuda="Em branco, usa as instruções do modelo.">
                    <x-area-texto id="instrucoes" wire:model="instrucoes" :linhas="2"
                                  placeholder="Ex.: prova sem consulta; marque apenas uma alternativa por questão."/>
                </x-campo>
            </div>
        </x-cartao>

        <x-cartao titulo="Layout da folha">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo rotulo="Colunas de texto" para="colunas" obrigatorio :erro="$errors->first('colunas')"
                         ajuda="Duas colunas é o padrão de prova impressa.">
                    <x-select id="colunas" wire:model.live="colunas" required>
                        <option value="2">Duas colunas</option>
                        <option value="1">Coluna única</option>
                    </x-select>
                </x-campo>

                <label class="flex items-start gap-2 pt-6 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="mostrar_pesos"
                           class="mt-0.5 rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                    Mostrar o peso de cada questão na folha
                </label>

            </div>
        </x-cartao>

        <x-cartao titulo="Questões aprovadas"
                  :descricao="$totalEscolhido.' selecionada(s) · soma dos pesos '.number_format($somaDosPesos, 2, ',', '.')">
            @error('questoesEscolhidas')
                <x-alerta tipo="erro" class="mb-3">{{ $message }}</x-alerta>
            @enderror

            @if ($turmaSelecionada === null)
                <x-alerta tipo="info">Escolha a turma para ver as questões já aprovadas.</x-alerta>
            @elseif ($porDisciplina->isEmpty())
                <x-vazio titulo="Nenhuma questão aprovada nesta turma"
                         descricao="Analise e aprove as questões enviadas pelos professores antes de montar a prova.">
                    <x-slot:acoes>
                        <x-botao variante="secundario" href="{{ route('questoes.index') }}" wire:navigate>
                            Ir para a análise
                        </x-botao>
                    </x-slot:acoes>
                </x-vazio>
            @else
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    A numeração é contínua e agrupada por disciplina — o aluno vê só o número, e o
                    sistema guarda de qual disciplina e professor cada questão veio.
                </p>

                <div class="space-y-4">
                    @foreach ($porDisciplina as $disciplina => $questoes)
                        <div class="rounded-lg border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $disciplina }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $questoes->count() }} aprovada(s) · {{ $questoes->first()->professor->nome ?? '' }}
                                    </p>
                                </div>
                                <x-botao variante="discreto" type="button"
                                         wire:click="alternarDisciplina({{ $questoes->first()->disciplina_id }})">
                                    Marcar/desmarcar todas
                                </x-botao>
                            </div>

                            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($questoes as $questao)
                                    <li class="flex items-start gap-2 px-3 py-2" wire:key="questao-{{ $questao->id }}">
                                        <input type="checkbox" value="{{ $questao->id }}"
                                               wire:model.live="questoesEscolhidas"
                                               class="mt-1 rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm text-slate-700 dark:text-slate-200">
                                                {{ $questao->enunciado ?: '(sem enunciado)' }}
                                            </p>
                                            <p class="text-xs text-slate-400">
                                                peso {{ number_format((float) $questao->peso, 2, ',', '.') }}
                                                @if ($questao->versao > 1) · v{{ $questao->versao }} @endif
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('provas.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled" :desabilitado="$totalEscolhido === 0">
                <span wire:loading.remove wire:target="montar">Montar prova</span>
                <span wire:loading wire:target="montar">Montando…</span>
            </x-botao>
        </div>
    </form>
</div>
