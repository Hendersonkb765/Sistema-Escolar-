<div class="mx-auto max-w-4xl space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeEditar)
                <x-botao variante="secundario" wire:click="salvarTudo" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="salvarTudo">Salvar rascunho</span>
                    <span wire:loading wire:target="salvarTudo">Salvando…</span>
                </x-botao>
                @unless ($confirmandoEnvio)
                    <x-botao wire:click="$set('confirmandoEnvio', true)" :desabilitado="$incompletas->isNotEmpty()">
                        Enviar para análise
                    </x-botao>
                @endunless
            @endif
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Turma</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $solicitacao->turma->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Questões</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->quantidade_questoes }} · {{ $solicitacao->quantidade_alternativas }} alternativas cada
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Prazo</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->prazo->format('d/m/Y H:i') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Situação</dt>
                <dd class="mt-0.5 flex flex-wrap gap-1">
                    <x-badge :cor="$solicitacao->status->cor()" :rotulo="$solicitacao->status->rotulo()"/>
                    @if ($rotulo = $solicitacao->rotuloDePrazo())
                        <x-badge cor="vermelho" :rotulo="$rotulo"/>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($solicitacao->observacoes)
            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Observações de {{ $solicitacao->criadoPor->nome }}
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $solicitacao->observacoes }}</p>
            </div>
        @endif

        @if (! $podeEditar)
            <x-alerta tipo="info" class="mt-4" titulo="Esta solicitação está fechada">
                A coordenação encerrou ou cancelou esta solicitação. As questões ficam visíveis, mas
                não podem mais ser alteradas.
            </x-alerta>
        @elseif ($solicitacao->estaAtrasada())
            <x-alerta tipo="atencao" class="mt-4" titulo="O prazo venceu em {{ $solicitacao->prazo->format('d/m/Y H:i') }}">
                Você continua podendo enviar. O envio será aceito e ficará registrado como feito em atraso.
            </x-alerta>
        @endif

        @if ($confirmandoEnvio)
            <div class="mt-4 space-y-3 rounded-lg border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-500/30 dark:bg-sky-500/5">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Enviar {{ $solicitacao->quantidade_questoes }} questão(ões) para análise?
                </h3>

                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Disciplina</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $solicitacao->disciplina->nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Turma</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $solicitacao->turma->nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Questões</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $questoes->count() }} completas</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Soma dos pesos</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-100">{{ number_format($somaDosPesos, 2, ',', '.') }}</dd>
                    </div>
                </dl>

                <p class="text-sm text-slate-700 dark:text-slate-200">
                    Depois do envio as questões ficam bloqueadas para edição até a coordenação
                    analisar. Se alguma for devolvida para correção, você poderá editá-la de novo.
                    @if ($solicitacao->estaAtrasada())
                        <strong class="text-amber-700 dark:text-amber-300">
                            O envio será registrado como feito em atraso — e será aceito assim mesmo.
                        </strong>
                    @endif
                </p>

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="$set('confirmandoEnvio', false)">Voltar</x-botao>
                    <x-botao wire:click="enviar" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="enviar">Confirmar envio</span>
                        <span wire:loading wire:target="enviar">Enviando…</span>
                    </x-botao>
                </div>
            </div>
        @endif
    </x-cartao>

    @if ($podeEditar)
        <x-alerta tipo="info">
            Você define o <strong>peso</strong> de cada questão — é ele que diz quanto ela vale na
            nota da disciplina. Soma atual: <strong>{{ number_format($somaDosPesos, 2, ',', '.') }}</strong>.
        </x-alerta>
    @endif

    @if ($incompletas->isNotEmpty() && $podeEditar)
        <x-alerta tipo="atencao" :titulo="'Faltam '.$incompletas->count().' questão(ões) para poder enviar'">
            Questões pendentes: {{ $incompletas->join(', ') }}. Cada uma precisa de enunciado, peso
            maior que zero, as {{ $solicitacao->quantidade_alternativas }} alternativas preenchidas
            e uma marcada como correta.
        </x-alerta>
    @endif

    @foreach ($questoes as $questao)
        @php
            $dados = $formulario[$questao->id] ?? ['enunciado' => null, 'alternativas' => []];
            $bloqueado = ! $podeEditar || ! $questao->status->editavelPeloProfessor();
        @endphp

        <x-cartao wire:key="questao-{{ $questao->id }}">
            <x-slot:titulo>Questão {{ $questao->item->ordem }}</x-slot:titulo>
            <x-slot:acoes>
                <x-badge :cor="$questao->status->cor()" :rotulo="$questao->status->rotulo()"/>
                @unless ($bloqueado)
                    <x-botao variante="discreto" wire:click="salvarQuestao({{ $questao->id }})">Salvar</x-botao>
                @endunless
            </x-slot:acoes>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-campo rotulo="Enunciado" :para="'enunciado-'.$questao->id" obrigatorio class="sm:col-span-3">
                        <x-area-texto id="enunciado-{{ $questao->id }}" :linhas="3"
                                      wire:model.blur="formulario.{{ $questao->id }}.enunciado"
                                      :desabilitado="$bloqueado"
                                      placeholder="O comando da questão"/>
                    </x-campo>

                    <x-campo rotulo="Peso da questão" :para="'peso-'.$questao->id" obrigatorio
                             ajuda="Quanto ela vale na nota da disciplina.">
                        <x-input tipo="number" step="0.25" min="0.25" max="100"
                                 id="peso-{{ $questao->id }}"
                                 wire:model.live.debounce.500ms="formulario.{{ $questao->id }}.peso"
                                 :desabilitado="$bloqueado"/>
                    </x-campo>
                </div>

                {{-- Blocos do enunciado: código, imagem e parágrafos --}}
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Conteúdo do enunciado
                            <span class="font-normal text-slate-500">— código, imagem ou mais texto</span>
                        </p>
                        @unless ($bloqueado)
                            <div class="flex gap-1">
                                <x-botao variante="secundario" type="button"
                                         wire:click="adicionarBloco({{ $questao->id }}, 'codigo')">+ Código</x-botao>
                                <x-botao variante="secundario" type="button"
                                         wire:click="adicionarBloco({{ $questao->id }}, 'imagem')">+ Imagem</x-botao>
                                <x-botao variante="secundario" type="button"
                                         wire:click="adicionarBloco({{ $questao->id }}, 'texto')">+ Texto</x-botao>
                            </div>
                        @endunless
                    </div>

                    @if (empty($dados['blocos']))
                        <p class="mt-2 text-xs text-slate-400">
                            Nenhum bloco. Use os botões acima para anexar um trecho de código, uma
                            imagem ou um parágrafo extra.
                        </p>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($dados['blocos'] as $indice => $bloco)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-900/40"
                                     wire:key="bloco-{{ $questao->id }}-{{ $indice }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <x-badge :cor="App\Enums\TipoBlocoQuestao::from($bloco['tipo'])->cor()"
                                                 :rotulo="App\Enums\TipoBlocoQuestao::from($bloco['tipo'])->rotulo()"/>

                                        @unless ($bloqueado)
                                            <div class="flex items-center gap-1">
                                                <x-botao variante="discreto" type="button"
                                                         wire:click="moverBloco({{ $questao->id }}, {{ $indice }}, -1)"
                                                         title="Mover para cima">↑</x-botao>
                                                <x-botao variante="discreto" type="button"
                                                         wire:click="moverBloco({{ $questao->id }}, {{ $indice }}, 1)"
                                                         title="Mover para baixo">↓</x-botao>
                                                <x-botao variante="discreto" type="button"
                                                         wire:click="removerBloco({{ $questao->id }}, {{ $indice }})"
                                                         class="text-rose-600 dark:text-rose-400">Remover</x-botao>
                                            </div>
                                        @endunless
                                    </div>

                                    @if ($bloco['tipo'] === 'codigo')
                                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-4">
                                            <x-campo rotulo="Linguagem">
                                                <x-select wire:model.live="formulario.{{ $questao->id }}.blocos.{{ $indice }}.linguagem"
                                                          :desabilitado="$bloqueado">
                                                    @foreach ($linguagens as $valor => $rotulo)
                                                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                                                    @endforeach
                                                </x-select>
                                            </x-campo>

                                            <x-campo rotulo="Código" class="sm:col-span-3">
                                                <x-area-texto :linhas="6"
                                                              wire:model.blur="formulario.{{ $questao->id }}.blocos.{{ $indice }}.conteudo"
                                                              :desabilitado="$bloqueado"
                                                              class="font-mono"
                                                              placeholder="Cole aqui o trecho de código"/>
                                            </x-campo>
                                        </div>
                                    @elseif ($bloco['tipo'] === 'imagem')
                                        <div class="mt-2 space-y-2">
                                            @if (! empty($bloco['caminho']))
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($bloco['caminho']) }}"
                                                     alt="{{ $bloco['legenda'] ?: 'Imagem do enunciado' }}"
                                                     class="max-h-56 rounded-lg border border-slate-200 dark:border-slate-700">
                                            @endif

                                            @unless ($bloqueado)
                                                <x-campo rotulo="Arquivo de imagem"
                                                         ajuda="JPG, PNG, GIF ou WEBP, até 4 MB.">
                                                    <x-input tipo="file" accept="image/*"
                                                             wire:model="imagens.{{ $questao->id }}.{{ $indice }}"/>
                                                </x-campo>
                                            @endunless

                                            <x-campo rotulo="Legenda (opcional)">
                                                <x-input wire:model.blur="formulario.{{ $questao->id }}.blocos.{{ $indice }}.legenda"
                                                         :desabilitado="$bloqueado"/>
                                            </x-campo>
                                        </div>
                                    @else
                                        <x-campo rotulo="Texto" class="mt-2">
                                            <x-area-texto :linhas="3"
                                                          wire:model.blur="formulario.{{ $questao->id }}.blocos.{{ $indice }}.conteudo"
                                                          :desabilitado="$bloqueado"/>
                                        </x-campo>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Alternativas <span class="font-normal text-slate-500">— marque a correta</span>
                    </p>

                    @foreach ($dados['alternativas'] as $indice => $alternativa)
                        <div class="flex items-start gap-2" wire:key="alt-{{ $questao->id }}-{{ $alternativa['letra'] }}">
                            <button type="button"
                                    wire:click="marcarCorreta({{ $questao->id }}, '{{ $alternativa['letra'] }}')"
                                    @disabled($bloqueado)
                                    aria-pressed="{{ $alternativa['correta'] ? 'true' : 'false' }}"
                                    @class([
                                        'mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition',
                                        'bg-emerald-500 text-white' => $alternativa['correta'],
                                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => ! $alternativa['correta'],
                                    ])
                                    title="Marcar {{ $alternativa['letra'] }} como correta">
                                {{ $alternativa['letra'] }}
                            </button>

                            <x-input wire:model.blur="formulario.{{ $questao->id }}.alternativas.{{ $indice }}.texto"
                                     placeholder="Texto da alternativa {{ $alternativa['letra'] }}"
                                     :desabilitado="$bloqueado"/>
                        </div>
                    @endforeach
                </div>

                @if ($questao->feedbacks->isNotEmpty())
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Feedback da análise
                        </p>
                        @foreach ($questao->feedbacks as $feedback)
                            <div class="mt-2 text-sm">
                                <x-badge :cor="$feedback->acao->cor()" :rotulo="$feedback->acao->rotulo()"/>
                                <span class="text-slate-600 dark:text-slate-300">{{ $feedback->comentario }}</span>
                                <span class="block text-xs text-slate-400">
                                    {{ $feedback->analisadoPor->nome }} · {{ $feedback->created_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-cartao>
    @endforeach

    @if ($podeEditar)
        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('solicitacoes.index') }}" wire:navigate>Voltar</x-botao>
            <x-botao variante="secundario" wire:click="salvarTudo" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvarTudo">Salvar rascunho</span>
                <span wire:loading wire:target="salvarTudo">Salvando…</span>
            </x-botao>
            <x-botao wire:click="$set('confirmandoEnvio', true)" :desabilitado="$incompletas->isNotEmpty()">
                Enviar para análise
            </x-botao>
        </div>
    @endif
</div>
