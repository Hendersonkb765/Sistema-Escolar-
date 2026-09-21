<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @can('update', $turma)
                <x-botao variante="secundario" href="{{ route('turmas.editar', $turma) }}" wire:navigate>Editar</x-botao>
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
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Curso</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $turma->curso->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Ano</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $turma->ano_curso }}º de {{ $turma->anoFinal() }}
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

        @if ($gradeDesatualizada)
            <x-alerta tipo="info" class="mt-4" titulo="Há uma versão de grade mais recente (v{{ $gradeDesatualizada->versao }})">
                Esta turma segue na v{{ $turma->grade->versao }}, congelada quando ela foi aberta —
                é assim de propósito. A troca, se for mesmo o caso, é uma decisão manual e registrada.
            </x-alerta>
        @endif
    </x-cartao>

    @if ($painelAvancoAberto)
        <x-cartao titulo="Avançar para o {{ $turma->ano_curso + 1 }}º ano"
                  descricao="O estado atual da turma é gravado no histórico antes de qualquer mudança. Nada é apagado.">
            <div class="space-y-4">
                <x-campo rotulo="Como avançar" para="turma-destino">
                    <x-select id="turma-destino" wire:model.live="turmaDestinoId">
                        <option value="">Promover esta turma ({{ $turma->identificacao }} passa ao {{ $turma->ano_curso + 1 }}º ano)</option>
                        @foreach ($destinos as $destino)
                            <option value="{{ $destino->id }}">Mover alunos para {{ $destino->identificacao }} ({{ $destino->periodo_letivo }})</option>
                        @endforeach
                    </x-select>
                </x-campo>

                @if ($turmaDestinoId === '')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-campo rotulo="Nova identificação" para="nova-identificacao"
                                 ajuda="Sugerida a partir da atual.">
                            <x-input id="nova-identificacao" wire:model="novaIdentificacao"/>
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
                    <textarea id="observacoes-avanco" wire:model="observacoesAvanco" rows="2"
                              class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></textarea>
                </x-campo>

                <div class="flex justify-end gap-2">
                    <x-botao variante="secundario" wire:click="fecharPainelAvanco">Cancelar</x-botao>
                    <x-botao wire:click="avancarAno" wire:loading.attr="disabled">Confirmar avanço</x-botao>
                </div>
            </div>
        </x-cartao>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-cartao titulo="Disciplinas do {{ $turma->ano_curso }}º ano"
                  :descricao="'Pela grade v'.$turma->grade->versao.' · '.$disciplinasDoAno->sum('carga_horaria').'h'">
            @forelse ($disciplinasDoAno->sortBy('disciplina.nome') as $item)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ $item->disciplina->nome }}</span>
                    <span class="text-xs tabular-nums text-slate-400">{{ $item->carga_horaria }}h</span>
                </div>
            @empty
                <x-vazio titulo="Nenhuma disciplina neste ano"
                         descricao="A grade congelada não define disciplinas para o {{ $turma->ano_curso }}º ano."/>
            @endforelse
        </x-cartao>

        <x-cartao titulo="Alunos" :descricao="$alunos->count().' matriculado(s)'">
            @forelse ($alunos as $aluno)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <div class="min-w-0">
                        <a href="{{ route('alunos.editar', $aluno) }}" wire:navigate
                           class="block truncate text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                            {{ $aluno->nome }}
                        </a>
                        <span class="text-xs text-slate-400">{{ $aluno->matricula }}</span>
                    </div>
                    <x-badge :cor="$aluno->status->cor()" :rotulo="$aluno->status->rotulo()"/>
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
                                Estado anterior: {{ $registro->identificacao }} ·
                                {{ $registro->ano_curso }}º ano ·
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
</div>
