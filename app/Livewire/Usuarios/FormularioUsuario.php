<?php

namespace App\Livewire\Usuarios;

use App\Enums\PerfilUsuario;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Criação e edição de contas. Este é o único caminho pelo qual um usuário
 * passa a existir no sistema.
 *
 * Um PAEET que também leciona não ganha uma segunda conta: o vínculo
 * docente é registrado em `professor_disciplina`, independente do perfil.
 */
class FormularioUsuario extends Component
{
    use AuthorizesRequests;

    public ?User $usuario = null;

    public string $nome = '';

    public string $email = '';

    public string $telefone = '';

    public string $perfil = PerfilUsuario::Professor->value;

    public bool $ativo = true;

    public string $senha = '';

    public string $senha_confirmation = '';

    /** @var array<int, int> */
    public array $eixosSelecionados = [];

    /** @var array<int, int> */
    public array $disciplinasSelecionadas = [];

    public bool $definirSenhaManualmente = false;

    public ?string $senhaGerada = null;

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->authorize('update', $usuario);

            $this->usuario = $usuario;
            $this->nome = $usuario->nome;
            $this->email = $usuario->email;
            $this->telefone = (string) $usuario->telefone;
            $this->perfil = $usuario->perfil->value;
            $this->ativo = $usuario->ativo;
            $this->eixosSelecionados = $usuario->eixos()->pluck('eixos.id')->all();
            $this->disciplinasSelecionadas = $usuario->vinculosDocentes()
                ->where('ativo', true)
                ->pluck('disciplina_id')
                ->unique()
                ->values()
                ->all();

            return;
        }

        $this->authorize('create', User::class);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $autor = auth()->user();

        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('usuarios', 'email')->ignore($this->usuario?->getKey()),
            ],
            'telefone' => ['nullable', 'string', 'max:30'],
            'perfil' => [
                'required',
                Rule::in($this->perfisPermitidos($autor)),
            ],
            'ativo' => ['boolean'],
            'senha' => [
                $this->exigeSenha() ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            // Só é possível vincular a Eixos que o próprio autor enxerga.
            'eixosSelecionados' => ['array'],
            'eixosSelecionados.*' => [
                Rule::exists('eixos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('id', $autor->eixoIds())
                ),
            ],
            'disciplinasSelecionadas' => ['array'],
            // A disciplina pertence a um curso, e o curso a um eixo:
            // só vale vincular o que está no escopo de quem edita.
            'disciplinasSelecionadas.*' => [
                Rule::exists('disciplinas', 'id')->where(
                    fn ($consulta) => $consulta->whereIn(
                        'curso_id',
                        Curso::query()->whereIn('eixo_id', $autor->eixoIds())->select('id')
                    )
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'nome' => 'nome',
            'email' => 'e-mail',
            'telefone' => 'telefone',
            'perfil' => 'perfil',
            'senha' => 'senha',
            'eixosSelecionados' => 'eixos',
            'eixosSelecionados.*' => 'eixo',
            'disciplinasSelecionadas' => 'disciplinas',
            'disciplinasSelecionadas.*' => 'disciplina',
        ];
    }

    public function salvar(): void
    {
        $autor = auth()->user();
        $perfilEscolhido = PerfilUsuario::tryFrom($this->perfil);

        // Segunda checagem, agora sobre o perfil pretendido: um PAEET
        // nunca cria outro PAEET, e ninguém cria PAEET Admin pela tela.
        if ($this->usuario === null) {
            $this->authorize('create', User::class);

            abort_unless(
                $perfilEscolhido !== null
                    && app(UserPolicy::class)->criarComPerfil($autor, $perfilEscolhido),
                403
            );
        } else {
            $this->authorize('update', $this->usuario);
        }

        $dados = $this->validate();

        $senhaFinal = $this->resolverSenha();

        DB::transaction(function () use ($dados, $senhaFinal, $autor) {
            if ($this->usuario === null) {
                $this->usuario = User::create([
                    'nome' => $dados['nome'],
                    'email' => $dados['email'],
                    'telefone' => $dados['telefone'] ?: null,
                    'perfil' => $dados['perfil'],
                    'ativo' => $this->ativo,
                    'password' => $senhaFinal,
                    'criado_por' => $autor->getKey(),
                ]);
            } else {
                $this->usuario->fill([
                    'nome' => $dados['nome'],
                    'email' => $dados['email'],
                    'telefone' => $dados['telefone'] ?: null,
                    'ativo' => $this->ativo,
                ]);

                // Trocar o perfil de alguém exige a mesma alçada de criá-lo.
                if ($this->usuario->perfil->value !== $dados['perfil']
                    && app(UserPolicy::class)->criarComPerfil($autor, PerfilUsuario::from($dados['perfil']))) {
                    $this->usuario->perfil = PerfilUsuario::from($dados['perfil']);
                }

                if ($senhaFinal !== null) {
                    $this->usuario->password = $senhaFinal;
                }

                $this->usuario->save();
            }

            $this->usuario->eixos()->sync($this->eixosSelecionados);
            $this->sincronizarVinculosDocentes();
            $this->usuario->esquecerEscopo();
        });

        session()->flash('sucesso', $this->senhaGerada !== null
            ? "Usuário criado. Senha provisória: {$this->senhaGerada}"
            : 'Usuário salvo com sucesso.');

        $this->redirectRoute('usuarios.index', navigate: true);
    }

    /**
     * Mantém um vínculo docente por disciplina, sem turma específica.
     * Vínculos retirados são desativados, nunca apagados: o histórico de
     * quem lecionou o quê precisa sobreviver.
     */
    protected function sincronizarVinculosDocentes(): void
    {
        $atuais = $this->usuario->vinculosDocentes()->get();

        foreach ($atuais as $vinculo) {
            $vinculo->update([
                'ativo' => in_array($vinculo->disciplina_id, $this->disciplinasSelecionadas, true),
            ]);
        }

        $jaExistentes = $atuais->pluck('disciplina_id')->all();

        foreach (array_diff($this->disciplinasSelecionadas, $jaExistentes) as $disciplinaId) {
            $this->usuario->vinculosDocentes()->create([
                'disciplina_id' => $disciplinaId,
                'turma_id' => null,
                'ativo' => true,
            ]);
        }
    }

    protected function resolverSenha(): ?string
    {
        if ($this->senha !== '') {
            return Hash::make($this->senha);
        }

        if ($this->usuario !== null) {
            return null;
        }

        $this->senhaGerada = Str::password(12, symbols: false);

        return Hash::make($this->senhaGerada);
    }

    protected function exigeSenha(): bool
    {
        return $this->usuario === null && $this->definirSenhaManualmente;
    }

    /** @return array<int, string> */
    protected function perfisPermitidos(User $autor): array
    {
        $politica = app(UserPolicy::class);

        return collect(PerfilUsuario::cases())
            ->filter(fn (PerfilUsuario $perfil) => $politica->criarComPerfil($autor, $perfil)
                || $this->usuario?->perfil === $perfil)
            ->map(fn (PerfilUsuario $perfil) => $perfil->value)
            ->values()
            ->all();
    }

    public function render(): View
    {
        $autor = auth()->user();

        return view('usuarios.formulario', [
            'eixosDisponiveis' => Eixo::query()->visivelPara($autor)->orderBy('nome')->get(),
            'disciplinasDisponiveis' => Disciplina::query()
                ->visivelPara($autor)
                ->with('curso:id,nome,eixo_id')
                ->orderBy('curso_id')
                ->orderBy('periodo')
                ->orderBy('nome')
                ->get(),
            'perfisDisponiveis' => collect($this->perfisPermitidos($autor))
                ->mapWithKeys(fn (string $valor) => [$valor => PerfilUsuario::from($valor)->rotulo()])
                ->all(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->usuario === null ? 'Novo usuário' : 'Editar usuário',
            'subtitulo' => $this->usuario?->email,
        ]);
    }
}
