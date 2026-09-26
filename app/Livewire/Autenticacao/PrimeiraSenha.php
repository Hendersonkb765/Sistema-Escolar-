<?php

namespace App\Livewire\Autenticacao;

use App\Actions\Fortify\PasswordValidationRules;
use App\Livewire\Concerns\Notifica;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * O primeiro acesso: o dono da conta escolhe a própria senha.
 *
 * Até aqui a senha em uso é a provisória, que quem criou a conta
 * conheceu e entregou. Escolher a sua encerra isso — e é a mesma tela
 * que aparece quando a coordenação redefine a senha de alguém, porque
 * uma senha definida por outra pessoa é sempre provisória.
 */
class PrimeiraSenha extends Component
{
    use Notifica;
    use PasswordValidationRules;

    public string $senha = '';

    public string $senha_confirmation = '';

    public function mount(): void
    {
        // Quem já escolheu a sua não tem o que fazer aqui.
        if (! auth()->user()->precisaDefinirSenha()) {
            $this->redirectRoute('painel', navigate: true);
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return ['senha' => $this->passwordRules()];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['senha' => 'senha'];
    }

    public function definir(): void
    {
        $usuario = auth()->user();

        $this->validate();

        if (Hash::check($this->senha, $usuario->password)) {
            $this->addError('senha', 'Escolha uma senha diferente da provisória que você recebeu.');

            return;
        }

        $usuario->forceFill([
            'password' => Hash::make($this->senha),
            'senha_definida_em' => now(),
        ])->save();

        // A senha mudou: a sessão recomeça, e qualquer outra aberta com
        // a provisória deixa de valer.
        session()->regenerate();

        activity('autenticacao')
            ->performedOn($usuario)
            ->causedBy($usuario)
            ->log('Senha definida no primeiro acesso');

        $this->flashSucesso('Senha definida. A provisória que você recebeu não vale mais.');

        $this->redirectRoute('painel', navigate: false);
    }

    public function render(): View
    {
        return view('auth.primeira-senha')
            ->layout('components.layouts.auth', [
                'titulo' => 'Escolha a sua senha',
                'subtitulo' => 'Antes de continuar, defina a senha que só você conhece',
            ]);
    }
}
