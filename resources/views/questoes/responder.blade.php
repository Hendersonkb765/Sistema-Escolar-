<div class="mx-auto max-w-4xl space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeEditar)
                <x-botao variante="secundario" wire:click="salvarTudo" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="salvarTudo">Salvar rascunho</span>
                    <span wire:loading wire:target="salvarTudo">Salvando…</span>
                </x-botao>
            @endif
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Prova</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->identificacao() }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Turma</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $solicitacao->turma->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Prazo</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->prazo->format('d/m/Y H:i') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Alternativas</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->quantidade_alternativas }} por questão
                </dd>
            </div>
        </dl>

        @if ($partes->count() > 1)
            <x-alerta tipo="info" class="mt-4" titulo="Você responde {{ $partes->count() }} disciplinas nesta prova">
                Cada uma é entregue separadamente, quando estiver pronta.
            </x-alerta>
        @endif

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

    </x-cartao>

    @if ($devolvidas->isNotEmpty())
        <div class="rounded-xl border-2 border-rose-300 bg-rose-50 p-4 dark:border-rose-500/50 dark:bg-rose-950/40">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-rose-900 dark:text-rose-100">
                        @if ($devolvidas->count() === 1)
                            A coordenação devolveu 1 questão para correção
                        @else
                            A coordenação devolveu {{ $devolvidas->count() }} questões para correção
                        @endif
                    </h2>

                    <p class="mt-0.5 text-sm text-rose-800 dark:text-rose-200">
                        Leia o que foi pedido em cada uma, ajuste e clique em
                        <strong>Reenviar corrigida</strong>. As demais questões seguem em análise.
                    </p>

                    <ul class="mt-3 space-y-2">
                        @foreach ($devolvidas as $devolvida)
                            @php $ultimo = $devolvida->feedbacks->first(); @endphp
                            <li class="rounded-lg bg-white/70 p-2 text-sm dark:bg-slate-900/50">
                                <a href="#questao-{{ $devolvida->id }}"
                                   class="font-semibold text-rose-900 hover:underline dark:text-rose-100">
                                    {{ $devolvida->parte->disciplina->nome }} · questão {{ $devolvida->ordem }}
                                </a>
                                @if ($ultimo?->comentario)
                                    <span class="text-slate-700 dark:text-slate-200">— {{ $ultimo->comentario }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if ($podeEditar)
        <x-alerta tipo="info">
            Você define o <strong>peso</strong> de cada questão — é ele que diz quanto ela vale na
            nota da disciplina. A soma de cada disciplina aparece no cabeçalho do bloco.
        </x-alerta>
    @endif

    @foreach ($partes as $parte)
        @php
            $questoesDaParte = $questoesPorParte[$parte->id] ?? collect();
            $pendenciasDaParte = $pendenciasPorParte[$parte->id] ?? collect();
            $parteBloqueada = ! $podeEditar || ! $parte->aceitaEnvio();
            $somaDaParte = $questoesDaParte->sum(fn ($q) => (float) ($formulario[$q->id]['peso'] ?? $q->peso));
        @endphp

        <div class="space-y-3" wire:key="bloco-parte-{{ $parte->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        {{ $parte->disciplina->nome }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $parte->quantidade_questoes }} questão(ões) ·
                        soma dos pesos {{ number_format($somaDaParte, 2, ',', '.') }}
                        @if ($parte->observacoes) · {{ $parte->observacoes }} @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :cor="$parte->status->cor()" :rotulo="$parte->status->rotulo()"/>
                    @if ($rotuloParte = $parte->rotuloDePrazo())
                        <x-badge cor="vermelho" :rotulo="$rotuloParte"/>
                    @endif

                    @if ($parte->aceitaEnvio() && $confirmandoEnvio !== $parte->id)
                        <x-botao wire:click="confirmarEnvio({{ $parte->id }})"
                                 :desabilitado="$pendenciasDaParte->isNotEmpty()"
                                 title="{{ $pendenciasDaParte->isNotEmpty()
                                    ? 'Complete as questões '.$pendenciasDaParte->keys()->join(', ').' desta disciplina'
                                    : 'Enviar as questões de '.$parte->disciplina->nome.' para análise' }}">
                            Enviar {{ $parte->disciplina->nome }}
                        </x-botao>
                    @endif
                </div>
            </div>

            @if ($pendenciasDaParte->isNotEmpty() && $parte->aceitaEnvio())
                <x-alerta tipo="atencao"
                          :titulo="$pendenciasDaParte->count() === 1
                            ? 'Falta 1 questão para liberar o envio de '.$parte->disciplina->nome
                            : 'Faltam '.$pendenciasDaParte->count().' questões para liberar o envio de '.$parte->disciplina->nome">
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($pendenciasDaParte as $ordem => $itens)
                            <li><strong>Questão {{ $ordem }}:</strong> {{ implode('; ', $itens) }}.</li>
                        @endforeach
                    </ul>
                </x-alerta>
            @endif

            @if ($confirmandoEnvio === $parte->id)
                <div class="space-y-3 rounded-lg border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-500/30 dark:bg-sky-500/5">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        Enviar as {{ $questoesDaParte->count() }} questões de {{ $parte->disciplina->nome }} para análise?
                    </h3>

                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        Elas ficam bloqueadas para edição até a coordenação analisar. Se alguma for
                        devolvida, você poderá corrigi-la. As outras disciplinas desta prova seguem
                        independentes.
                        @if ($solicitacao->estaAtrasada())
                            <strong class="text-amber-700 dark:text-amber-300">
                                O envio será registrado como feito em atraso — e será aceito assim mesmo.
                            </strong>
                        @endif
                    </p>

                    <div class="flex justify-end gap-2">
                        <x-botao variante="secundario" wire:click="cancelarEnvio">Voltar</x-botao>
                        <x-botao wire:click="enviarParte({{ $parte->id }})" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="enviarParte({{ $parte->id }})">Confirmar envio</span>
                            <span wire:loading wire:target="enviarParte({{ $parte->id }})">Enviando…</span>
                        </x-botao>
                    </div>
                </div>
            @endif

            @foreach ($questoesDaParte as $questao)
                @php
                    $dados = $formulario[$questao->id] ?? ['enunciado' => null, 'peso' => '1', 'alternativas' => [], 'blocos' => []];
                    $bloqueado = $parteBloqueada || ! $questao->status->editavelPeloProfessor();
                    $pendenciasDaQuestao = $pendenciasDaParte[$questao->ordem] ?? [];
                @endphp

                @include('questoes.partials.questao', [
                    'questao' => $questao,
                    'dados' => $dados,
                    'bloqueado' => $bloqueado,
                    'pendenciasDaQuestao' => $pendenciasDaQuestao,
                    'linguagens' => $linguagens,
                    'podeEditar' => $podeEditar,
                ])
            @endforeach
        </div>
    @endforeach

    @if ($podeEditar)
        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('solicitacoes.index') }}" wire:navigate>Voltar</x-botao>
            <x-botao variante="secundario" wire:click="salvarTudo" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvarTudo">Salvar rascunho</span>
                <span wire:loading wire:target="salvarTudo">Salvando…</span>
            </x-botao>
        </div>

        <p class="text-right text-xs text-slate-500 dark:text-slate-400">
            O envio é feito por disciplina, no botão de cada bloco acima.
        </p>
    @endif
</div>
