<?php

namespace App\Livewire\Provas;

use App\Actions\Prova\GerarGabaritoCsvAction;
use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\StatusProva;
use App\Livewire\Concerns\Notifica;
use App\Models\Prova;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Pré-visualização da prova dentro do sistema, com o mesmo HTML que vira
 * PDF. O professor confere antes de imprimir.
 */
class DetalheProva extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public Prova $prova;

    public function mount(Prova $prova): void
    {
        $this->authorize('view', $prova);

        $this->prova = $prova;
    }

    public function marcarComoAplicada(): void
    {
        $this->authorize('aplicar', $this->prova);

        if (! $this->prova->podeSerAplicada()) {
            $this->notificarErro(
                $this->prova->motivoParaNaoAplicar() ?? 'Esta prova não pode ser aplicada agora.'
            );

            return;
        }

        $this->prova->update(['status' => StatusProva::Aplicada]);

        activity('prova')
            ->performedOn($this->prova)
            ->causedBy(auth()->user())
            ->log('Prova marcada como aplicada');

        $this->notificarSucesso('Prova marcada como aplicada. Agora ela aceita a importação de resultados.');
    }

    public function render(RenderizarProvaAction $renderizar, GerarGabaritoCsvAction $gabarito): View
    {
        $this->prova->load(['turma.curso.eixo', 'modelo', 'geradaPor']);

        $podeVerOGabarito = auth()->user()->can('verGabarito', $this->prova);

        return view('provas.detalhe', [
            'folha' => $renderizar->paraTela($this->prova),
            'porDisciplina' => $this->prova->questoesPorDisciplina(),
            'podeVerOGabarito' => $podeVerOGabarito,
            // Os avisos são do gabarito, e só quem pode baixá-lo os lê.
            'avisosDoGabarito' => $podeVerOGabarito ? $gabarito->avisos($this->prova) : [],
        ])->layout('components.layouts.app', [
            'titulo' => $this->prova->titulo,
            'subtitulo' => 'Turma '.$this->prova->turma->nome
                .' · '.$this->prova->totalDeQuestoes().' questões'
                .' · v'.$this->prova->versao,
        ]);
    }
}
