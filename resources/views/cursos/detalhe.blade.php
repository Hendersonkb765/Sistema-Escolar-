<div class="space-y-4">
    <x-cartao titulo="Identificação">
        <x-slot:acoes>
            @can('update', $curso)
                <x-botao variante="secundario" href="{{ route('cursos.editar', $curso) }}" wire:navigate>Editar curso</x-botao>
            @endcan
            @can('create', App\Models\Disciplina::class)
                <x-botao variante="secundario" href="{{ route('disciplinas.criar', ['curso' => $curso->id]) }}" wire:navigate>
                    Nova disciplina
                </x-botao>
            @endcan
            @can('create', App\Models\Turma::class)
                <x-botao href="{{ route('turmas.criar', ['curso' => $curso->id]) }}" wire:navigate>Nova turma</x-botao>
            @endcan
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Eixo</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $curso->eixo->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Código</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $curso->codigo }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Duração</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $curso->duracao_anos }} anos</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Grade vigente</dt>
                <dd class="mt-0.5 text-sm font-medium">
                    @if ($gradeVigente)
                        <a href="{{ route('grades.show', $gradeVigente) }}" wire:navigate
                           class="text-marca-600 hover:underline dark:text-marca-400">v{{ $gradeVigente->versao }}</a>
                    @else
                        <span class="text-slate-400">nenhuma publicada</span>
                    @endif
                </dd>
            </div>
        </dl>
    </x-cartao>

    <x-cartao titulo="Disciplinas por período"
              descricao="Cada disciplina pertence a este curso e é cursada no período indicado.">
        <x-slot:acoes>
            @can('create', App\Models\GradeCurricular::class)
                @if ($confirmandoPublicacao)
                    <x-botao wire:click="publicarGrade">Confirmar publicação</x-botao>
                    <x-botao variante="discreto" wire:click="$set('confirmandoPublicacao', false)">Cancelar</x-botao>
                @else
                    <x-botao :variante="$diferencas['divergente'] ? 'primario' : 'secundario'"
                             wire:click="$set('confirmandoPublicacao', true)">
                        Publicar grade
                    </x-botao>
                @endif
            @endcan
        </x-slot:acoes>

        @if ($gradeVigente === null)
            <x-alerta tipo="atencao" class="mb-4" titulo="Nenhuma grade publicada ainda">
                As turmas congelam uma versão da grade ao serem abertas. Publique a primeira versão
                depois de cadastrar as disciplinas — ou abra uma turma, que o sistema publica a v1
                automaticamente.
            </x-alerta>
        @elseif ($diferencas['divergente'])
            <x-alerta tipo="info" class="mb-4" titulo="Há mudanças ainda não publicadas">
                A grade vigente é a v{{ $gradeVigente->versao }}. O cadastro abaixo já mudou desde então:
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($diferencas['incluidas'] as $disciplina)
                        <li>{{ $disciplina->nome }} foi incluída ({{ $disciplina->periodo }}º período)</li>
                    @endforeach
                    @foreach ($diferencas['removidas'] as $disciplina)
                        <li>{{ $disciplina->nome }} saiu do curso</li>
                    @endforeach
                    @foreach ($diferencas['movidas'] as $mudanca)
                        <li>
                            {{ $mudanca['disciplina']->nome }}:
                            {{ $mudanca['periodo_publicado'] }}º → {{ $mudanca['periodo_atual'] }}º período
                        </li>
                    @endforeach
                </ul>
                Turmas já abertas não são afetadas; publique uma nova versão para que valha nas próximas.
            </x-alerta>
        @endif

        @if ($confirmandoPublicacao)
            <div class="mb-4 space-y-3 rounded-lg border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-500/30 dark:bg-sky-500/5">
                <p class="text-sm text-slate-700 dark:text-slate-200">
                    Publicar cria a <strong>v{{ ($gradeVigente?->versao ?? 0) + 1 }}</strong> com uma foto das
                    disciplinas abaixo. A versão atual passa a "arquivada" e as turmas que a congelaram
                    continuam exatamente como estão.
                </p>
                <x-campo rotulo="Observações (o que mudou)" para="obs-publicacao">
                    <x-input id="obs-publicacao" wire:model="observacoesPublicacao"
                             placeholder="Ex.: Back-end passou para o 2º período"/>
                </x-campo>
            </div>
        @endif

        @if ($porPeriodo->isEmpty())
            <x-vazio titulo="Nenhuma disciplina cadastrada"
                     descricao="Comece cadastrando as disciplinas de cada período do curso.">
                <x-slot:acoes>
                    @can('create', App\Models\Disciplina::class)
                        <x-botao href="{{ route('disciplinas.criar', ['curso' => $curso->id]) }}" wire:navigate>
                            Nova disciplina
                        </x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-{{ min(count($periodos), 3) }}">
                @foreach ($periodos as $periodo)
                    @php $doPeriodo = $porPeriodo->get($periodo, collect()); @endphp
                    <div class="rounded-lg border border-slate-200 dark:border-slate-800">
                        <div class="flex items-baseline justify-between border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $periodo }}º período</h3>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $doPeriodo->count() }} disc. · {{ $doPeriodo->sum('carga_horaria') }}h
                            </span>
                        </div>

                        @if ($doPeriodo->isEmpty())
                            <p class="px-3 py-4 text-center text-xs text-slate-400">Nenhuma disciplina neste período</p>
                        @else
                            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($doPeriodo as $disciplina)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                        @can('update', $disciplina)
                                            <a href="{{ route('disciplinas.editar', $disciplina) }}" wire:navigate
                                               class="min-w-0 truncate text-marca-600 hover:underline dark:text-marca-400">
                                                {{ $disciplina->nome }}
                                            </a>
                                        @else
                                            <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $disciplina->nome }}</span>
                                        @endcan
                                        <span class="shrink-0 text-xs tabular-nums text-slate-400">{{ $disciplina->carga_horaria }}h</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @can('create', App\Models\Disciplina::class)
                            <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                                <a href="{{ route('disciplinas.criar', ['curso' => $curso->id, 'periodo' => $periodo]) }}"
                                   wire:navigate class="text-xs font-medium text-marca-600 hover:underline dark:text-marca-400">
                                    + Adicionar neste período
                                </a>
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
    </x-cartao>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-cartao titulo="Versões da grade"
                  descricao="Cada versão é uma foto do curso no momento em que foi publicada.">
            @forelse ($grades as $grade)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <a href="{{ route('grades.show', $grade) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        v{{ $grade->versao }} · {{ $grade->ano_vigencia }}
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">{{ $grade->turmas_count }} turma(s)</span>
                        <x-badge :cor="$grade->status->cor()" :rotulo="$grade->status->rotulo()"/>
                    </div>
                </div>
            @empty
                <x-vazio titulo="Nenhuma versão publicada"/>
            @endforelse
        </x-cartao>

        <x-cartao titulo="Turmas" :descricao="$turmas->count().' turma(s)'">
            @forelse ($turmas as $turma)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <a href="{{ route('turmas.show', $turma) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        {{ $turma->nome }}
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $turma->periodo }}º período · {{ $turma->periodo_letivo }} · grade v{{ $turma->grade->versao }}
                        </span>
                        <x-badge :cor="$turma->status->cor()" :rotulo="$turma->status->rotulo()"/>
                    </div>
                </div>
            @empty
                <x-vazio titulo="Nenhuma turma cadastrada"/>
            @endforelse
        </x-cartao>
    </div>
</div>
