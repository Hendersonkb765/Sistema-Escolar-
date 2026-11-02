<div class="space-y-4">
    <x-cartao titulo="Eventos e feriados de {{ $ano }}" :icone="App\Support\Navegacao::icone('calendario')"
              descricao="Feriado e recesso são dias sem aula: neles não se informa ausência.">
        @if ($eventos->isEmpty())
            <x-vazio titulo="Nenhum evento ou feriado em {{ $ano }}"
                     :descricao="$podeEditar ? 'Cadastre no formulário abaixo.' : 'Quem cadastra os eventos da escola é o PAEET Admin.'"/>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($eventos as $evento)
                    <li wire:key="evento-{{ $evento->id }}" data-evento
                        @class(['flex flex-wrap items-start justify-between gap-3 py-3', 'opacity-60' => $evento->jaPassou()])>
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="tabular-nums text-sm text-slate-500 dark:text-slate-400">{{ $evento->periodo() }}</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $evento->titulo }}</span>
                                <x-badge :cor="$evento->tipo->cor()">{{ $evento->tipo->rotulo() }}</x-badge>
                                @if ($evento->jaPassou())
                                    <span class="text-xs text-slate-400">Já passou</span>
                                @endif
                            </p>
                            @if ($evento->descricao)
                                <p class="mt-0.5 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $evento->descricao }}</p>
                            @endif
                        </div>

                        @if ($podeEditar)
                            <div class="flex gap-1">
                                <x-botao variante="discreto" class="text-xs" wire:click="editar({{ $evento->id }})">Editar</x-botao>
                                <x-botao variante="discreto" class="text-xs" wire:click="remover({{ $evento->id }})"
                                         wire:confirm="Remover {{ $evento->titulo }} do calendário?">Remover</x-botao>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-cartao>

    @if ($podeEditar)
        <form wire:submit="salvar">
            <x-cartao :titulo="$editando ? 'Editar evento' : 'Incluir evento ou feriado'">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Título" para="evento-titulo" obrigatorio class="sm:col-span-2" :erro="$errors->first('titulo')">
                        <x-input id="evento-titulo" wire:model="titulo" value="{{ $titulo }}" maxlength="120"
                                 placeholder="Ex.: Nossa Senhora Aparecida, Conselho de classe"/>
                    </x-campo>

                    <x-campo rotulo="Tipo" para="evento-tipo" obrigatorio :erro="$errors->first('tipo')">
                        <x-select id="evento-tipo" wire:model="tipo">
                            @foreach ($tipos as $opcao)
                                <option value="{{ $opcao->value }}" @selected($tipo === $opcao->value)>{{ $opcao->rotulo() }}{{ $opcao->semAula() ? ' (sem aula)' : '' }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <div class="grid grid-cols-2 gap-3">
                        <x-campo rotulo="Data" para="evento-inicio" obrigatorio :erro="$errors->first('inicio')">
                            <x-input id="evento-inicio" tipo="date" wire:model="inicio" value="{{ $inicio }}"/>
                        </x-campo>
                        <x-campo rotulo="Até" para="evento-fim" :erro="$errors->first('fim')" ajuda="Em branco: um dia só.">
                            <x-input id="evento-fim" tipo="date" wire:model="fim" value="{{ $fim }}"/>
                        </x-campo>
                    </div>

                    <x-campo rotulo="Descrição" para="evento-descricao" class="sm:col-span-2" :erro="$errors->first('descricao')">
                        <x-area-texto id="evento-descricao" wire:model="descricao" maxlength="1000" linhas="2">{{ $descricao }}</x-area-texto>
                    </x-campo>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    @if ($editando)
                        <x-botao variante="secundario" wire:click="cancelarEdicao">Cancelar edição</x-botao>
                    @endif
                    <x-botao tipo="submit" wire:loading.attr="disabled">{{ $editando ? 'Salvar evento' : 'Incluir no calendário' }}</x-botao>
                </div>
            </x-cartao>
        </form>
    @endif

    @if ($podeEditar)
        <x-cartao titulo="Importar de uma planilha" :icone="App\Support\Navegacao::icone('importacao')"
                  descricao="Baixe o modelo, preencha um evento por linha e envie. Nada é gravado antes de você conferir.">
            <x-slot:acoes>
                <x-botao variante="secundario" href="{{ route('calendario.eventos.modelo') }}">Baixar modelo (XLSX)</x-botao>
            </x-slot:acoes>

            @if ($relatorio === null)
                <form wire:submit="conferirImportacao" class="space-y-3">
                    <x-campo rotulo="Planilha de eventos" para="planilha-eventos" :erro="$errors->first('planilha')"
                             ajuda="XLSX, ODS ou CSV, com as colunas Título, Tipo, Data, Até e Descrição.">
                        <input type="file" id="planilha-eventos" wire:model="planilha" accept=".xlsx,.xls,.ods,.csv"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                    </x-campo>
                    <div wire:loading wire:target="planilha" class="text-sm text-slate-500">Enviando o arquivo…</div>
                    <div class="flex justify-end">
                        <x-botao tipo="submit" wire:loading.attr="disabled">Conferir planilha</x-botao>
                    </div>
                </form>
            @else
                <div class="space-y-4" data-relatorio-de-importacao>
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        <strong>{{ count($relatorio['entram']) }}</strong> evento(s) entram no calendário.
                        @if ($relatorio['repetidos'] !== [])
                            {{ count($relatorio['repetidos']) }} já estão nele e ficam de fora.
                        @endif
                        @if ($relatorio['recusadas'] !== [])
                            {{ count($relatorio['recusadas']) }} linha(s) não puderam ser lidas.
                        @endif
                    </p>

                    @if ($relatorio['entram'] !== [])
                        <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            @foreach ($relatorio['entram'] as $linha)
                                <li class="flex flex-wrap items-center gap-2 py-1.5" wire:key="entra-{{ $linha['linha'] }}">
                                    <span class="w-44 tabular-nums text-slate-500 dark:text-slate-400">{{ $linha['periodo'] }}</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $linha['titulo'] }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $linha['tipo'] }}{{ $linha['sem_aula'] ? ' · sem aula' : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($relatorio['repetidos'] !== [])
                        <x-alerta tipo="info" titulo="Já estão no calendário">
                            <ul class="list-inside list-disc">
                                @foreach ($relatorio['repetidos'] as $linha)
                                    <li wire:key="repetido-{{ $linha['linha'] }}">Linha {{ $linha['linha'] }}: {{ $linha['titulo'] }} ({{ $linha['periodo'] }})</li>
                                @endforeach
                            </ul>
                        </x-alerta>
                    @endif

                    @if ($relatorio['recusadas'] !== [])
                        <x-alerta tipo="atencao" titulo="Linhas que não entram">
                            <ul class="list-inside list-disc">
                                @foreach ($relatorio['recusadas'] as $linha)
                                    <li wire:key="recusada-{{ $linha['linha'] }}">Linha {{ $linha['linha'] }}{{ $linha['titulo'] ? ' ('.$linha['titulo'].')' : '' }}: {{ $linha['erro'] }}</li>
                                @endforeach
                            </ul>
                        </x-alerta>
                    @endif

                    <div class="flex justify-end gap-2">
                        <x-botao variante="secundario" wire:click="descartarImportacao">Descartar</x-botao>
                        <x-botao wire:click="confirmarImportacao" :desabilitado="$relatorio['entram'] === []"
                                 title="{{ $relatorio['entram'] === [] ? 'Nenhum evento novo para importar.' : 'Gravar os eventos no calendário' }}">
                            Importar {{ count($relatorio['entram']) }} evento(s)
                        </x-botao>
                    </div>
                </div>
            @endif
        </x-cartao>
    @endif
</div>
