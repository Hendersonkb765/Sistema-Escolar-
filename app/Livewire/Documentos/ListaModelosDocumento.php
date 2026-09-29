<?php

namespace App\Livewire\Documentos;

use App\Actions\Documento\CompartilharModeloAction;
use App\Enums\StatusCompartilhamento;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\ComTabela;
use App\Livewire\Concerns\Notifica;
use App\Models\CompartilhamentoDeModelo;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaModelosDocumento extends Component
{
    use AuthorizesRequests;
    use ComTabela;
    use Notifica;

    #[Url(as: 'inativos', except: false)]
    public bool $mostrarInativos = false;

    /** O modelo cuja janela de compartilhamento está aberta. */
    public ?int $compartilhando = null;

    public ?int $destinatario_id = null;

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ModeloDocumento::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'created_at'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function updatedMostrarInativos(): void
    {
        $this->resetPage();
    }

    /** Desativar tira o modelo da lista de geração sem apagar o que já saiu. */
    public function alternarAtivo(int $id): void
    {
        $modelo = ModeloDocumento::query()->findOrFail($id);

        $this->authorize('update', $modelo);

        $modelo->update(['ativo' => ! $modelo->ativo]);

        $this->notificarSucesso($modelo->ativo
            ? 'Modelo reativado: volta a aparecer na geração de documentos.'
            : 'Modelo desativado: some da geração, e os documentos já gerados seguem válidos.');
    }

    public function abrirCompartilhamento(int $id): void
    {
        $modelo = ModeloDocumento::query()->findOrFail($id);

        $this->authorize('compartilhar', $modelo);

        $this->compartilhando = $id;
        $this->destinatario_id = null;
        $this->mensagem = '';
        $this->resetValidation();
    }

    public function fecharCompartilhamento(): void
    {
        $this->compartilhando = null;
        $this->destinatario_id = null;
        $this->mensagem = '';
        $this->resetValidation();
    }

    public function compartilhar(CompartilharModeloAction $acao): void
    {
        $modelo = ModeloDocumento::query()->findOrFail($this->compartilhando);

        $this->authorize('compartilhar', $modelo);

        $this->validate([
            'destinatario_id' => ['required', 'integer'],
            'mensagem' => ['nullable', 'string', 'max:255'],
        ], attributes: ['destinatario_id' => 'PAEET que vai receber']);

        $destinatario = User::query()->findOrFail($this->destinatario_id);

        try {
            $acao->executar($modelo, auth()->user(), $destinatario, $this->mensagem ?: null);
        } catch (RegraDeNegocioException $erro) {
            $this->addError('destinatario_id', $erro->getMessage());

            return;
        }

        $this->fecharCompartilhamento();

        $this->notificarSucesso(
            "Modelo enviado a {$destinatario->nome}. Ele entra na lista dele só se aceitar."
        );
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = ModeloDocumento::query()
            ->visivelPara($usuario)
            ->with(['eixo:id,nome', 'autor:id,nome', 'original:id,criado_por', 'original.autor:id,nome'])
            ->withCount([
                'compartilhamentos',
                'compartilhamentos as aceitos_count' => fn (Builder $q) => $q
                    ->where('status', StatusCompartilhamento::Aceito),
            ])
            ->when(! $this->mostrarInativos, fn (Builder $q) => $q->where('ativo', true))
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('descricao', 'like', $termo));
            });

        return view('documentos.lista', [
            'modelos' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'destinatarios' => $this->compartilhando === null
                ? collect()
                : app(CompartilharModeloAction::class)->destinatariosPossiveis($usuario),
            'aguardando' => CompartilhamentoDeModelo::query()
                ->recebidosPor($usuario)
                ->pendentes()
                ->count(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Documentos do aluno',
            'subtitulo' => 'Modelos reutilizáveis para gerar autorizações, declarações e fichas',
        ]);
    }
}
