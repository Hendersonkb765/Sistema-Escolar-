<?php

namespace App\Livewire\Questoes;

use App\Actions\Avaliacao\EnviarParteAction;
use App\Actions\Avaliacao\ReenviarQuestaoAction;
use App\Actions\Avaliacao\SalvarBlocosDaQuestaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\LinguagemCodigo;
use App\Enums\StatusQuestao;
use App\Enums\TipoBlocoQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use App\Models\SolicitacaoProva;
use App\Support\TextoDoEnunciado;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Área do professor: escrever e enviar as questões pedidas.
 *
 * A prova reúne várias disciplinas, mas o professor vê e responde apenas
 * as partes dele. O envio é por parte: ele entrega a sua quando termina,
 * sem esperar pelos outros.
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
     * @var array<int, array{enunciado: ?string, habilidade: ?string, peso: string, alternativas: array<int, array{letra: string, texto: ?string, correta: bool}>, blocos: array<int, array<string, mixed>>}>
     */
    public array $formulario = [];

    /** Upload em andamento, por questão. @var array<int, mixed> */
    public array $imagens = [];

    /** Parte cujo envio está sendo confirmado. */
    public ?int $confirmandoEnvio = null;

    /**
     * Quando o professor chega por uma questão específica — o link de
     * "Corrigir", por exemplo — a tela mostra só ela, em vez da
     * disciplina inteira.
     */
    #[Url(as: 'questao', except: null)]
    public ?int $questaoEmFoco = null;

    public function mount(SolicitacaoProva $solicitacao, SalvarQuestaoAction $action, ?int $questao = null): void
    {
        $this->authorize('responder', $solicitacao);

        $this->solicitacaoId = $solicitacao->getKey();

        $foco = $questao ?? $this->questaoEmFoco;

        if ($foco !== null) {
            // O id vem da URL: só vale se a questão for desta solicitação
            // e de quem está respondendo.
            $daSolicitacao = Questao::query()
                ->where('solicitacao_id', $this->solicitacaoId)
                ->whereKey($foco)
                ->first();

            abort_if($daSolicitacao === null, 404);
            $this->authorize('update', $daSolicitacao);

            $this->questaoEmFoco = $daSolicitacao->getKey();
        }

        foreach ($this->questoes() as $questao) {
            $action->prepararAlternativas($questao);
        }

        $this->carregarFormulario();
    }

    /** Volta a mostrar todas as questões da disciplina. */
    public function limparFoco(): void
    {
        $this->questaoEmFoco = null;
    }

    /**
     * As partes desta solicitação que pertencem a quem está respondendo —
     * normalmente uma, mas um professor pode ter duas disciplinas.
     *
     * @return Collection<int, SolicitacaoParte>
     */
    protected function minhasPartes(): Collection
    {
        return SolicitacaoParte::query()
            ->where('solicitacao_id', $this->solicitacaoId)
            ->where('professor_id', auth()->id())
            ->with(['disciplina', 'solicitacao'])
            ->orderBy('ordem')
            ->get();
    }

    protected function solicitacao(): SolicitacaoProva
    {
        return SolicitacaoProva::query()
            ->with(['turma.curso.eixo', 'criadoPor'])
            ->findOrFail($this->solicitacaoId);
    }

    /**
     * Só as questões das partes deste professor.
     *
     * @return Collection<int, Questao>
     */
    protected function questoes(): Collection
    {
        return Questao::query()
            ->where('solicitacao_id', $this->solicitacaoId)
            ->where('professor_id', auth()->id())
            // Foco: o professor veio corrigir uma questão específica.
            ->when($this->questaoEmFoco !== null, fn ($q) => $q->whereKey($this->questaoEmFoco))
            ->with(['parte.disciplina', 'alternativas', 'blocos', 'feedbacks.analisadoPor'])
            ->get()
            ->sortBy(fn (Questao $questao) => [$questao->parte->ordem, $questao->ordem])
            ->values();
    }

    protected function carregarFormulario(): void
    {
        $this->formulario = $this->questoes()
            ->mapWithKeys(fn (Questao $questao) => [
                $questao->getKey() => [
                    'enunciado' => $questao->enunciado,
                    'habilidade' => $questao->habilidade,
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

    /**
     * Liga ou desliga negrito/itálico no trecho selecionado.
     *
     * A conta é do `TextoDoEnunciado`, no servidor: manter uma segunda
     * cópia da regra em JavaScript é o caminho curto para as duas
     * divergirem. O navegador só informa o que está selecionado.
     */
    public function alternarMarca(string $caminho, string $antes, string $selecao, string $depois, string $marca): void
    {
        if (! in_array($marca, [TextoDoEnunciado::MARCA_NEGRITO, TextoDoEnunciado::MARCA_ITALICO], true)) {
            return;
        }

        // O caminho vem do navegador: só os campos de texto da própria
        // questão são aceitos, e só se ela ainda puder ser editada.
        if (preg_match('/^formulario\\.(\\d+)\\.(enunciado|blocos\\.\\d+\\.conteudo)$/', $caminho, $achado) !== 1) {
            return;
        }

        $questao = Questao::query()->find((int) $achado[1]);

        if ($questao === null || auth()->user()->cannot('update', $questao)) {
            return;
        }

        $partes = TextoDoEnunciado::alternar($antes, $selecao, $depois, $marca);

        data_set($this, $caminho, $partes['antes'].$partes['selecao'].$partes['depois']);

        // O campo é redesenhado com o valor novo; sem isto o cursor cai
        // no fim e quem estava formatando perde o lugar.
        $this->dispatch(
            'marca-aplicada',
            campo: $caminho,
            inicio: mb_strlen($partes['antes']),
            fim: mb_strlen($partes['antes'].$partes['selecao']),
        );
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
        // `parte` entra no eager load porque a mensagem cita a disciplina
        // e o número da questão.
        $questao = Questao::query()->with('parte.disciplina')->findOrFail($questaoId);

        $this->authorize('update', $questao);

        // O nome é guardado antes: o `refresh()` dentro da action descarta
        // as relações aninhadas que já estavam carregadas.
        $disciplina = $questao->parte->disciplina->nome;
        $ordem = $questao->ordem;

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
                habilidade: $rascunho['habilidade'] ?? null,
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
            "Questão {$ordem} de {$disciplina} salva como rascunho. Nada foi enviado ainda.",
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
                    habilidade: $this->formulario[$questaoId]['habilidade'] ?? null,
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
        $questao = Questao::query()->with('parte.disciplina')->findOrFail($questaoId);

        $this->authorize('update', $questao);

        $disciplina = $questao->parte->disciplina->nome;
        $ordem = $questao->ordem;

        $rascunho = $this->formulario[$questaoId] ?? null;

        try {
            if ($rascunho !== null) {
                $salvar->executar(
                    questao: $questao,
                    autor: auth()->user(),
                    enunciado: $rascunho['enunciado'] ?: null,
                    alternativas: $rascunho['alternativas'],
                    peso: $rascunho['peso'] ?? null,
                    habilidade: $rascunho['habilidade'] ?? null,
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
            "Questão {$ordem} de {$disciplina} reenviada como versão {$reenviada->versao}. A coordenação vai analisar de novo.",
            'Correção enviada',
        );

        $this->carregarFormulario();
    }

    public function confirmarEnvio(int $parteId): void
    {
        $this->confirmandoEnvio = $parteId;
    }

    public function cancelarEnvio(): void
    {
        $this->confirmandoEnvio = null;
    }

    /** Envia uma parte — a cota de uma disciplina. */
    public function enviarParte(int $parteId, SalvarQuestaoAction $salvar, EnviarParteAction $enviar): void
    {
        $parte = SolicitacaoParte::query()
            ->with(['disciplina', 'solicitacao'])
            ->findOrFail($parteId);

        $this->authorize('responder', $parte);

        // Grava o que estiver na tela antes de enviar, sem avisar duas
        // vezes: quem manda a mensagem é o envio.
        $this->salvarTudo($salvar, avisar: false);

        $disciplina = $parte->disciplina->nome;

        try {
            $enviada = $enviar->executar($parte->refresh(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());
            $this->confirmandoEnvio = null;

            return;
        }

        $this->confirmandoEnvio = null;
        $this->carregarFormulario();

        $this->notificarSucesso($enviada->enviada_em_atraso
            ? "Questões de {$disciplina} enviadas. O envio ficou registrado como em atraso, mas foi aceito normalmente."
            : "Questões de {$disciplina} enviadas para análise dentro do prazo.",
            'Enviado para análise');
    }

    public function render(EnviarParteAction $enviar): View
    {
        $solicitacao = $this->solicitacao();
        $questoes = $this->questoes();

        // Em foco, só a disciplina da questão aparece.
        $partes = $this->questaoEmFoco === null
            ? $this->minhasPartes()
            : $this->minhasPartes()->whereIn('id', $questoes->pluck('solicitacao_parte_id'));

        return view('questoes.responder', [
            'solicitacao' => $solicitacao,
            'partes' => $partes,
            'questoesPorParte' => $questoes->groupBy('solicitacao_parte_id'),
            // Pendências e bloqueio são por parte: cada disciplina entrega
            // quando estiver pronta.
            'pendenciasPorParte' => $partes->mapWithKeys(
                fn (SolicitacaoParte $parte) => [$parte->getKey() => $enviar->pendenciasPorQuestao($parte)]
            ),
            'podeEditar' => $solicitacao->aceitaEnvio(),
            'emFoco' => $this->questaoEmFoco !== null,
            'linguagens' => LinguagemCodigo::opcoes(),
            // Habilidades já escritas na disciplina, para o professor
            // reusar a mesma redação em vez de criar uma variação.
            'habilidadesPorParte' => $partes->mapWithKeys(fn (SolicitacaoParte $parte) => [
                $parte->getKey() => Questao::habilidadesDaDisciplina($parte->disciplina_id),
            ]),
            // O que a coordenação devolveu, para o topo da tela avisar.
            'devolvidas' => $questoes
                ->filter(fn (Questao $questao) => $questao->status === StatusQuestao::Rejeitada)
                ->values(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Responder: '.$partes->map(fn (SolicitacaoParte $p) => $p->disciplina->nome)->join(', '),
            'subtitulo' => 'Turma '.$solicitacao->turma->nome
                .' · prazo '.$solicitacao->prazo->format('d/m/Y H:i')
                .($solicitacao->estaAtrasada() ? ' · atrasada' : ''),
        ]);
    }
}
