<div class="space-y-4">
    <x-cartao titulo="Identificação">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
        </dl>
    </x-cartao>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-cartao titulo="Grades curriculares" :descricao="$curso->grades->count().' versão(ões)'">
            @forelse ($curso->grades->sortByDesc('versao') as $grade)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Versão {{ $grade->versao }} · {{ $grade->ano_vigencia }}</span>
                    <x-badge :cor="$grade->status->cor()" :rotulo="$grade->status->rotulo()"/>
                </div>
            @empty
                <x-vazio titulo="Nenhuma grade cadastrada"/>
            @endforelse
        </x-cartao>

        <x-cartao titulo="Turmas" :descricao="$curso->turmas->count().' turma(s)'">
            @forelse ($curso->turmas->sortBy('identificacao') as $turma)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <span class="text-sm text-slate-700 dark:text-slate-200">
                        {{ $turma->identificacao }} · {{ $turma->periodo_letivo }}
                    </span>
                    <x-badge :cor="$turma->status->cor()" :rotulo="$turma->status->rotulo()"/>
                </div>
            @empty
                <x-vazio titulo="Nenhuma turma cadastrada"/>
            @endforelse
        </x-cartao>
    </div>
</div>
