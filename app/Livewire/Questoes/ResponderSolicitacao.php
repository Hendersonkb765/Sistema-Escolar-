<?php

namespace App\Livewire\Questoes;

use App\Actions\Avaliacao\EnviarSolicitacaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Área do professor: preencher e enviar as questões pedidas.
 *
 * O peso de cada questão aparece, mas só para leitura — quem o define é o
 * PAEET, na solicitação.
 */
class ResponderSolicitacao extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $solicitacaoId;

    /**
     * Rascunho de cada questão, indexado pelo id.
     *
     * @var array<int, array{enunciado: ?string, alternativas: array<int, array{letra: string, texto: ?string, correta: bool}>}>
     */
    public array $formulario = [];

    public bool $confirmandoEnvio = false;

    public function mount(SolicitacaoProva $solicitacao, SalvarQuestaoAction $action): void
    {
        $this->authorize('responder', $solicitacao);

        $this->solicitacaoId = $solicitacao->getKey();

        foreach ($solicitacao->questoes()->with('alternativas')->get() as $questao) {
            $action->prepararAlternativas($questao);
        }

        $this->carregarFormulario();
    }

    protected function solicitacao(): SolicitacaoProva
    {
        return SolicitacaoProva::query()
            ->with(['disciplina', 'turma.curso.eixo', 'criadoPor'])
            ->findOrFail($this->solicitacaoId);
    }

    /** @return Collection<int, Questao> */
    protected function questoes(): Collection
    {
        return Questao::query()
            ->where('solicitacao_id', $this->solicitacaoId)
            ->with(['item', 'alternativas', 'feedbacks.analisadoPor'])
            ->get()
            ->sortBy(fn (Questao $questao) => $questao->item->ordem)
            ->values();
    }

    protected function carregarFormulario(): void
    {
        $this->formulario = $this->questoes()
            ->mapWithKeys(fn (Questao $questao) => [
                $questao->getKey() => [
                    'enunciado' => $questao->enunciado,
                    'alternativas' => $questao->alternativas
                        ->sortBy('letra')
                        ->map(fn ($alternativa) => [
                            'letra' => $alternativa->letra,
                            'texto' => $alternativa->texto,
                            'correta' => (bool) $alternativa->correta,
                        ])
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
    }

    /** Marcar uma correta desmarca as demais da mesma questão. */
    public function marcarCorreta(int $questaoId, string $letra): void
    {
        if (! isset($this->formulario[$questaoId])) {
            return;
        }

        foreach ($this->formulario[$questaoId]['alternativas'] as $indice => $alternativa) {
            $this->formulario[$questaoId]['alternativas'][$indice]['correta'] =
                $alternativa['letra'] === $letra;
        }
    }

    public function salvarQuestao(int $questaoId, SalvarQuestaoAction $action): void
    {
        $questao = Questao::query()->findOrFail($questaoId);

        $this->authorize('update', $questao);

        $rascunho = $this->formulario[$questaoId] ?? null;

        if ($rascunho === null) {
            return;
        }

        try {
            $action->executar(
                questao: $questao,
                autor: auth()->user(),
                enunciado: $rascunho['enunciado'] ?: null,
                alternativas: $rascunho['alternativas'],
            );
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());

            return;
        }

        session()->flash('sucesso', 'Rascunho salvo.');
    }

    public function salvarTudo(SalvarQuestaoAction $action): void
    {
        foreach (array_keys($this->formulario) as $questaoId) {
            $questao = Questao::query()->find($questaoId);

            if ($questao === null || auth()->user()->cannot('update', $questao)) {
                continue;
            }

            try {
                $action->executar(
                    questao: $questao,
                    autor: auth()->user(),
                    enunciado: $this->formulario[$questaoId]['enunciado'] ?: null,
                    alternativas: $this->formulario[$questaoId]['alternativas'],
                );
            } catch (RegraDeNegocioException $excecao) {
                session()->flash('erro', $excecao->getMessage());

                return;
            }
        }

        session()->flash('sucesso', 'Rascunhos salvos.');
    }

    public function enviar(SalvarQuestaoAction $salvar, EnviarSolicitacaoAction $enviar): void
    {
        $solicitacao = $this->solicitacao();

        $this->authorize('responder', $solicitacao);

        $this->salvarTudo($salvar);

        try {
            $enviada = $enviar->executar($solicitacao->refresh(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());
            $this->confirmandoEnvio = false;

            return;
        }

        session()->flash('sucesso', $enviada->enviada_em_atraso
            ? 'Questões enviadas. O envio ficou registrado como em atraso, mas foi aceito normalmente.'
            : 'Questões enviadas para análise dentro do prazo.');

        $this->redirectRoute('solicitacoes.show', $enviada, navigate: true);
    }

    public function render(EnviarSolicitacaoAction $enviar): View
    {
        $solicitacao = $this->solicitacao();
        $questoes = $this->questoes();

        return view('questoes.responder', [
            'solicitacao' => $solicitacao,
            'questoes' => $questoes,
            'incompletas' => $enviar->questoesIncompletas($solicitacao),
            'podeEditar' => $solicitacao->aceitaEnvio(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Responder: '.$solicitacao->disciplina->nome,
            'subtitulo' => 'Turma '.$solicitacao->turma->nome
                .' · prazo '.$solicitacao->prazo->format('d/m/Y H:i')
                .($solicitacao->estaAtrasada() ? ' · atrasada' : ''),
        ]);
    }
}
