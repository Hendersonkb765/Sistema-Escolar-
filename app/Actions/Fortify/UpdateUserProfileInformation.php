<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * O próprio usuário ajusta nome, e-mail e telefone. Perfil, ativação e
 * vínculos ficam fora daqui: são atribuições da gestão.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email')->ignore($user->id),
            ],
            'telefone' => ['nullable', 'string', 'max:30'],
        ], [], [
            'nome' => 'nome',
            'email' => 'e-mail',
            'telefone' => 'telefone',
        ])->validateWithBag('updateProfileInformation');

        $user->forceFill([
            'nome' => $input['nome'],
            'email' => $input['email'],
            'telefone' => $input['telefone'] ?? null,
        ])->save();
    }
}
