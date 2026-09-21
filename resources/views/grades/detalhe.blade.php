<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            <x-botao variante="secundario" href="{{ route('cursos.show', $grade->curso) }}" wire:navigate>
                Abrir curso
            </x-botao>
        </x-slot:acoes>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Curso</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $grade->curso->nome }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Versão</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                    v{{ $grade->versao }}
                    @if ($grade->origem)
                        <span class="text-xs font-normal text-slate-400">de v{{ $grade->origem->versao }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Vigência</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $grade->ano_vigencia }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</dt>
                <dd class="mt-0.5"><x-badge :cor="$grade->status->cor()" :rotulo="$grade->status->rotulo()"/></dd>
            </div>
        </dl>

        @if ($grade->observacoes)
            <p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                {{ $grade->observacoes }}
            </p>
        @endif

        <x-alerta tipo="info" class="mt-4" titulo="Esta página é um registro, não um formulário">
            A grade é a foto do curso no momento em que foi publicada. Para mudar o que as
            próximas turmas vão cursar, edite as disciplinas no curso e publique uma nova versão.
            @if ($grade->emUso())
                <strong>{{ $grade->turmas->count() }} turma(s)</strong> congelaram esta versão e
                continuam exatamente como estão.
            @endif
        </x-alerta>
    </x-cartao>

    <x-cartao titulo="Disciplinas por período" :descricao="$porPeriodo->flatten()->count().' disciplina(s)'">
        @if ($porPeriodo->isEmpty())
            <x-vazio titulo="Grade sem disciplinas"/>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($porPeriodo as $periodo => $itens)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-800">
                        <div class="border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $periodo }}º período</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $itens->count() }} disciplina(s) · {{ $itens->sum('carga_horaria') }}h
                            </p>
                        </div>
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($itens->sortBy('disciplina.nome') as $item)
                                <li class="flex items-center justify-between px-3 py-2 text-sm">
                                    <span class="text-slate-700 dark:text-slate-200">{{ $item->disciplina->nome }}</span>
                                    <span class="text-xs tabular-nums text-slate-400">{{ $item->carga_horaria }}h</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-cartao>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-cartao titulo="Turmas nesta versão">
            @forelse ($grade->turmas->sortBy('nome') as $turma)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <a href="{{ route('turmas.show', $turma) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        {{ $turma->nome }}
                    </a>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $turma->periodo }}º período · {{ $turma->periodo_letivo }}
                    </span>
                </div>
            @empty
                <x-vazio titulo="Nenhuma turma usa esta versão"/>
            @endforelse
        </x-cartao>

        <x-cartao titulo="Outras versões deste curso"
                  descricao="Nenhuma é apagada — o percurso de cada turma fica preservado.">
            @forelse ($outrasVersoes as $outra)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <a href="{{ route('grades.show', $outra) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        v{{ $outra->versao }} · {{ $outra->ano_vigencia }}
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">{{ $outra->turmas_count }} turma(s)</span>
                        <x-badge :cor="$outra->status->cor()" :rotulo="$outra->status->rotulo()"/>
                    </div>
                </div>
            @empty
                <x-vazio titulo="Esta é a única versão"/>
            @endforelse
        </x-cartao>
    </div>
</div>
