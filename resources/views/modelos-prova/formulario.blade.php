@use('Illuminate\Support\Facades\Storage')

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

            </div>
        </x-cartao>

        <x-cartao titulo="Logos do cabeçalho"
                  descricao="Uma em cada extremo, com a identificação da escola ao centro.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($lados as $lado => $rotulo)
                    @php
                        $campoOrigem = "origem_logo_{$lado}";
                        $campoArquivo = 'logo'.ucfirst($lado);
                        $guardada = $modelo?->{"logo_{$lado}_path"};
                        $escolhida = $this->{$campoOrigem};
                    @endphp

                    <x-campo :rotulo="$rotulo" :erro="$errors->first($campoOrigem)">
                        <div class="space-y-2">
                            @foreach ($origensDaLogo as $valor => $texto)
                                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    {{-- Radio de verdade: a escolha é uma entre três. --}}
                                    <input type="radio" value="{{ $valor }}"
                                           wire:model.live="{{ $campoOrigem }}"
                                           class="mt-0.5 border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                                    <span>
                                        {{ $texto }}
                                        @if ($valor === 'padrao')
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                Já vem com o sistema — não precisa enviar nada.
                                            </span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @if ($escolhida === 'padrao')
                            <img src="/{{ App\Support\LogoDaFolha::PADRAO[$lado] }}" alt=""
                                 class="mt-3 h-16 w-auto rounded border border-slate-200 bg-white p-1 dark:border-slate-700">
                        @elseif ($escolhida === 'enviada')
                            @if ($guardada)
                                <img src="{{ Storage::disk('public')->url($guardada) }}" alt=""
                                     class="mt-3 h-16 w-auto rounded border border-slate-200 bg-white p-1 dark:border-slate-700">
                            @endif

                            <div class="mt-3 space-y-1.5">
                                <input type="file" id="{{ $campoArquivo }}" wire:model="{{ $campoArquivo }}"
                                       accept="image/png,image/jpeg,image/gif"
                                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">

                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    PNG, JPG ou GIF de até 2 MB. {{ $guardada ? 'Envie outra para substituir.' : '' }}
                                </p>

                                @error($campoArquivo)
                                    <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </x-campo>
                @endforeach
            </div>
        </x-cartao>

        <x-cartao titulo="Formatação da folha"
                  :descricao="$normaEscolhida->descricao()">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Norma" para="norma" obrigatorio :erro="$errors->first('norma')">
                        <x-select id="norma" wire:model.live="norma" required>
                            @foreach ($normas as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Fonte" para="fonte" obrigatorio :erro="$errors->first('fonte')"
                             ajuda="A ABNT admite as duas.">
                        <x-select id="fonte" wire:model.live="fonte" required>
                            @foreach ($fontesDisponiveis as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>
                </div>

                @php $travado = $normaEscolhida->fixaAFormatacao(); @endphp

                @if ($travado)
                    <x-alerta tipo="info" titulo="Os valores abaixo são da norma">
                        Enquanto a formatação for {{ $normaEscolhida->rotulo() }}, tamanho,
                        espaçamento e margens ficam fixos. Escolha <strong>Livre</strong> para
                        ajustá-los.
                    </x-alerta>
                @endif

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <x-campo rotulo="Corpo (pt)" para="tamanho" :erro="$errors->first('tamanho')">
                        <x-input tipo="number" id="tamanho" wire:model.live="tamanho" min="8" max="16"
                                 :desabilitado="$travado"
                                 title="{{ $travado ? 'A norma fixa o corpo em 12 pt.' : '' }}"/>
                    </x-campo>

                    <x-campo rotulo="Entrelinhas" para="espacamento" :erro="$errors->first('espacamento')">
                        <x-input tipo="number" id="espacamento" wire:model.live="espacamento"
                                 step="0.05" min="1" max="2" :desabilitado="$travado"
                                 title="{{ $travado ? 'A norma fixa o espaçamento em 1,5.' : '' }}"/>
                    </x-campo>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ([
                        'margem_superior' => 'Sup. (mm)',
                        'margem_esquerda' => 'Esq. (mm)',
                        'margem_inferior' => 'Inf. (mm)',
                        'margem_direita' => 'Dir. (mm)',
                    ] as $campo => $rotulo)
                        <x-campo :rotulo="$rotulo" :para="$campo" :erro="$errors->first($campo)">
                            <x-input tipo="number" :id="$campo" wire:model.live="{{ $campo }}"
                                     step="1" min="5" max="50" :desabilitado="$travado"
                                     title="{{ $travado ? 'A norma fixa 3 cm em cima e à esquerda, 2 cm embaixo e à direita.' : '' }}"/>
                        </x-campo>
                    @endforeach
                </div>
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
