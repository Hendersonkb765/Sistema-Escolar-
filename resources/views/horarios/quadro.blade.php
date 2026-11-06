<div class="space-y-4">
    <x-cartao titulo="Semana da turma {{ $turma->nome }}"
              descricao="É deste quadro que a ausência de um professor tira as aulas do dia.">
        <x-slot:acoes>
            <x-botao variante="secundario" href="{{ route('turmas.show', $turma) }}" wire:navigate>Voltar à turma</x-botao>
        </x-slot:acoes>

        @if ($numeros->isEmpty())
            <x-vazio titulo="Nenhuma aula no quadro"
                     descricao="Inclua as aulas da semana no formulário abaixo, uma por vez."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Aula</th>
                            @foreach ($dias as $coluna)
                                <th class="px-3 py-2">{{ $coluna->rotulo() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($numeros as $linha)
                            <tr wire:key="linha-{{ $linha }}">
                                <td class="px-4 py-3 font-medium text-slate-700 sm:px-6 dark:text-slate-200">{{ $linha }}ª</td>
                                {{-- `$coluna`, e não `$dia`: `$dia` é o campo do formulário abaixo. --}}
                                @foreach ($dias as $coluna)
                                    @php($aula = $porCelula->get($coluna->value.'-'.$linha))
                                    <td class="min-w-40 px-3 py-3 align-top" wire:key="celula-{{ $coluna->value }}-{{ $linha }}">
                                        @if ($aula)
                                            <div @class([
                                                'rounded-lg p-2 ring-1',
                                                'bg-marca-50 ring-marca-300 dark:bg-marca-900/20 dark:ring-marca-700' => $editando === $aula->id,
                                                'ring-slate-200 dark:ring-slate-700' => $editando !== $aula->id,
                                            ])>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $aula->disciplina->nome }}</p>
                                                <p class="text-xs text-slate-600 dark:text-slate-300">{{ $aula->professor->nome }}</p>
                                                <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $aula->intervalo() }}</p>

                                                <div class="mt-1 flex gap-1">
                                                    @can('update', $aula)
                                                        <x-botao variante="discreto" class="!px-2 !py-1 text-xs"
                                                                 wire:click="editar({{ $aula->id }})">Editar</x-botao>
                                                    @endcan
                                                    @can('delete', $aula)
                                                        <x-botao variante="discreto" class="!px-2 !py-1 text-xs"
                                                                 wire:click="remover({{ $aula->id }})"
                                                                 wire:confirm="Remover a {{ $aula->descricaoDaAula() }} do quadro?">Remover</x-botao>
                                                    @endcan
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-cartao>

    <form wire:submit="salvar">
        <x-cartao :titulo="$editando ? 'Editar aula' : 'Incluir aula'"
                  descricao="O professor precisa ter vínculo com a disciplina, e não pode estar em outra turma na mesma hora.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-campo rotulo="Dia" para="dia" obrigatorio :erro="$errors->first('dia')">
                    <x-select id="dia" wire:model="dia">
                        @foreach ($diasDoFormulario as $opcao)
                            <option value="{{ $opcao->value }}" @selected($dia === (string) $opcao->value)>{{ $opcao->rotulo() }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Aula" para="aula_numero" obrigatorio :erro="$errors->first('aula_numero')">
                    <x-select id="aula_numero" wire:model="aula_numero">
                        @foreach (range(1, 12) as $numero)
                            <option value="{{ $numero }}" @selected($aula_numero === (string) $numero)>{{ $numero }}ª aula</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Início" para="hora_inicio" obrigatorio :erro="$errors->first('hora_inicio')">
                    <x-input id="hora_inicio" tipo="time" wire:model="hora_inicio" value="{{ $hora_inicio }}"/>
                </x-campo>

                <x-campo rotulo="Fim" para="hora_fim" obrigatorio :erro="$errors->first('hora_fim')">
                    <x-input id="hora_fim" tipo="time" wire:model="hora_fim" value="{{ $hora_fim }}"/>
                </x-campo>

                <x-campo rotulo="Disciplina" para="disciplina_id" obrigatorio :erro="$errors->first('disciplina_id')"
                         class="sm:col-span-2">
                    <x-select id="disciplina_id" wire:model.live="disciplina_id">
                        <option value="" @selected((string) $disciplina_id === '')>Selecione…</option>
                        @foreach ($disciplinas as $disciplina)
                            <option value="{{ $disciplina->id }}" @selected($disciplina_id === (string) $disciplina->id)>{{ $disciplina->nome }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Professor" para="professor_id" obrigatorio :erro="$errors->first('professor_id')"
                         class="sm:col-span-2"
                         :ajuda="$disciplina_id === ''
                             ? 'Escolha a disciplina primeiro.'
                             : ($professores->isEmpty() ? 'Ninguém tem vínculo com esta disciplina. O vínculo é feito no cadastro do usuário.' : null)">
                    <x-select id="professor_id" wire:model="professor_id" :desabilitado="$professores->isEmpty()">
                        <option value="" @selected((string) $professor_id === '')>Selecione…</option>
                        @foreach ($professores as $professor)
                            <option value="{{ $professor->id }}" @selected($professor_id === (string) $professor->id)>{{ $professor->nome }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                @if ($editando)
                    <x-botao variante="secundario" wire:click="cancelarEdicao">Cancelar edição</x-botao>
                @endif
                <x-botao tipo="submit" wire:loading.attr="disabled">{{ $editando ? 'Salvar aula' : 'Incluir aula' }}</x-botao>
            </div>
        </x-cartao>
    </form>
</div>
