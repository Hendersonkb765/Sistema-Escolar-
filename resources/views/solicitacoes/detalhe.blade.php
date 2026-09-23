<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeResponder)
                <x-botao href="{{ route('solicitacoes.responder', $solicitacao) }}" wire:navigate>Responder questões</x-botao>
            @endif

            @if ($resumo['aguardando'] > 0 && auth()->user()->ehGestao())
                <x-botao wire:click="aprovarPendentes"
                         wire:confirm="Aprovar as {{ $resumo['aguardando'] }} questão(ões) que ainda aguardam análise?">
                    Aprovar {{ $resumo['aguardando'] }} pendente(s)
                </x-botao>
            @endif

            @can('encerrar', $solicitacao)
                @if ($solicitacao->aceitaEnvio())
                    <x-botao variante="secundario" wire:click="confirmar('encerrar')">Encerrar</x-botao>
                    <x-botao variante="discreto" wire:click="confirmar('cancelar')"
                             class="text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                        Cancelar solicitação
                    </x-botao>
                @else
                    <x-botao variante="secundario" wire:click="confirmar('reabrir')">Reabrir</x-botao>
                @endif
            @endcan
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Turma</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->turma->nome }}
                    <span class="block text-xs font-normal text-slate-400">{{ $solicitacao->turma->curso->nome }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Disciplinas</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $partes->count() }}
                    <span class="block text-xs font-normal text-slate-400">
                        {{ $solicitacao->totalDeQuestoes() }} questão(ões) no total
                    </span>
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
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Observações</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $solicitacao->observacoes }}</p>
            </div>
        @endif

        @if ($solicitacao->estaAtrasada())
            <x-alerta tipo="atencao" class="mt-4" titulo="Prazo vencido">
                O prazo era {{ $solicitacao->prazo->format('d/m/Y H:i') }}. A solicitação continua
                aceitando questões — só o encerramento manual bloqueia o envio.
            </x-alerta>
        @endif

        @if ($acaoConfirmando !== '')
            <div class="mt-4 space-y-3 rounded-lg border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
                <p class="text-sm text-slate-700 dark:text-slate-200">
                    @if ($acaoConfirmando === 'encerrar')
                        Encerrar fecha o envio: o professor não poderá mais enviar nem alterar questões.
                    @elseif ($acaoConfirmando === 'cancelar')
                        Cancelar encerra a solicitação sem aproveitar as questões. Nada é apagado.
                    @else
                        Reabrir devolve a solicitação para envio.
                    @endif
                </p>

                <x-campo rotulo="Motivo (opcional)" para="motivo">
                    <x-input id="motivo" wire:model="motivo"/>
                </x-campo>

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="cancelarConfirmacao">Voltar</x-botao>
                    <x-botao :variante="$acaoConfirmando === 'cancelar' ? 'perigo' : 'primario'"
                             wire:click="executarAcao">Confirmar</x-botao>
                </div>
            </div>
        @endif
    </x-cartao>

    @foreach ($partes as $parte)
        @php $questoesDaParte = $questoesPorParte[$parte->id] ?? collect(); @endphp

        <x-cartao wire:key="parte-{{ $parte->id }}">
            <x-slot:titulo>{{ $parte->disciplina->nome }}</x-slot:titulo>
            <x-slot:descricao>
                {{ $parte->professor->nome }} · {{ $parte->quantidade_questoes }} questão(ões) pedida(s)
                @if ($parte->observacoes) · {{ $parte->observacoes }} @endif
            </x-slot:descricao>

            <x-slot:acoes>
                <x-badge :cor="$parte->status->cor()" :rotulo="$parte->status->rotulo()"/>
                @if ($rotuloParte = $parte->rotuloDePrazo())
                    <x-badge cor="vermelho" :rotulo="$rotuloParte"/>
                @endif
            </x-slot:acoes>

            @if ($parte->enviada_em)
                <p class="-mt-1 mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Entregue em {{ $parte->enviada_em->format('d/m/Y H:i') }}.
                </p>
            @endif

            <div class="space-y-3">
                @forelse ($questoesDaParte as $questao)
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $questao->ordem }}
                                </span>
                                <x-badge cor="cinza">peso {{ number_format((float) $questao->peso, 2, ',', '.') }}</x-badge>
                                <x-badge :cor="$questao->status->cor()" :rotulo="$questao->status->rotulo()"/>
                                @if ($questao->versao > 1)
                                    <x-badge cor="cinza">v{{ $questao->versao }}</x-badge>
                                @endif
                            </div>
                            @if ($questao->estaCompleta($solicitacao->quantidade_alternativas))
                                <x-badge cor="verde">Completa</x-badge>
                            @else
                                <x-badge cor="amarelo">Pendente</x-badge>
                            @endif
                        </div>

                        @if ($questao->enunciado)
                            <div class="mt-2">
                                <x-enunciado :questao="$questao"/>
                            </div>

                            <ul class="mt-2 space-y-1">
                                @foreach ($questao->alternativas->sortBy('letra') as $alternativa)
                                    <li class="flex gap-2 text-sm">
                                        <span @class([
                                            'font-medium',
                                            'text-emerald-600 dark:text-emerald-400' => $alternativa->correta,
                                            'text-slate-400' => ! $alternativa->correta,
                                        ])>{{ $alternativa->letra }})</span>
                                        <span class="text-slate-600 dark:text-slate-300">{{ $alternativa->texto ?: '—' }}</span>
                                        @if ($alternativa->correta)
                                            <x-badge cor="verde">correta</x-badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-slate-400">Ainda não preenchida.</p>
                        @endif

                        @if ($questao->feedbacks->isNotEmpty())
                            <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-900/50">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    Histórico da análise
                                </p>
                                @foreach ($questao->feedbacks as $feedback)
                                    <div class="mt-1.5 text-sm">
                                        <x-badge :cor="$feedback->acao->cor()" :rotulo="$feedback->acao->rotulo()"/>
                                        <span class="text-xs text-slate-400">v{{ $feedback->versao_questao }}</span>
                                        @if ($feedback->comentario)
                                            <span class="text-slate-600 dark:text-slate-300">— {{ $feedback->comentario }}</span>
                                        @endif
                                        <span class="block text-xs text-slate-400">
                                            {{ $feedback->analisadoPor->nome }} · {{ $feedback->created_at?->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @can('analisar', $questao)
                            @if ($questao->status->analisavel())
                                @if ($devolvendoQuestao === $questao->id)
                                    <div class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-500/30 dark:bg-amber-500/5">
                                        <x-campo rotulo="O que precisa ser corrigido?" :para="'motivo-'.$questao->id" obrigatorio
                                                 ajuda="O professor vê exatamente este texto.">
                                            <x-area-texto id="motivo-{{ $questao->id }}" :linhas="2"
                                                          wire:model="comentarioDaDevolucao"
                                                          placeholder="Ex.: a alternativa C está ambígua; reescreva deixando uma única leitura possível."/>
                                        </x-campo>

                                        <div class="flex justify-end gap-2">
                                            <x-botao variante="secundario" wire:click="fecharDevolucao">Cancelar</x-botao>
                                            <x-botao variante="perigo" wire:click="devolverQuestao">Devolver para correção</x-botao>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-3 flex flex-wrap items-end justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                        <x-campo rotulo="Comentário na aprovação (opcional)" class="min-w-0 flex-1">
                                            <x-input wire:model="comentarioDaAprovacao.{{ $questao->id }}"
                                                     placeholder="Ex.: ótima cobertura do conteúdo"/>
                                        </x-campo>

                                        <div class="flex shrink-0 gap-2">
                                            <x-botao variante="secundario" wire:click="abrirDevolucao({{ $questao->id }})">
                                                Devolver
                                            </x-botao>
                                            <x-botao wire:click="aprovarQuestao({{ $questao->id }})">Aprovar</x-botao>
                                        </div>
                                    </div>
                                @endif
                            @elseif ($questao->status === App\Enums\StatusQuestao::Aprovada)
                                <p class="mt-3 border-t border-slate-100 pt-2 text-xs text-emerald-600 dark:border-slate-800 dark:text-emerald-400">
                                    Aprovada — já pode entrar em uma prova.
                                </p>
                            @elseif ($questao->status === App\Enums\StatusQuestao::Rejeitada)
                                <p class="mt-3 border-t border-slate-100 pt-2 text-xs text-amber-600 dark:border-slate-800 dark:text-amber-400">
                                    Devolvida — aguardando a correção do professor.
                                </p>
                            @endif
                        @elseif (auth()->user()->ehGestao() && $questao->status->analisavel())
                            <p class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-400 dark:border-slate-800">
                                Você escreveu esta questão, então a análise cabe a outra pessoa da coordenação.
                            </p>
                        @endcan
                    </div>
                @empty
                    <x-vazio titulo="Nenhuma questão nesta disciplina"/>
                @endforelse
            </div>
        </x-cartao>
    @endforeach
</div>
