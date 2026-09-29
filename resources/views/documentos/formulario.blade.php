<div class="space-y-4">
    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            <x-cartao titulo="Identificação">
                <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">
                    <x-campo rotulo="Nome do modelo" para="nome" obrigatorio :erro="$errors->first('nome')"
                             class="sm:col-span-2">
                        <x-input id="nome" wire:model="nome" placeholder="Autorização de saída pedagógica"/>
                    </x-campo>

                    <x-campo rotulo="Descrição" para="descricao" :erro="$errors->first('descricao')"
                             ajuda="Aparece na lista, para diferenciar modelos parecidos."
                             class="sm:col-span-2">
                        <x-input id="descricao" wire:model="descricao" placeholder="Para visitas técnicas fora da escola"/>
                    </x-campo>

                    <x-campo rotulo="Eixo" para="eixo_id" obrigatorio :erro="$errors->first('eixo_id')">
                        <x-select id="eixo_id" wire:model="eixo_id">
                            @foreach ($eixos as $eixo)
                                <option value="{{ $eixo->id }}">{{ $eixo->nome }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Situação" para="ativo">
                        <label class="flex items-center gap-2 pt-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" id="ativo" wire:model="ativo"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            Disponível para gerar documentos
                        </label>
                    </x-campo>
                </div>
            </x-cartao>

            <x-cartao titulo="Formato">
                <div class="space-y-4 p-4 sm:p-6">
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Como o documento sai
                        </legend>

                        <div class="mt-2 space-y-2">
                            @foreach (App\Enums\TipoDeDocumento::cases() as $opcao)
                                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                    <input type="radio" name="tipo" value="{{ $opcao->value }}"
                                           wire:model.live="tipo"
                                           class="mt-0.5 border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ $opcao->rotulo() }}
                                        </span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ $opcao->descricao() }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @if ($this->tipoEscolhido() === App\Enums\TipoDeDocumento::Individual)
                        <x-campo rotulo="Vias por página" para="por_pagina" :erro="$errors->first('por_pagina')"
                                 ajuda="Com texto curto, três vias na mesma folha economizam papel — e a folha sai com linha de corte entre elas.">
                            <x-select id="por_pagina" wire:model.live="por_pagina">
                                @foreach (App\Models\ModeloDocumento::POR_PAGINA as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </x-select>
                        </x-campo>
                    @endif
                </div>
            </x-cartao>

            <x-cartao titulo="Texto do documento">
                <div class="space-y-3 p-4 sm:p-6">
                    <x-campo para="corpo" obrigatorio :erro="$errors->first('corpo')">
                        <x-area-texto id="corpo" wire:model.live.debounce.500ms="corpo" :linhas="14"
                                      class="font-mono text-sm"/>
                    </x-campo>

                    @error('corpo')
                        {{-- Os demais campos recusados, além do primeiro que o x-campo já mostra. --}}
                        @if (count($errors->get('corpo')) > 1)
                            <x-alerta tipo="erro">
                                <ul class="list-disc space-y-0.5 pl-4">
                                    @foreach (array_slice($errors->get('corpo'), 1) as $outro)
                                        <li>{{ $outro }}</li>
                                    @endforeach
                                </ul>
                            </x-alerta>
                        @endif
                    @enderror

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Use <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">**negrito**</code> e
                        <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">*itálico*</code>.
                        Os campos entre chaves viram os dados de cada aluno.
                    </p>
                </div>
            </x-cartao>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <x-cartao titulo="Como vai sair"
                      descricao="Com dados de exemplo. No documento de verdade entram os do aluno.">
                <div class="p-4 sm:p-6">
                    <div class="previa-do-documento rounded-lg border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-800 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        {!! $this->previa() !!}
                    </div>
                </div>
            </x-cartao>

            <x-cartao titulo="Campos que você pode usar"
                      descricao="Clique num campo para acrescentá-lo ao fim do texto.">
                <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    @foreach ($campos as $campo => $explicacao)
                        <div class="px-4 py-2 sm:px-6">
                            <dt>
                                <button type="button" wire:click="inserirCampo({{ Js::from($campo) }})"
                                        class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-800 transition hover:bg-marca-100 hover:text-marca-800 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-marca-900 dark:hover:text-marca-100"
                                        title="Acrescentar ao fim do texto">
                                    &#123;&#123; {{ $campo }} &#125;&#125;
                                </button>
                            </dt>
                            <dd class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $explicacao }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-cartao>
        </div>
    </div>

    <x-cartao>
        <div class="flex items-center justify-end gap-2 p-4 sm:p-6">
            <x-botao variante="secundario" href="{{ route('documentos.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao wire:click="salvar">Salvar modelo</x-botao>
        </div>
    </x-cartao>
</div>
