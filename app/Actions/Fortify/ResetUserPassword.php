<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Redefinição de senha nunca é auto-executável: o link só é emitido para
 * conta existente e ativa (ver User::sendPasswordResetNotification) e a
 * troca em si é recusada se a conta tiver sido desativada nesse meio-tempo.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        if (! $user->ativo) {
            throw ValidationException::withMessages([
                'email' => __('Esta conta está inativa. Procure a coordenação PAEET.'),
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ], [], ['password' => 'senha'])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'senha_definida_em' => now(),
        ])->save();

        activity('autenticacao')
            ->performedOn($user)
            ->log('Senha redefinida pelo próprio usuário');
    }
}
