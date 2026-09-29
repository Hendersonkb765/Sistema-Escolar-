<?php

namespace App\Livewire\Documentos;

use App\Actions\Documento\ResponderCompartilhamentoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\CompartilhamentoDeModelo;
use App\Support\CamposDoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Os modelos que me ofereceram e os que eu ofereci.
 *
 * Quem recebeu precisa ver o texto antes de decidir — aceitar às cegas um
 * documento que vai para a família de um aluno não é decisão nenhuma. A
 * pré-visualização sai daqui, autorizada pelo compartilhamento; o modelo
 * em si continua sendo de outro Eixo e dando 403 pela rota dele.
 */
class ListaCompartilhamentos extends Component
{
    use AuthorizesRequests;
    use Notifica;
    use WithPagination;

    #[Url(as: 'caixa', except: 'recebidos')]
    public string $caixa = 'recebidos';

    /** O compartilhamento cuja prévia está aberta. */
    public ?int $espiando = null;

    public function mount(): void
    {
        $this->authorize('viewAny', CompartilhamentoDeModelo::class);
    }

    public function trocarCaixa(string $caixa): void
    {
        $this->caixa = in_array($caixa, ['recebidos', 'enviados'], true) ? $caixa : 'recebidos';
        $this->espiando = null;
        $this->resetPage();
    }

    public function espiar(int $id): void
    {
        $this->encontrar($id);

        $this->espiando = $this->espiando === $id ? null : $id;
    }

    public function aceitar(int $id, ResponderCompartilhamentoAction $acao): void
    {
        $compartilhamento = $this->encontrar($id);

        try {
            $copia = $acao->aceitar($compartilhamento, auth()->user());
        } catch (RegraDeNegocioException $erro) {
            $this->notificarErro($erro->getMessage());

            return;
        }

        $this->espiando = null;

        $this->notificarSucesso(
            "\"{$copia->nome}\" entrou nos seus modelos. A cópia é sua: pode editar sem afetar o original."
        );
    }

    public function recusar(int $id, ResponderCompartilhamentoAction $acao): void
    {
        $compartilhamento = $this->encontrar($id);

        try {
            $acao->recusar($compartilhamento, auth()->user());
        } catch (RegraDeNegocioException $erro) {
            $this->notificarErro($erro->getMessage());

            return;
        }

        $this->espiando = null;

        $this->notificarSucesso('Modelo recusado. Ele não entra na sua lista.');
    }

    /** O texto do modelo oferecido, com dados de exemplo. */
    public function previa(CompartilhamentoDeModelo $compartilhamento): HtmlString
    {
        $modelo = $compartilhamento->loadMissing('modelo')->modelo;

        return CamposDoDocumento::render($modelo->corpo, CamposDoDocumento::contexto(
            aluno: null,
            numero: 7,
            turma: '1 A',
            periodo: 1,
            periodoLetivo: (string) now()->year,
            curso: 'Curso de exemplo',
            eixo: 'Eixo de exemplo',
            instituicao: (string) config('instituicao.nome'),
            extra: [
                'aluno.nome' => 'Marina Alves de Souza',
                'aluno.ra' => '20261001',
                'lista_de_alunos' => view('documentos.previa-da-lista')->render(),
            ],
        ));
    }

    /**
     * Busca sem filtrar pelo dono e deixa a Policy responder.
     *
     * Filtrar antes devolveria 404 para quem não é das duas pontas, e a
     * convenção do projeto é 403 — a decisão de quem pode o quê mora na
     * Policy, num lugar só, e não espalhada pelos escopos de consulta.
     */
    protected function encontrar(int $id): CompartilhamentoDeModelo
    {
        $compartilhamento = CompartilhamentoDeModelo::query()
            ->with(['modelo', 'remetente:id,nome', 'destinatario:id,nome'])
            ->findOrFail($id);

        $this->authorize('view', $compartilhamento);

        return $compartilhamento;
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = CompartilhamentoDeModelo::query()
            ->with([
                'modelo:id,nome,descricao,tipo,corpo,criado_por',
                'modelo.autor:id,nome',
                'remetente:id,nome,email',
                'destinatario:id,nome,email',
                'copia:id,nome',
            ])
            ->when(
                $this->caixa === 'recebidos',
                fn ($q) => $q->recebidosPor($usuario),
                fn ($q) => $q->enviadosPor($usuario),
            )
            ->orderByDesc('created_at');

        return view('documentos.compartilhamentos', [
            'compartilhamentos' => $consulta->paginate(15),
            'pendentes' => CompartilhamentoDeModelo::query()
                ->recebidosPor($usuario)->pendentes()->count(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Modelos compartilhados',
            'subtitulo' => 'O que me ofereceram e o que eu ofereci',
        ]);
    }
}
