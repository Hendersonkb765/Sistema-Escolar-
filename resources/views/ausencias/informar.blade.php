@php
    $bloqueio = match (true) {
        $previsao === null => 'Escolha o dia da ausência.',
        $previsao->vazio() => $previsao->motivo,
        $pendencias->filter()->isNotEmpty() => $pendencias->filter()->implode(' '),
        default => null,
    };
@endphp

<form wire:submit="informar" class="mx-auto max-w-3xl space-y-4">
    <x-cartao titulo="Quando" descricao="As aulas do dia vêm do quadro de horários das suas turmas.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-campo rotulo="Dia da ausência" para="data" obrigatorio :erro="$errors->first('data')">
                <x-input id="data" tipo="date" wire:model.live="data" value="{{ $data }}" min="{{ today()->format('Y-m-d') }}"/>
            </x-campo>

            <x-campo rotulo="Motivo" para="motivo" class="sm:col-span-2" :erro="$errors->first('motivo')"
                     ajuda="Opcional. A coordenação vê o que você escrever aqui.">
                <x-input id="motivo" wire:model="motivo" value="{{ $motivo }}" maxlength="500"/>
            </x-campo>
        </div>

        @if ($dataPassada)
            <x-alerta tipo="atencao" class="mt-4">
                Esse dia já passou. A ausência é informada com antecedência: escolha hoje ou uma data adiante.
            </x-alerta>
        @endif
    </x-cartao>

    @if ($previsao?->vazio())
        <x-cartao>
            <x-vazio titulo="Nenhuma aula nesse dia" :descricao="$previsao->motivo"/>
        </x-cartao>
    @elseif ($previsao)
        @foreach ($previsao->aulas as $aula)
            @php($id = $aula->quadro->id)
            <x-cartao :titulo="$aula->descricao()" wire:key="aula-{{ $id }}">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            @if ($aula->local->encontrada())
                                {{ $aula->local->bimestre->rotulo() }}, semana {{ $aula->local->semana }} do escopo
                            @else
                                Sem semana do escopo
                            @endif
                        </p>

                        @if ($pendencias->get($id))
                            <x-badge cor="amarelo">Falta definir o conteúdo</x-badge>
                        @else
                            <x-badge cor="verde">Pronta para enviar</x-badge>
                        @endif
                    </div>

                    @if ($aula->motivo)
                        <x-alerta tipo="info">{{ $aula->motivo }}</x-alerta>
                    @endif

                    @if ($aula->opcoes->isNotEmpty())
                        <x-campo rotulo="Aula do escopo" para="escopo-{{ $id }}"
                                 ajuda="Sugerida pela posição desta aula na semana da turma. Troque se você estiver em outro ponto.">
                            <x-select id="escopo-{{ $id }}" wire:model.live="planos.{{ $id }}.escopo_aula_id">
                                <option value="" @selected(($planos[$id]['escopo_aula_id'] ?? '') === '')>Nenhuma — vou descrever o conteúdo</option>
                                @foreach ($aula->opcoes as $opcao)
                                    <option value="{{ $opcao->id }}" @selected(($planos[$id]['escopo_aula_id'] ?? '') === (string) $opcao->id)>
                                        {{ $opcao->titulo_aula }}{{ $aula->sugerida?->id === $opcao->id ? ' (sugerida)' : '' }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-campo>
                    @endif

                    <x-campo rotulo="Conteúdo planejado" para="conteudo-{{ $id }}"
                             :obrigatorio="$aula->opcoes->isEmpty() || ($planos[$id]['escopo_aula_id'] ?? '') === ''"
                             :erro="$errors->first('planos.'.$id) ?: $errors->first('planos.'.$id.'.conteudo')"
                             ajuda="O que a turma deve fazer nesta aula: aprofundamento, exercícios, conteúdo complementar ao escopo.">
                        <x-area-texto id="conteudo-{{ $id }}" wire:model.live.debounce.500ms="planos.{{ $id }}.conteudo"
                                      maxlength="2000">{{ $planos[$id]['conteudo'] ?? '' }}</x-area-texto>
                    </x-campo>

                    @if ($pendencias->get($id))
                        <p class="text-xs text-amber-700 dark:text-amber-300">{{ $pendencias->get($id) }}</p>
                    @endif
                </div>
            </x-cartao>
        @endforeach
    @endif

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-botao variante="secundario" href="{{ route('ausencias.index') }}" wire:navigate>Voltar</x-botao>
        <x-botao tipo="submit" :desabilitado="$bloqueio !== null" title="{{ $bloqueio ?? 'Enviar à coordenação' }}"
                 wire:loading.attr="disabled">Informar ausência</x-botao>
    </div>
</form>
