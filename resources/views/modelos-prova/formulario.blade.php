<div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
    <form wire:submit="salvar" class="space-y-4 lg:col-span-3">
        <x-cartao titulo="Identificação do modelo">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Nome do modelo" para="nome" obrigatorio :erro="$errors->first('nome')"
                             ajuda="Só aparece aqui no sistema, para você escolher na montagem.">
                        <x-input id="nome" wire:model.blur="nome" required placeholder="Padrão do Eixo de TI"/>
                    </x-campo>

                    <x-campo rotulo="Eixo" para="eixo_id" obrigatorio :erro="$errors->first('eixo_id')">
                        <x-select id="eixo_id" wire:model="eixo_id" required>
                            <option value="">Selecione…</option>
                            @foreach ($eixos as $eixo)
                                <option value="{{ $eixo->id }}">{{ $eixo->nome }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Instituição" para="instituicao" :erro="$errors->first('instituicao')"
                             ajuda="Sai em destaque no topo da folha.">
                        <x-input id="instituicao" wire:model.blur="instituicao"/>
                    </x-campo>

                    <x-campo rotulo="Nome da avaliação" para="nome_avaliacao"
                             :erro="$errors->first('nome_avaliacao')"
                             ajuda="Ex.: Avaliação Bimestral. O título da prova vem depois dele.">
                        <x-input id="nome_avaliacao" wire:model.blur="nome_avaliacao"/>
                    </x-campo>
                </div>

                <x-campo rotulo="Cabeçalho" para="cabecalho" :erro="$errors->first('cabecalho')"
                         ajuda="Linha extra abaixo do título: curso, endereço, código do documento.">
                    <x-area-texto id="cabecalho" wire:model.blur="cabecalho" :linhas="2"/>
                </x-campo>

                <x-campo rotulo="Logo" para="logo" :erro="$errors->first('logo')"
                         ajuda="PNG ou JPG de até 2 MB. Sai à esquerda do cabeçalho.">
                    <input type="file" id="logo" wire:model="logo" accept="image/*"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                    @if ($modelo?->logo_path && ! $removerLogo)
                        <label class="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model.live="removerLogo"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            Remover a logo atual
                        </label>
                    @endif
                </x-campo>
            </div>
        </x-cartao>

        <x-cartao titulo="Quadro de identificação"
                  descricao="O que o aluno preenche antes de começar a prova.">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($camposDisponiveis as $chave => $rotulo)
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" value="{{ $chave }}" wire:model.live="campos_identificacao"
                               class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                        {{ $rotulo }}
                    </label>
                @endforeach
            </div>
            @error('campos_identificacao')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </x-cartao>

        <x-cartao titulo="Instruções e rodapé">
            <div class="space-y-4">
                <x-campo rotulo="Instruções padrão" para="instrucoes" :erro="$errors->first('instrucoes')"
                         ajuda="Usadas quando a montagem da prova não informar outras.">
                    <x-area-texto id="instrucoes" wire:model.blur="instrucoes" :linhas="3"
                                  placeholder="Leia atentamente cada questão. Marque apenas uma alternativa."/>
                </x-campo>

                <x-campo rotulo="Rodapé" para="rodape" :erro="$errors->first('rodape')">
                    <x-area-texto id="rodape" wire:model.blur="rodape" :linhas="2"/>
                </x-campo>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="ativo"
                           class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                    Disponível para montar provas
                </label>
            </div>
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('modelos-prova.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit">Salvar modelo</x-botao>
        </div>
    </form>

    <div class="lg:col-span-2">
        <x-cartao titulo="Amostra da folha"
                  descricao="As questões são de exemplo; a moldura é a que será impressa.">
            <iframe srcdoc="{{ $folha }}"
                    title="Amostra do modelo de prova"
                    class="h-[70vh] w-full rounded-lg border border-slate-300 bg-white dark:border-slate-700"></iframe>
        </x-cartao>
    </div>
</div>
