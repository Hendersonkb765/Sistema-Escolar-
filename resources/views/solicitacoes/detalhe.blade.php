<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @if ($podeResponder)
                <x-botao href="{{ route('solicitacoes.responder', $solicitacao) }}" wire:navigate>Responder questões</x-botao>
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
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Professor</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $solicitacao->professor->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Prazo</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $solicitacao->prazo->format('d/m/Y H:i') }}
                    @if ($solicitacao->enviada_em)
                        <span class="block text-xs font-normal text-slate-400">
                            Enviada em {{ $solicitacao->enviada_em->format('d/m/Y H:i') }}
                        </span>
                    @endif
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

        @if ($solicitacao->enviada_em_atraso)
            <x-alerta tipo="atencao" class="mt-4" titulo="Enviada em atraso">
                O prazo era {{ $solicitacao->prazo->format('d/m/Y H:i') }} e o envio aconteceu em
                {{ $solicitacao->enviada_em->format('d/m/Y H:i') }}. O atraso fica registrado, mas o
                envio foi aceito normalmente.
            </x-alerta>
        @elseif ($solicitacao->estaAtrasada())
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

    <x-cartao titulo="Questões pedidas"
              :descricao="$completas.' de '.$solicitacao->quantidade_questoes.' preenchida(s) · soma dos pesos '.number_format($solicitacao->somaDosPesos(), 2, ',', '.')">
        <div class="space-y-3">
            @foreach ($questoes as $questao)
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ $questao->item->ordem }}
                            </span>
                            <x-badge cor="cinza">peso {{ number_format((float) $questao->peso, 2, ',', '.') }}</x-badge>
                            <x-badge :cor="$questao->status->cor()" :rotulo="$questao->status->rotulo()"/>
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
                </div>
            @endforeach
        </div>
    </x-cartao>
</div>
