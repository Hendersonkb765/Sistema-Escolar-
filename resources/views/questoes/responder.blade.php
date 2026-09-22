<div class="mx-auto max-w-4xl space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeEditar)
                <x-botao variante="secundario" wire:click="salvarTudo" wire:loading.attr="disabled">
                    Salvar rascunho
                </x-botao>
                @if ($confirmandoEnvio)
                    <x-botao wire:click="enviar" wire:loading.attr="disabled">Confirmar envio</x-botao>
                    <x-botao variante="discreto" wire:click="$set('confirmandoEnvio', false)">Voltar</x-botao>
                @else
                    <x-botao wire:click="$set('confirmandoEnvio', true)" :disabled="$incompletas->isNotEmpty()">
                        Enviar para análise
                    </x-botao>
                @endif
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
            <x-alerta tipo="info" class="mt-4" titulo="Enviar as {{ $solicitacao->quantidade_questoes }} questões para análise?">
                Depois do envio elas ficam bloqueadas para edição até a coordenação analisar.
                @if ($solicitacao->estaAtrasada())
                    O envio será marcado como <strong>em atraso</strong>.
                @endif
            </x-alerta>
        @endif
    </x-cartao>

    @if ($incompletas->isNotEmpty() && $podeEditar)
        <x-alerta tipo="atencao" :titulo="'Faltam '.$incompletas->count().' questão(ões) para poder enviar'">
            Questões pendentes: {{ $incompletas->join(', ') }}. Cada uma precisa de enunciado,
            as {{ $solicitacao->quantidade_alternativas }} alternativas preenchidas e uma marcada como correta.
        </x-alerta>
    @endif

    @foreach ($questoes as $questao)
        @php $dados = $formulario[$questao->id] ?? ['enunciado' => null, 'alternativas' => []]; @endphp

        <x-cartao wire:key="questao-{{ $questao->id }}">
            <x-slot:titulo>Questão {{ $questao->item->ordem }}</x-slot:titulo>
            <x-slot:acoes>
                <x-badge cor="cinza">peso {{ number_format((float) $questao->peso, 2, ',', '.') }}</x-badge>
                <x-badge :cor="$questao->status->cor()" :rotulo="$questao->status->rotulo()"/>
                @if ($podeEditar && $questao->status->editavelPeloProfessor())
                    <x-botao variante="discreto" wire:click="salvarQuestao({{ $questao->id }})">Salvar</x-botao>
                @endif
            </x-slot:acoes>

            <div class="space-y-4">
                <x-campo rotulo="Enunciado" :para="'enunciado-'.$questao->id" obrigatorio>
                    <textarea id="enunciado-{{ $questao->id }}" rows="3"
                              wire:model.blur="formulario.{{ $questao->id }}.enunciado"
                              @disabled(! $podeEditar || ! $questao->status->editavelPeloProfessor())
                              class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30 disabled:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-900"></textarea>
                </x-campo>

                <div class="space-y-2">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Alternativas <span class="font-normal text-slate-500">— marque a correta</span>
                    </p>

                    @foreach ($dados['alternativas'] as $indice => $alternativa)
                        <div class="flex items-start gap-2" wire:key="alt-{{ $questao->id }}-{{ $alternativa['letra'] }}">
                            <button type="button"
                                    wire:click="marcarCorreta({{ $questao->id }}, '{{ $alternativa['letra'] }}')"
                                    @disabled(! $podeEditar || ! $questao->status->editavelPeloProfessor())
                                    @class([
                                        'mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition',
                                        'bg-emerald-500 text-white' => $alternativa['correta'],
                                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => ! $alternativa['correta'],
                                    ])
                                    :aria-pressed="$alternativa['correta'] ? 'true' : 'false'"
                                    title="Marcar {{ $alternativa['letra'] }} como correta">
                                {{ $alternativa['letra'] }}
                            </button>

                            <x-input wire:model.blur="formulario.{{ $questao->id }}.alternativas.{{ $indice }}.texto"
                                     placeholder="Texto da alternativa {{ $alternativa['letra'] }}"
                                     @disabled(! $podeEditar || ! $questao->status->editavelPeloProfessor())/>
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
            <x-botao variante="secundario" wire:click="salvarTudo">Salvar rascunho</x-botao>
            <x-botao wire:click="$set('confirmandoEnvio', true)" :disabled="$incompletas->isNotEmpty()">
                Enviar para análise
            </x-botao>
        </div>
    @endif
</div>
