<?php

namespace App\Livewire\Questoes;

use App\Actions\Avaliacao\EnviarSolicitacaoAction;
use App\Actions\Avaliacao\ReenviarQuestaoAction;
use App\Actions\Avaliacao\SalvarBlocosDaQuestaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\LinguagemCodigo;
use App\Enums\TipoBlocoQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Área do professor: escrever e enviar as questões pedidas.
 *
 * O enunciado é montado em blocos — parágrafos, trechos de código com a
 * linguagem declarada e imagens — e o peso de cada questão é escolhido
 * aqui, por quem a escreve.
 */
class ResponderSolicitacao extends Component
{
    use AuthorizesRequests;
    use Notifica;
    use WithFileUploads;

    #[Locked]
    public int $solicitacaoId;

    /**
     * Rascunho de cada questão, indexado pelo id.
     *
     * @var array<int, array{enunciado: ?string, peso: string, alternativas: array<int, array{letra: string, texto: ?string, correta: bool}>, blocos: array<int, array<string, mixed>>}>
     */
    public array $formulario = [];

    /** Upload em andamento, por questão. @var array<int, mixed> */
    public array $imagens = [];

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
            ->with(['item', 'alternativas', 'blocos', 'feedbacks.analisadoPor'])
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
                    'peso' => (string) (float) $questao->peso,
                    'alternativas' => $questao->alternativas
                        ->sortBy('letra')
                        ->map(fn ($alternativa) => [
                            'letra' => $alternativa->letra,
                            'texto' => $alternativa->texto,
                            'correta' => (bool) $alternativa->correta,
                        ])
                        ->values()
                        ->all(),
                    'blocos' => $questao->blocos
                        ->map(fn ($bloco) => [
                            'tipo' => $bloco->tipo->value,
                            'conteudo' => $bloco->conteudo,
                            'linguagem' => $bloco->linguagem?->value,
                            'caminho' => $bloco->caminho,
                            'legenda' => $bloco->legenda,
                        ])
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Blocos do enunciado
    // ------------------------------------------------------------------

    public function adicionarBloco(int $questaoId, string $tipo): void
    {
        $tipoBloco = TipoBlocoQuestao::tryFrom($tipo);

        if ($tipoBloco === null || ! isset($this->formulario[$questaoId])) {
            return;
        }

        $this->formulario[$questaoId]['blocos'][] = [
            'tipo' => $tipoBloco->value,
            'conteudo' => null,
            'linguagem' => $tipoBloco === TipoBlocoQuestao::Codigo ? LinguagemCodigo::Python->value : null,
            'caminho' => null,
            'legenda' => null,
        ];
    }

    public function removerBloco(int $questaoId, int $indice): void
    {
        if (! isset($this->formulario[$questaoId]['blocos'][$indice])) {
            return;
        }

        unset($this->formulario[$questaoId]['blocos'][$indice]);

        $this->formulario[$questaoId]['blocos'] = array_values($this->formulario[$questaoId]['blocos']);
    }

    public function moverBloco(int $questaoId, int $indice, int $direcao): void
    {
        $blocos = $this->formulario[$questaoId]['blocos'] ?? [];
        $destino = $indice + $direcao;

        if (! isset($blocos[$indice], $blocos[$destino])) {
            return;
        }

        [$blocos[$indice], $blocos[$destino]] = [$blocos[$destino], $blocos[$indice]];

        $this->formulario[$questaoId]['blocos'] = $blocos;
    }

    /**
     * Guarda a imagem assim que ela é escolhida, para o professor ver o
     * que subiu antes de salvar o resto da questão.
     */
    public function updatedImagens(mixed $arquivo, string $chave): void
    {
        [$questaoId, $indice] = array_pad(explode('.', $chave), 2, null);

        $questao = Questao::query()->find((int) $questaoId);

        if ($questao === null || auth()->user()->cannot('update', $questao)) {
            return;
        }

        try {
            $caminho = app(SalvarBlocosDaQuestaoAction::class)
                ->guardarImagem($this->imagens[$questaoId][$indice], $questao);

            $this->notificarSucesso('Imagem anexada ao enunciado.');
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());
            unset($this->imagens[$questaoId][$indice]);

            return;
        }

        $this->formulario[(int) $questaoId]['blocos'][(int) $indice]['caminho'] = $caminho;
        unset($this->imagens[$questaoId][$indice]);
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
        // `item` entra no eager load porque a mensagem cita o número da
        // questão.
        $questao = Questao::query()->with('item')->findOrFail($questaoId);

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
                peso: $rascunho['peso'] ?? null,
            );

            app(SalvarBlocosDaQuestaoAction::class)->executar(
                questao: $questao,
                autor: auth()->user(),
                blocos: $rascunho['blocos'] ?? [],
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso(
            "Questão {$questao->item->ordem} salva como rascunho. Nada foi enviado ainda.",
            'Rascunho salvo',
        );
    }

    public function salvarTudo(SalvarQuestaoAction $action, bool $avisar = true): void
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
                    peso: $this->formulario[$questaoId]['peso'] ?? null,
                );

                app(SalvarBlocosDaQuestaoAction::class)->executar(
                    questao: $questao,
                    autor: auth()->user(),
                    blocos: $this->formulario[$questaoId]['blocos'] ?? [],
                );
            } catch (RegraDeNegocioException $excecao) {
                $this->notificarErro($excecao->getMessage());

                return;
            }
        }

        if ($avisar) {
            $quantidade = count($this->formulario);

            $this->notificarSucesso(
                $quantidade === 1
                    ? 'A questão foi salva como rascunho. Nada foi enviado ainda.'
                    : "As {$quantidade} questões foram salvas como rascunho. Nada foi enviado ainda.",
                'Rascunho salvo',
            );
        }
    }

    /**
     * Reenvia uma questão que voltou para correção. Vai como versão nova,
     * e o feedback anterior continua no histórico apontando para a versão
     * a que se referia.
     */
    public function reenviarQuestao(int $questaoId, SalvarQuestaoAction $salvar, ReenviarQuestaoAction $reenviar): void
    {
        $questao = Questao::query()->with('item')->findOrFail($questaoId);

        $this->authorize('update', $questao);

        $rascunho = $this->formulario[$questaoId] ?? null;

        try {
            if ($rascunho !== null) {
                $salvar->executar(
                    questao: $questao,
                    autor: auth()->user(),
                    enunciado: $rascunho['enunciado'] ?: null,
                    alternativas: $rascunho['alternativas'],
                    peso: $rascunho['peso'] ?? null,
                );

                app(SalvarBlocosDaQuestaoAction::class)->executar(
                    questao: $questao,
                    autor: auth()->user(),
                    blocos: $rascunho['blocos'] ?? [],
                );
            }

            $reenviada = $reenviar->executar($questao->refresh(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso(
            "Questão {$questao->item->ordem} reenviada como versão {$reenviada->versao}. A coordenação vai analisar de novo.",
            'Correção enviada',
        );

        $this->carregarFormulario();
    }

    public function enviar(SalvarQuestaoAction $salvar, EnviarSolicitacaoAction $enviar): void
    {
        $solicitacao = $this->solicitacao();

        $this->authorize('responder', $solicitacao);

        // Grava o que estiver na tela antes de enviar, sem avisar duas
        // vezes: quem manda a mensagem é o envio.
        $this->salvarTudo($salvar, avisar: false);

        try {
            $enviada = $enviar->executar($solicitacao->refresh(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());
            $this->confirmandoEnvio = false;

            return;
        }

        $this->flashSucesso($enviada->enviada_em_atraso
            ? 'Questões enviadas para análise. O envio ficou registrado como em atraso, mas foi aceito normalmente.'
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
            'linguagens' => LinguagemCodigo::opcoes(),
            'somaDosPesos' => collect($this->formulario)->sum(fn (array $q) => (float) ($q['peso'] ?? 0)),
        ])->layout('components.layouts.app', [
            'titulo' => 'Responder: '.$solicitacao->disciplina->nome,
            'subtitulo' => 'Turma '.$solicitacao->turma->nome
                .' · prazo '.$solicitacao->prazo->format('d/m/Y H:i')
                .($solicitacao->estaAtrasada() ? ' · atrasada' : ''),
        ]);
    }
}
