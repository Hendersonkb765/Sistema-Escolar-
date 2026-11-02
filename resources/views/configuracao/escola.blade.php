<form wire:submit="salvar" class="mx-auto max-w-3xl space-y-4">
    <x-cartao titulo="A escola" :icone="App\Support\Navegacao::icone('painel')">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-campo rotulo="Nome da escola" para="nome" obrigatorio class="sm:col-span-2" :erro="$errors->first('nome')"
                     ajuda="Sai na folha de prova, no boletim e nos documentos, quando o modelo não diz outro.">
                <x-input id="nome" wire:model="nome" value="{{ $nome }}" maxlength="150"/>
            </x-campo>

            <x-campo rotulo="Aulas por dia" para="aulas_por_dia" obrigatorio :erro="$errors->first('aulas_por_dia')"
                     ajuda="A escala da semana mostra da 1ª até esta.">
                <x-input id="aulas_por_dia" tipo="number" min="1" max="12" wire:model="aulas_por_dia" value="{{ $aulas_por_dia }}"/>
            </x-campo>
        </div>
    </x-cartao>

    <x-cartao titulo="Intervalos" :icone="App\Support\Navegacao::icone('relogio')"
              descricao="Cada intervalo vale para todas as turmas, a menos que você escolha outras. Uma turma com horário diferente fica de fora do padrão e ganha um intervalo só dela.">
        <div class="space-y-4">
            @foreach ($intervalos as $indice => $intervalo)
                <div class="space-y-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700" wire:key="padrao-{{ $indice }}" data-linha-de-intervalo>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_8rem_8rem_auto] sm:items-end">
                        <x-campo rotulo="Nome" para="nome-{{ $indice }}" :erro="$errors->first('intervalos.'.$indice.'.nome')">
                            <x-input id="nome-{{ $indice }}" wire:model="intervalos.{{ $indice }}.nome" value="{{ $intervalo['nome'] }}" maxlength="60"/>
                        </x-campo>
                        <x-campo rotulo="Início" para="ini-{{ $indice }}" :erro="$errors->first('intervalos.'.$indice.'.inicio')">
                            <x-input id="ini-{{ $indice }}" tipo="time" wire:model="intervalos.{{ $indice }}.inicio" value="{{ $intervalo['inicio'] }}"/>
                        </x-campo>
                        <x-campo rotulo="Fim" para="fim-{{ $indice }}" :erro="$errors->first('intervalos.'.$indice.'.fim')">
                            <x-input id="fim-{{ $indice }}" tipo="time" wire:model="intervalos.{{ $indice }}.fim" value="{{ $intervalo['fim'] }}"/>
                        </x-campo>
                        <x-botao variante="discreto" wire:click="removerIntervalo({{ $indice }})">Remover</x-botao>
                    </div>

                    <x-campo rotulo="Aplicar a" para="alcance-{{ $indice }}" class="sm:max-w-xs">
                        <x-select id="alcance-{{ $indice }}" wire:model.live="intervalos.{{ $indice }}.alcance">
                            @foreach ($alcances as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($intervalo['alcance'] === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    @if ($intervalo['alcance'] !== App\Support\IntervaloDoDia::TODAS)
                        <fieldset data-turmas-do-intervalo>
                            <legend class="mb-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                {{ $intervalo['alcance'] === App\Support\IntervaloDoDia::EXCETO ? 'Turmas que ficam de fora' : 'Turmas que recebem este intervalo' }}
                            </legend>
                            @if ($turmas->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-slate-400">Nenhuma turma ativa cadastrada.</p>
                            @else
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-4">
                                    @foreach ($turmas as $turma)
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" wire:key="padrao-{{ $indice }}-turma-{{ $turma->id }}">
                                            <input type="checkbox" value="{{ $turma->id }}" wire:model="intervalos.{{ $indice }}.turmas"
                                                   @checked(in_array((string) $turma->id, $intervalo['turmas'], true))
                                                   class="rounded border-slate-300 dark:border-slate-600">
                                            <span>{{ $turma->nome }} <span class="text-xs text-slate-400">{{ $turma->curso->nome }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </fieldset>
                    @endif
                </div>
            @endforeach

            <x-botao variante="discreto" wire:click="adicionarIntervalo">+ Intervalo</x-botao>
        </div>
    </x-cartao>

    <x-cartao titulo="Calendário letivo" :icone="App\Support\Navegacao::icone('calendario')"
              descricao="As datas dos bimestres do ano são cadastradas no Calendário letivo e valem para todas as turmas.">
        <x-botao variante="secundario" href="{{ route('calendario.index') }}" wire:navigate>Abrir o calendário letivo</x-botao>
    </x-cartao>

    <div class="flex justify-end">
        <x-botao tipo="submit" wire:loading.attr="disabled">Salvar configuração</x-botao>
    </div>
</form>
