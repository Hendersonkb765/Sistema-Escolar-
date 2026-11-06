<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @can('delete', $turma)
                <x-botao variante="discreto" class="text-rose-600 dark:text-rose-400" wire:click="pedirExclusao('turma', {{ $turma->id }})">Excluir turma</x-botao>
            @endcan
            @can('update', $turma)
                <x-botao variante="secundario" href="{{ route('turmas.editar', $turma) }}" wire:navigate>Editar</x-botao>
                <x-botao variante="secundario" href="{{ route('turmas.horarios', $turma) }}" wire:navigate>Quadro de horários</x-botao>
                <x-botao variante="secundario" href="{{ route('turmas.calendario', $turma) }}" wire:navigate>Calendário e intervalos</x-botao>
            @endcan
            @can('create', App\Models\Aluno::class)
                <x-botao variante="secundario" href="{{ route('alunos.criar', ['turma' => $turma->id]) }}" wire:navigate>
                    Matricular aluno
                </x-botao>
            @endcan
            @can('avancarAno', $turma)
                @if ($turma->podeAvancar())
                    <x-botao wire:click="abrirPainelAvanco">Avançar ano</x-botao>
                @endif
            @endcan
            {{--
                No último período não há para onde avançar, e o botão
                some. Antes sumia sem dizer nada: quem chegava ao 2º de
                um curso de dois períodos não encontrava mais o avanço e
                nem a razão. Agora o lugar dele é ocupado pelo fim do
                percurso.
            --}}
            @can('encerrar', $turma)
                @if ($turma->podeConcluir())
                    <x-botao wire:click="abrirPainelConclusao">Concluir turma</x-botao>
                @endif
            @endcan
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Curso</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $turma->curso->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Período</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $turma->periodo }}º de {{ $turma->periodoFinal() }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Grade congelada</dt>
                <dd class="mt-0.5">
                    <a href="{{ route('grades.show', $turma->grade) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        v{{ $turma->grade->versao }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Período</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $turma->periodo_letivo }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</dt>
                <dd class="mt-0.5"><x-badge :cor="$turma->status->cor()" :rotulo="$turma->status->rotulo()"/></dd>
            </div>
        </dl>

        {{--
            Turma fora de circulação diz por quê, e o que isso significa
            na prática. Sem esta linha, a tela de uma turma concluída é
            igual à de uma ativa, menos dois botões que sumiram.
        --}}
        @if (! $turma->podeAvancar() && ! $turma->podeConcluir())
            <x-alerta tipo="info" class="mt-4"
                      titulo="Turma {{ $turma->status->rotulo() }}">
                O percurso desta turma terminou: ela não avança mais de período e não aparece
                como opção ao abrir uma solicitação de prova. As notas, o histórico e os alunos
                continuam aqui.
            </x-alerta>
        @endif

        @if ($gradeDesatualizada)
            <x-alerta tipo="info" class="mt-4" titulo="Há uma versão de grade mais recente (v{{ $gradeDesatualizada->versao }})">
                Esta turma segue na v{{ $turma->grade->versao }}, congelada quando ela foi aberta —
                é assim de propósito. A troca, se for mesmo o caso, é uma decisão manual e registrada.
            </x-alerta>
        @endif
    </x-cartao>

    @if ($painelAvancoAberto)
        <x-cartao titulo="Avançar para o {{ $turma->periodo + 1 }}º período"
                  descricao="O estado atual da turma é gravado no histórico antes de qualquer mudança. Nada é apagado.">
            <div class="space-y-4">
                <x-campo rotulo="Como avançar" para="turma-destino">
                    <x-select id="turma-destino" wire:model.live="turmaDestinoId">
                        <option value="" @selected((string) $turmaDestinoId === '')>Promover esta turma ({{ $turma->nome }} passa ao {{ $turma->periodo + 1 }}º período)</option>
                        @foreach ($destinos as $destino)
                            <option value="{{ $destino->id }}" @selected((string) $turmaDestinoId === (string) ($destino->id))>Mover alunos para {{ $destino->nome }} ({{ $destino->periodo_letivo }})</option>
                        @endforeach
                    </x-select>
                </x-campo>

                @if ($turmaDestinoId === '')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-campo rotulo="Novo nome da turma" para="novo-nome"
                                 ajuda="Sugerida a partir da atual.">
                            <x-input id="novo-nome" wire:model="novoNome"/>
                        </x-campo>

                        <x-campo rotulo="Período letivo" para="novo-periodo">
                            <x-input id="novo-periodo" wire:model="novoPeriodoLetivo"/>
                        </x-campo>
                    </div>
                @else
                    <x-alerta tipo="atencao">
                        Os {{ $alunos->where('status', App\Enums\StatusAluno::Ativo)->count() }} aluno(s) ativo(s) serão
                        movidos, cada um com registro em seu histórico, e esta turma passa a "Concluída".
                    </x-alerta>
                @endif

                <x-campo rotulo="Observações" para="observacoes-avanco">
                    <x-area-texto id="observacoes-avanco" wire:model="observacoesAvanco" :linhas="2"/>
                </x-campo>

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="fecharPainelAvanco">Cancelar</x-botao>
                    <x-botao wire:click="avancarAno" wire:loading.attr="disabled">Confirmar avanço</x-botao>
                </div>
            </div>
        </x-cartao>
    @endif

    @if ($painelConclusaoAberto)
        <x-cartao titulo="Concluir a turma {{ $turma->nome }}"
                  descricao="Último período do curso. O estado atual vai para o histórico antes de qualquer mudança. Nada é apagado.">
            <div class="space-y-4">
                <x-alerta tipo="atencao">
                    @if ($alunosAtivos > 0)
                        {{ $alunosAtivos }} aluno(s) ativo(s) passam a <strong>Concluinte</strong>, cada um com
                        registro em seu histórico. Quem já está transferido ou inativo fica como está.
                    @else
                        Nenhum aluno ativo nesta turma: só o estado da turma muda.
                    @endif
                    A turma passa a <strong>Concluída</strong> e deixa de aparecer ao abrir
                    uma solicitação de prova.
                </x-alerta>

                <x-campo rotulo="Observações" para="observacoes-conclusao"
                         ajuda="Fica no histórico da turma, junto do registro de encerramento.">
                    <x-area-texto id="observacoes-conclusao" wire:model="observacoesConclusao" :linhas="2"/>
                </x-campo>

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="fecharPainelConclusao">Cancelar</x-botao>
                    <x-botao wire:click="concluirTurma" wire:loading.attr="disabled">Confirmar conclusão</x-botao>
                </div>
            </div>
        </x-cartao>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-cartao titulo="Disciplinas do {{ $turma->periodo }}º período"
                  :descricao="'Pela grade v'.$turma->grade->versao.' · '.$disciplinasDoPeriodo->sum('carga_horaria').'h'">
            @forelse ($disciplinasDoPeriodo->sortBy('disciplina.nome') as $item)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ $item->disciplina->nome }}</span>
                    <span class="text-xs tabular-nums text-slate-400">{{ $item->carga_horaria }}h</span>
                </div>
            @empty
                <x-vazio titulo="Nenhuma disciplina neste ano"
                         descricao="A grade congelada não define disciplinas para o {{ $turma->periodo }}º período."/>
            @endforelse
        </x-cartao>

        @php
            // Matriculados e ativos são números diferentes assim que
            // alguém sai, e é o segundo que responde "quantos alunos
            // esta turma tem hoje".
            $ativos = $alunos->where('status', App\Enums\StatusAluno::Ativo)->count();
        @endphp

        <x-cartao titulo="Alunos"
                  :descricao="$alunos->count().' matriculado(s)'
                      .($ativos === $alunos->count() ? '' : ' · '.$ativos.' ativo(s)')">
            @forelse ($alunos as $aluno)
                <div wire:key="aluno-{{ $aluno->id }}"
                     class="border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('alunos.editar', $aluno) }}" wire:navigate
                               class="block truncate text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                                {{ $aluno->nome }}
                            </a>
                            <span class="text-xs text-slate-400">RA {{ $aluno->ra }}</span>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <x-badge :cor="$aluno->status->cor()" :rotulo="$aluno->status->rotulo()"/>

                            {{--
                                Quem já saiu não sai de novo: o botão
                                aparece só para quem está ativo, e o selo
                                ao lado diz o que aconteceu com os outros.
                            --}}
                            @can('desligar', $aluno)
                                @if ($aluno->podeSerDesligado() && $alunoDesligando !== $aluno->id)
                                    <x-botao variante="discreto" wire:click="abrirDesligamento({{ $aluno->id }})">
                                        Retirar
                                    </x-botao>
                                @endif
                            @endcan
                        </div>
                    </div>

                    @if ($alunoDesligando === $aluno->id)
                        <div class="mt-2 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Retirar {{ $aluno->nome }} da turma. O cadastro, as notas e o histórico
                                continuam; ele(a) deixa de ser contado(a) como ativo(a) e não entra em prova nova.
                            </p>

                            <x-campo rotulo="Para onde está saindo" para="saida-{{ $aluno->id }}">
                                <x-select id="saida-{{ $aluno->id }}" wire:model.live="situacaoDesligamento">
                                    @foreach ($situacoesDeSaida as $situacao)
                                        <option value="{{ $situacao->value }}" @selected((string) $situacaoDesligamento === (string) ($situacao->value))>
                                            {{ $situacao->rotulo() }} — {{ $situacao->descricao() }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-campo>

                            {{--
                                Obrigatório: "Transferido" responde para
                                onde, não por quê, e é o porquê que alguém
                                vai procurar no histórico daqui a um ano.
                            --}}
                            <x-campo rotulo="Motivo" para="motivo-{{ $aluno->id }}"
                                     ajuda="Fica no histórico do aluno, que não é reescrito.">
                                <x-input id="motivo-{{ $aluno->id }}" wire:model="motivoDesligamento"
                                         placeholder="Transferência para a E.E. Central"/>
                            </x-campo>

                            <div class="flex justify-end gap-2">
                                <x-botao variante="secundario" wire:click="fecharDesligamento">Cancelar</x-botao>
                                <x-botao wire:click="desligarAluno" wire:loading.attr="disabled">
                                    Confirmar saída
                                </x-botao>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-vazio titulo="Nenhum aluno matriculado"/>
            @endforelse
        </x-cartao>
    </div>

    <x-cartao titulo="Histórico da turma"
              descricao="Registro append-only: cada mudança grava o estado que existia antes dela.">
        @if ($historicos->isEmpty())
            <x-vazio titulo="Sem registros"/>
        @else
            <ol class="space-y-3">
                @foreach ($historicos as $registro)
                    <li class="flex gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-slate-800">
                        <div class="mt-1 shrink-0">
                            <x-badge :cor="$registro->evento->cor()" :rotulo="$registro->evento->rotulo()"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700 dark:text-slate-200">
                                Estado anterior: {{ $registro->nome }} ·
                                {{ $registro->periodo }}º período ·
                                grade v{{ $registro->grade?->versao ?? '—' }} ·
                                {{ $registro->periodo_letivo }}
                            </p>
                            @if ($registro->observacoes)
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $registro->observacoes }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $registro->created_at?->format('d/m/Y H:i') }}
                                @if ($registro->registradoPor) · {{ $registro->registradoPor->nome }} @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-cartao>

    <x-confirmacao-de-exclusao :exclusao="$exclusao"/>
</div>
