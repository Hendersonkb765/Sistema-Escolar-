<?php

namespace App\Livewire\Usuarios;

use App\Enums\PerfilUsuario;
use App\Livewire\Concerns\ComTabela;
use App\Livewire\Concerns\Notifica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaUsuarios extends Component
{
    use AuthorizesRequests;
    use ComTabela;
    use Notifica;

    #[Url(as: 'perfil', except: '')]
    public string $filtroPerfil = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroSituacao = '';

    public ?int $confirmandoDesativacao = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'email', 'perfil', 'ativo', 'created_at'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function updatedFiltroPerfil(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroSituacao(): void
    {
        $this->resetPage();
    }

    /**
     * Alterna o acesso de uma conta. Desativar corta a sessão em curso na
     * próxima requisição (middleware GarantirUsuarioAtivo).
     */
    public function alternarAtivacao(int $usuarioId): void
    {
        $alvo = User::query()->findOrFail($usuarioId);

        $this->authorize('alternarAtivacao', $alvo);

        $alvo->update(['ativo' => ! $alvo->ativo]);

        $this->confirmandoDesativacao = null;

        $this->notificarSucesso($alvo->ativo
            ? "Acesso de {$alvo->nome} reativado."
            : "Acesso de {$alvo->nome} desativado. A sessão dele cai na próxima requisição.");
    }

    public function confirmarDesativacao(int $usuarioId): void
    {
        $this->confirmandoDesativacao = $usuarioId;
    }

    public function cancelarConfirmacao(): void
    {
        $this->confirmandoDesativacao = null;
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = User::query()
            ->with('eixos:id,nome')
            ->where(fn (Builder $q) => $this->restringirAoEscopo($q, $usuario))
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('email', 'like', $termo));
            })
            ->when($this->filtroPerfil !== '', fn (Builder $q) => $q->where('perfil', $this->filtroPerfil))
            ->when($this->filtroSituacao !== '', fn (Builder $q) => $q->where('ativo', $this->filtroSituacao === 'ativos'));

        return view('usuarios.lista', [
            'usuarios' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'perfis' => PerfilUsuario::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Usuários',
            'subtitulo' => 'Contas são criadas apenas aqui — não existe autocadastro',
        ]);
    }

    /**
     * Um usuário de gestão vê as contas que tocam seus Eixos, as que ele
     * mesmo criou e a própria conta.
     */
    protected function restringirAoEscopo(Builder $consulta, User $usuario): Builder
    {
        return $consulta
            ->whereHas('eixos', fn (Builder $q) => $q->whereIn('eixos.id', $usuario->eixoIds()))
            ->orWhere('criado_por', $usuario->getKey())
            ->orWhere('usuarios.id', $usuario->getKey());
    }
}
