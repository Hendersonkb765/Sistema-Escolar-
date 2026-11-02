{{-- A cada minuto: o marcador de "Agora" anda com o relógio. --}}
<div wire:poll.60s>
    <x-cartao :titulo="$daCoordenacao ? 'Escala da semana da escola' : 'Sua escala da semana'"
              :icone="App\Support\Navegacao::icone('calendario')"
              :descricao="($hoje
                  ? 'A coluna marcada é hoje, '.mb_strtolower($hoje->rotulo()).'.'
                  : 'Hoje é domingo: não há aula. A semana abaixo começa amanhã.')
                  .($daCoordenacao ? ' Seu Eixo vem primeiro; depois, os demais.' : '')">
        @if ($daCoordenacao && ($eixos->count() > 1 || $cursos->count() > 1))
            <x-slot:acoes>
                <div class="flex flex-wrap gap-2">
                    @if ($eixos->count() > 1)
                        <x-select id="escala-eixo" wire:model.live="eixo" class="!w-auto">
                            <option value="" @selected($eixo === '')>Todos os Eixos</option>
                            @foreach ($eixos as $opcao)
                                <option value="{{ $opcao->id }}" @selected($eixo === (string) $opcao->id)>{{ $opcao->nome }}{{ in_array($opcao->id, $meusEixos, true) ? ' (seu Eixo)' : '' }}</option>
                            @endforeach
                        </x-select>
                    @endif
                    @if ($cursos->count() > 1)
                        <x-select id="escala-curso" wire:model.live="curso" class="!w-auto">
                            <option value="" @selected($curso === '')>Todos os cursos</option>
                            @foreach ($cursos as $opcao)
                                <option value="{{ $opcao->id }}" @selected($curso === (string) $opcao->id)>{{ $opcao->nome }}</option>
                            @endforeach
                        </x-select>
                    @endif
                </div>
            </x-slot:acoes>
        @endif

        @if ($linhas->isEmpty())
            <x-vazio :titulo="$daCoordenacao ? 'Nenhuma aula no quadro das turmas' : 'Nenhuma aula sua no quadro de horários'"
                     :descricao="$daCoordenacao
                         ? 'O quadro de cada turma é montado na tela da turma, pelo botão Quadro de horários.'
                         : 'Quem monta o quadro é a coordenação. Se faltar alguma aula, avise o PAEET.'"/>
        @else
            <div class="-mx-4 -my-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">{{ $daCoordenacao ? 'Turma' : '' }}</th>
                            <th class="px-2 py-2">Aula</th>
                            @foreach ($dias as $coluna)
                                <th @class([
                                    'min-w-40 px-3 py-2',
                                    'bg-marca-50 text-marca-700 dark:bg-marca-900/30 dark:text-marca-300' => $coluna === $hoje,
                                ]) @if ($coluna === $hoje) data-hoje aria-current="date" @endif>
                                    {{ $coluna->rotulo() }}
                                    @if ($coluna === $hoje)
                                        <span class="ml-1 rounded-full bg-marca-600 px-1.5 py-0.5 text-[10px] font-semibold normal-case text-white">Hoje</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            @php($anterior = $loop->first ? null : $linhas[$loop->index - 1])
                            @if ($daCoordenacao && ($anterior === null || $anterior['eixo'] !== $linha['eixo']))
                                <tr wire:key="eixo-{{ $loop->index }}" data-eixo-da-escala>
                                    <td colspan="{{ $dias->count() + 2 }}"
                                        class="border-t-2 border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800 sm:px-6 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                        {{ $linha['eixo'] }}
                                        @if ($linha['doMeuEixo'])
                                            <span class="ml-2 rounded-full bg-marca-600 px-2 py-0.5 text-[10px] font-semibold text-white">Seu Eixo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            @if ($daCoordenacao && ($anterior === null || $anterior['eixo'] !== $linha['eixo'] || $anterior['grupo'] !== $linha['grupo']))
                                <tr wire:key="grupo-{{ $loop->index }}">
                                    <td colspan="{{ $dias->count() + 2 }}"
                                        class="border-t border-slate-200 bg-slate-50 px-4 py-1.5 text-xs font-semibold text-slate-600 sm:px-6 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">{{ $linha['grupo'] }}</td>
                                </tr>
                            @endif

                            @foreach ($linha['linhas'] as $item)
                                @if (isset($item['intervalo']))
                                    @php($intervaloAgora = $item['intervalo']->acontecendoEm($momento))
                                    <tr wire:key="intervalo-{{ $loop->parent->index }}-{{ $loop->index }}" data-intervalo
                                        @if ($intervaloAgora) data-agora @endif
                                        class="border-t border-dashed border-slate-200 dark:border-slate-700">
                                        @if ($loop->first)
                                            <td rowspan="{{ count($linha['linhas']) }}"
                                                class="whitespace-nowrap px-4 py-2 align-top font-medium text-slate-800 sm:px-6 dark:text-slate-100">{{ $linha['rotulo'] }}</td>
                                        @endif
                                        <td colspan="{{ $dias->count() + 1 }}"
                                            @class([
                                                'px-2 py-1 text-center text-xs',
                                                'bg-slate-50 text-slate-500 dark:bg-slate-800/40 dark:text-slate-400' => ! $intervaloAgora,
                                                'bg-amber-50 font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' => $intervaloAgora,
                                            ])>
                                            {{ $item['intervalo']->nome }} · {{ $item['intervalo']->intervalo() }}
                                            @if ($intervaloAgora)
                                                <span class="ml-1 rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">Agora</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @continue
                                @endif

                                @php($numero = $item['aula'])
                                <tr wire:key="linha-{{ $loop->parent->index }}-{{ $numero }}" data-aula="{{ $numero }}"
                                    @class([
                                        'border-t',
                                        'border-slate-200 dark:border-slate-700' => $loop->first,
                                        'border-slate-100 dark:border-slate-800' => ! $loop->first,
                                    ])>
                                    @if ($loop->first)
                                        <td rowspan="{{ count($linha['linhas']) }}"
                                            class="whitespace-nowrap px-4 py-2 align-top font-medium text-slate-800 sm:px-6 dark:text-slate-100">{{ $linha['rotulo'] }}</td>
                                    @endif
                                    <td class="whitespace-nowrap px-2 py-2 align-top text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $numero }}ª</td>
                                    @foreach ($dias as $coluna)
                                        <td @class([
                                            'px-3 py-2 align-top',
                                            'bg-marca-50/60 dark:bg-marca-900/20' => $coluna === $hoje,
                                        ])>
                                            @foreach ($linha['porCelula']->get($coluna->value.'-'.$numero, collect()) as $aula)
                                                @php($aulaAgora = $aula->acontecendoEm($momento))
                                                <p wire:key="aula-{{ $aula->id }}" @if ($aulaAgora) data-agora @endif
                                                   @class([
                                                       'text-xs leading-5',
                                                       '-mx-1 rounded-md bg-marca-600/10 px-1 ring-2 ring-marca-500 dark:bg-marca-400/10 dark:ring-marca-400' => $aulaAgora,
                                                   ])>
                                                    @if ($aulaAgora)
                                                        <span class="mr-1 rounded-full bg-marca-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">Agora</span>
                                                    @endif
                                                    <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $aula->horaInicio() }}</span>
                                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ $aula->disciplina->nome }}</span>
                                                    <span class="text-slate-500 dark:text-slate-400">· {{ $daCoordenacao ? $aula->professor->nome : $aula->turma->nome }}</span>
                                                </p>
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-cartao>
</div>
