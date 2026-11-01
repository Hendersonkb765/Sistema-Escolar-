<div class="space-y-6">
    @if ($usuario->ehGestao() && count($usuario->eixoIds()) === 0)
        <x-alerta tipo="atencao" titulo="Sua conta ainda não tem Eixo vinculado">
            Sem vínculo de Eixo, nenhum curso, turma ou avaliação fica visível. Peça a um PAEET Admin
            que vincule sua conta aos Eixos sob sua responsabilidade.
        </x-alerta>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($indicadores as $indicador)
            <x-indicador
                :rotulo="$indicador['rotulo']"
                :valor="$indicador['valor']"
                :cor="$indicador['cor']"
                :href="$indicador['href']"
                :detalhe="$indicador['detalhe'] ?? null"/>
        @endforeach
    </div>

    @if ($acessoRapido)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-cartao titulo="Minha semana" descricao="Suas aulas no quadro de horários das turmas.">
                <x-slot:acoes>
                    <x-botao variante="secundario" href="{{ route('horarios.meus') }}" wire:navigate>Abrir</x-botao>
                </x-slot:acoes>

                <p class="text-sm text-slate-700 dark:text-slate-200">
                    {{ $acessoRapido['aulasHoje'] === 0 ? 'Nenhuma aula sua hoje.' : $acessoRapido['aulasHoje'].' aula(s) sua(s) hoje.' }}
                </p>
            </x-cartao>

            <x-cartao titulo="Minhas ausências" descricao="Avise a coordenação com antecedência.">
                <x-slot:acoes>
                    <x-botao variante="secundario" href="{{ route('ausencias.index') }}" wire:navigate>Abrir</x-botao>
                    <x-botao href="{{ route('ausencias.criar') }}" wire:navigate>Informar ausência</x-botao>
                </x-slot:acoes>

                @if ($acessoRapido['proximaAusencia'])
                    <p class="flex flex-wrap items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        Próxima:
                        <a href="{{ route('ausencias.show', $acessoRapido['proximaAusencia']) }}" wire:navigate
                           class="font-medium hover:underline">{{ $acessoRapido['proximaAusencia']->rotuloDoDia() }}</a>
                        <x-badge :cor="$acessoRapido['proximaAusencia']->status->cor()">{{ $acessoRapido['proximaAusencia']->status->rotulo() }}</x-badge>
                    </p>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">Nenhuma ausência marcada.</p>
                @endif
            </x-cartao>
        </div>
    @endif

    {{--
        O número diz quantos; a lista diz quais. Sem ela, "3 atrasadas"
        custa três telas até chegar na primeira, e o painel vira um
        lugar por onde se passa em vez de onde se começa.
    --}}
    @if ($pendencias->isNotEmpty())
        <x-cartao titulo="{{ $usuario->ehProfessor() ? 'Esperando você' : 'Esperando alguém' }}">
            <x-slot:descricao>
                @if ($usuario->ehProfessor())
                    As disciplinas que foram pedidas a você e ainda não foram enviadas,
                    da mais urgente para a menos. Prazo vencido não bloqueia o envio.
                @else
                    As solicitações com parte pendente, da mais urgente para a menos.
                @endif
            </x-slot:descricao>

            <x-slot:acoes>
                <x-botao variante="discreto" href="{{ route('solicitacoes.index', ['prazo' => 'pendentes']) }}"
                         wire:navigate>Ver todas</x-botao>
            </x-slot:acoes>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pendencias as $item)
                    <li wire:key="pendencia-{{ $item['id'] }}">
                        <a href="{{ $item['href'] }}" wire:navigate
                           class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 transition hover:bg-slate-50 sm:px-6 dark:hover:bg-slate-800/50">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-slate-900 dark:text-slate-100">
                                    {{ $item['titulo'] }}
                                </span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $item['turma'] }}
                                    @if ($item['faltam'] > 0)
                                        · {{ $item['faltam'] }}
                                        {{ $usuario->ehProfessor() ? 'questão(ões) a preencher' : 'disciplina(s) a enviar' }}
                                    @endif
                                </span>
                            </span>

                            {{--
                                O prazo em texto, e não só a cor: atraso é
                                estado de prazo, e a data é o que diz de
                                quanto ele é.
                            --}}
                            <span class="shrink-0 text-right">
                                <span @class([
                                    'block text-sm tabular-nums',
                                    'font-semibold text-rose-700 dark:text-rose-400' => $item['atrasada'],
                                    'text-slate-700 dark:text-slate-200' => ! $item['atrasada'],
                                ])>{{ $item['prazo']->format('d/m/Y') }}</span>
                                <span @class([
                                    'block text-xs',
                                    'text-rose-600 dark:text-rose-400' => $item['atrasada'],
                                    'text-slate-400' => ! $item['atrasada'],
                                ])>
                                    {{ $item['atrasada'] ? 'Atrasada' : 'Vence '.$item['prazo']->diffForHumans() }}
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-cartao>
    @endif

    <x-cartao titulo="Bem-vindo, {{ $usuario->primeiroNome() }}"
              descricao="Perfil: {{ $usuario->perfil->rotulo() }}">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            @if ($usuario->ehProfessor())
                Aqui você acompanha as solicitações de questões recebidas e seus prazos. Um prazo
                vencido não impede o envio — a solicitação apenas fica marcada como atrasada, e o
                envio continua sendo aceito.
            @else
                Use o menu lateral para gerenciar a estrutura acadêmica, solicitar questões aos
                professores, analisar o que foi enviado, montar provas e importar resultados.
            @endif
        </p>

        {{--
            Os números acima são todos recortados pelo escopo de quem
            olha: o PAEET conta o que é do Eixo dele, o professor conta o
            que é dele. Dizer isso aqui evita o chamado de "faltam
            turmas no painel".
        --}}
        @if ($estrutura !== [])
            {{--
                Referência, não pendência: ficava na mesma fileira dos
                alertas, e "Disciplinas: 8" não compete por atenção com
                "3 solicitações atrasadas" — só empurra a segunda para
                longe do olho.
            --}}
            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">A estrutura do Eixo</p>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ($estrutura as $item)
                        <a @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @endif
                           class="text-sm text-slate-500 hover:text-marca-600 dark:text-slate-400 dark:hover:text-marca-400">
                            <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ $item['valor'] }}</span>
                            {{ $item['rotulo'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
            @if ($usuario->ehProfessor())
                Os números acima contam apenas as suas solicitações e questões.
            @else
                Os números acima contam apenas o que pertence
                {{ count($usuario->eixoIds()) === 1 ? 'ao seu Eixo' : 'aos seus Eixos' }}.
            @endif
        </p>
    </x-cartao>
</div>
