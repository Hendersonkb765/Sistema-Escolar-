<div class="space-y-4">
    <x-cartao titulo="Recorte">
        @if (auth()->user()->can('importar', App\Models\EscopoAula::class))
            <x-slot:acoes>
                <x-botao variante="secundario" href="{{ route('escopo.importar') }}" wire:navigate>
                    Importar escopo
                </x-botao>
            </x-slot:acoes>
        @endif

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
            {{--
                `@selected` em cada opção: o estado do componente não
                chega ao HTML sozinho. Sem ele, abrir um link com filtro
                no endereço mostrava a tela filtrada e o select dizendo
                "Escolha um curso" — o controle mentindo sobre o que a
                página está mostrando.
            --}}
            <x-campo rotulo="Curso" para="filtro-curso">
                <x-select id="filtro-curso" wire:model.live="filtroCurso" :desabilitado="$cursos->isEmpty()">
                    <option value="">
                        {{ $cursos->isEmpty() ? 'Nenhum curso com escopo' : 'Escolha um curso' }}
                    </option>
                    @foreach ($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected((string) $curso->id === $filtroCurso)>{{ $curso->nome }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Período" para="filtro-periodo">
                <x-select id="filtro-periodo" wire:model.live="filtroPeriodo" :desabilitado="$periodos->isEmpty()">
                    <option value="">Todos</option>
                    @foreach ($periodos as $periodo)
                        <option value="{{ $periodo }}" @selected((string) $periodo === $filtroPeriodo)>{{ $periodo }}º período</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Bimestre" para="filtro-bimestre">
                <x-select id="filtro-bimestre" wire:model.live="filtroBimestre" :desabilitado="$bimestres->isEmpty()">
                    <option value="">Todos</option>
                    @foreach ($bimestres as $bimestre)
                        <option value="{{ $bimestre }}" @selected((string) $bimestre === $filtroBimestre)>{{ $bimestre }}º bimestre</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Componente" para="filtro-componente">
                <x-select id="filtro-componente" wire:model.live="filtroComponente" :desabilitado="$componentes->isEmpty()">
                    <option value="">Todos</option>
                    @foreach ($componentes as $nome => $rotulo)
                        <option value="{{ $nome }}" @selected($nome === $filtroComponente)>{{ $rotulo }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <div class="sm:col-span-2">
                <x-campo rotulo="Buscar na aula" para="busca"
                         ajuda="Procura no título, no tema da semana, no objeto de conhecimento e nas habilidades.">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="função, endereçamento IP…"/>
                </x-campo>
            </div>

            {{--
                O professor abre já nos componentes dele: é o que veio
                ver. Mas a semana do colega não é segredo — é o que a
                turma viu antes da aula dele —, então dá para desligar.
            --}}
            @if ($temRecomposicao)
                <div class="flex items-end sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <input type="checkbox" wire:model.live="soRecomposicao" @checked($soRecomposicao)
                               class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                        <span class="text-slate-700 dark:text-slate-200">
                            Só as aulas de recomposição da aprendizagem
                        </span>
                    </label>
                </div>
            @endif

            @if ($temDisciplinas)
                <div class="flex items-end sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                        <input type="checkbox" wire:model.live="soOsMeus" @checked($soOsMeus)
                               class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                        <span class="text-slate-700 dark:text-slate-200">
                            Só os componentes que eu leciono
                        </span>
                    </label>
                </div>
            @endif
        </div>
    </x-cartao>

    {{--
        Nada importado ainda é o estado em que a tela nasce, e ele não
        pode mandar escolher de uma lista vazia. Para a coordenação é um
        convite a importar; para o professor, a notícia de que não há o
        que ver — e de quem cobrar.
    --}}
    @if ($cursos->isEmpty())
        <x-cartao>
            <x-vazio titulo="Nenhum escopo-sequência importado ainda">
                <x-slot:descricao>
                    @can('importar', App\Models\EscopoAula::class)
                        O escopo vem da rede como planilha, um arquivo por período de cada curso.
                        Importe o primeiro para que ele apareça aqui.
                    @else
                        A coordenação ainda não importou o escopo dos cursos em que você leciona.
                    @endcan
                </x-slot:descricao>
                <x-slot:acoes>
                    @can('importar', App\Models\EscopoAula::class)
                        <x-botao href="{{ route('escopo.importar') }}" wire:navigate>Importar escopo</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        </x-cartao>
    @elseif ($filtroCurso === '')
        <x-cartao>
            <x-vazio titulo="Escolha um curso"
                     descricao="O escopo-sequência é o percurso de um curso, semana a semana. Escolha acima para vê-lo."/>
        </x-cartao>
    @elseif ($porSemana === [])
        <x-cartao>
            <x-vazio titulo="Nenhuma aula neste recorte"
                     :descricao="$busca !== '' || $soOsMeus
                        ? 'Nenhuma aula casa com a busca ou com os componentes que você leciona. Limpe os filtros para ver o percurso inteiro.'
                        : 'Este curso ainda não teve o escopo-sequência importado.'">
                <x-slot:acoes>
                    @can('importar', App\Models\EscopoAula::class)
                        <x-botao href="{{ route('escopo.importar', ['curso' => $filtroCurso]) }}" wire:navigate>
                            Importar escopo
                        </x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        </x-cartao>
    @else
        <p class="px-1 text-sm text-slate-500 dark:text-slate-400">
            {{ $totalDeAulas }} aula(s) neste recorte. Clique numa semana para ver as aulas dela.
        </p>

        @foreach ($porSemana as $grupo)
            <x-cartao titulo="{{ $grupo['bimestre'] }}º bimestre"
                      :descricao="count($grupo['semanas']).' semana(s)'">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($grupo['semanas'] as $semana)
                        @php $aberta = $semanaAberta === $semana['chave']; @endphp

                        <li wire:key="semana-{{ $semana['chave'] }}">
                            {{--
                                A semana fechada já diz o essencial: o tema
                                e quais componentes entram nela. Abrir é para
                                ler a aula, não para descobrir se vale abrir.
                            --}}
                            <button type="button" wire:click="abrirSemana('{{ $semana['chave'] }}')"
                                    aria-expanded="{{ $aberta ? 'true' : 'false' }}"
                                    class="flex w-full items-start justify-between gap-4 px-4 py-3 text-left transition hover:bg-slate-50 sm:px-6 dark:hover:bg-slate-800/50">
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900 dark:text-slate-100">
                                            Semana {{ $semana['semana'] }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            {{ $semana['aulas']->count() }} aula(s)
                                        </span>
                                        {{--
                                            No escopo real a semana inteira é de
                                            recomposição, uma por bimestre. Dizer
                                            isso na linha fechada evita abrir oito
                                            semanas até achar a certa.
                                        --}}
                                        @if ($semana['recomposicao'] > 0)
                                            <x-badge cor="amarelo" :rotulo="$semana['recomposicao'] === $semana['aulas']->count()
                                                ? 'Recomposição da aprendizagem'
                                                : $semana['recomposicao'].' de recomposição'"/>
                                        @endif
                                    </span>
                                    @if ($semana['tema'])
                                        <span class="mt-0.5 block text-sm text-slate-600 dark:text-slate-300">
                                            {{ $semana['tema'] }}
                                        </span>
                                    @elseif (count($semana['temasPorComponente']) > 1)
                                        {{--
                                            Cada componente tem o seu tema nesta
                                            semana. Eleger um deles seria pôr
                                            "Aplicações Móveis" em cima de uma
                                            semana que também é de banco de dados.
                                        --}}
                                        <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                            {{ count($semana['temasPorComponente']) }} temas, um por componente
                                        </span>
                                    @endif
                                    <span class="mt-1 block truncate text-xs text-slate-400">
                                        {{ implode(' · ', $semana['componentes']) }}
                                    </span>
                                </span>

                                <span class="shrink-0 text-xs text-slate-400">
                                    {{ $aberta ? 'Fechar' : 'Ver aulas' }}
                                </span>
                            </button>

                            @if ($aberta)
                                <div class="space-y-3 bg-slate-50 px-4 py-4 sm:px-6 dark:bg-slate-950/40">
                                    @foreach ($semana['aulas'] as $aula)
                                        <article wire:key="aula-{{ $aula->id }}"
                                                 class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                                <h3 class="min-w-0 font-medium text-slate-900 dark:text-slate-100">
                                                    {{ $aula->titulo_aula }}
                                                </h3>
                                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                                    {{--
                                                        O valor cru vai no `title`: a coluna é de
                                                        fora, e o selo não pode afirmar mais do que
                                                        o arquivo disse.
                                                    --}}
                                                    @if ($aula->ehRecomposicao())
                                                        <span title="Aula complementar: {{ $aula->aula_complementar }}">
                                                            <x-badge cor="amarelo" rotulo="Recomposição da aprendizagem"/>
                                                        </span>
                                                    @endif
                                                    @if ($aula->disciplina)
                                                        <x-badge cor="azul" :rotulo="$aula->disciplina->nome"/>
                                                    @endif
                                                    @if ($aula->carga_horaria)
                                                        <span class="text-xs text-slate-400">CH {{ $aula->carga_horaria }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $aula->componente() }}
                                                @if ($aula->unidade_nome)
                                                    · {{ $aula->unidade_nome }}
                                                @endif
                                            </p>

                                            {{-- O tema fica aqui quando a semana tem vários. --}}
                                            @if ($aula->tema_semana && ! $semana['tema'])
                                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                                    {{ $aula->tema_semana }}
                                                </p>
                                            @endif

                                            {{--
                                                Só o que veio preenchido. O arquivo chega de
                                                fora e nem toda aula traz tudo — um rótulo
                                                com nada embaixo faz parecer que o sistema
                                                perdeu o conteúdo.
                                            --}}
                                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                                @foreach ([
                                                    'Objetivos da aula' => $aula->objetivos,
                                                    'Objeto de conhecimento' => $aula->objeto_conhecimento,
                                                    'Habilidades técnicas' => $aula->habilidades_tecnicas,
                                                    'Habilidades socioemocionais' => $aula->habilidades_socioemocionais,
                                                    'Competência técnica' => $aula->competencia_tecnica,
                                                    'Competências socioemocionais' => $aula->competencias_socioemocionais,
                                                ] as $rotulo => $valor)
                                                    @if ($valor)
                                                        <div>
                                                            <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $rotulo }}</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $valor }}</dd>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </dl>

                                            @if ($aula->aulas_semanais)
                                                <p class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-400 dark:border-slate-800">
                                                    Aulas semanais: {{ $aula->aulas_semanais }}
                                                </p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-cartao>
        @endforeach
    @endif
</div>
