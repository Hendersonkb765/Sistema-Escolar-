<?php

namespace Database\Factories;

use App\Enums\PerfilUsuario;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $senhaPadrao = null;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$senhaPadrao ??= Hash::make('senha-de-teste'),
            'perfil' => PerfilUsuario::Professor,
            'ativo' => true,
            // Conta em uso: quem testa outra coisa não deve esbarrar na
            // tela de primeira senha. Use `semSenhaPropria()` para o
            // caso do primeiro acesso.
            'senha_definida_em' => now(),
            'telefone' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /** Conta recém-criada: a senha em uso é a que a coordenação definiu. */
    public function semSenhaPropria(): static
    {
        return $this->state(fn () => ['senha_definida_em' => null]);
    }

    public function paeetAdmin(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::PaeetAdmin]);
    }

    public function paeet(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::Paeet]);
    }

    public function professor(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::Professor]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    /** Vincula o usuário aos Eixos indicados após a criação. */
    public function noEixo(Eixo|int ...$eixos): static
    {
        return $this->afterCreating(function (User $usuario) use ($eixos) {
            $usuario->eixos()->syncWithoutDetaching(
                collect($eixos)->map(fn ($eixo) => $eixo instanceof Eixo ? $eixo->getKey() : $eixo)->all()
            );
            $usuario->esquecerEscopo();
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
