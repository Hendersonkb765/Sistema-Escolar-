<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes>
            @can('update', $grade)
                <x-botao variante="secundario" href="{{ route('grades.editar', $grade) }}" wire:navigate>Editar</x-botao>
            @endcan

            @can('publicar', $grade)
                @if ($confirmandoPublicacao)
                    <x-botao wire:click="publicar">Confirmar publicação</x-botao>
                    <x-botao variante="discreto" wire:click="$set('confirmandoPublicacao', false)">Cancelar</x-botao>
                @else
                    <x-botao wire:click="$set('confirmandoPublicacao', true)">Publicar versão</x-botao>
                @endif
            @endcan

            @can('novaVersao', $grade)
                <x-botao variante="secundario" wire:click="criarNovaVersao"
                         wire:confirm="Isto cria a próxima versão em rascunho, copiando as disciplinas. A versão atual e as turmas que a usam não são alteradas.">
                    Criar nova versão
                </x-botao>
            @endcan
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

        @if ($grade->emUso())
            <x-alerta tipo="atencao" class="mt-4" titulo="Versão congelada em {{ $grade->turmas->count() }} turma(s)">
                Esta versão não pode mais ser editada. Para mudar a grade, crie uma nova versão —
                as turmas listadas continuam exatamente como estão.
            </x-alerta>
        @endif

        @if ($confirmandoPublicacao)
            <x-alerta tipo="info" class="mt-4" titulo="Publicar a versão {{ $grade->versao }}?">
                A versão vigente atual passa a "arquivada" e esta entra em vigência para
                <strong>novas</strong> turmas. Turmas já abertas mantêm a versão que congelaram.
            </x-alerta>
        @endif
    </x-cartao>

    <x-cartao titulo="Disciplinas por ano" :descricao="$grade->disciplinas->count().' disciplina(s)'">
        @if ($porAno->isEmpty())
            <x-vazio titulo="Grade sem disciplinas"
                     descricao="Uma grade vazia não pode entrar em vigência."/>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($porAno as $ano => $itens)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-800">
                        <div class="border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $ano }}º ano</h3>
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
            @forelse ($grade->turmas->sortBy('identificacao') as $turma)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0 dark:border-slate-800">
                    <a href="{{ route('turmas.show', $turma) }}" wire:navigate
                       class="text-sm font-medium text-marca-600 hover:underline dark:text-marca-400">
                        {{ $turma->identificacao }}
                    </a>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $turma->ano_curso }}º ano · {{ $turma->periodo_letivo }}
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
