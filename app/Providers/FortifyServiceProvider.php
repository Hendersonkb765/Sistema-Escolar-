<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Regra de ouro: não existe cadastro público.
         *
         * `Fortify::createUsersUsing()` NÃO é registrado e a feature
         * `registration` foi removida de config/fortify.php — nem a rota
         * nem a action de registro existem. Contas nascem apenas no painel
         * de Usuários, criadas por um PAEET Admin ou por um PAEET.
         */
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->autenticacao();
        $this->views();
        $this->limitesDeTentativa();
    }

    /**
     * Login só acontece se o usuário existir E estiver ativo. A conferência
     * se repete a cada request no middleware GarantirUsuarioAtivo, de modo
     * que desativar uma conta corta o acesso imediatamente.
     */
    protected function autenticacao(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $usuario = User::query()
                ->where('email', (string) $request->input(Fortify::username()))
                ->first();

            if (! $usuario || ! Hash::check((string) $request->input('password'), $usuario->password)) {
                return null;
            }

            if (! $usuario->ativo) {
                activity('autenticacao')
                    ->performedOn($usuario)
                    ->withProperties(['ip' => $request->ip(), 'motivo' => 'conta_inativa'])
                    ->log('Tentativa de login bloqueada: conta inativa');

                throw ValidationException::withMessages([
                    Fortify::username() => __('Esta conta está inativa. Procure a coordenação PAEET.'),
                ]);
            }

            $usuario->forceFill(['ultimo_login_em' => now()])->saveQuietly();

            return $usuario;
        });
    }

    protected function views(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.esqueci-senha'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.redefinir-senha', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirmar-senha'));
        Fortify::twoFactorChallengeView(fn () => view('auth.dois-fatores'));
    }

    protected function limitesDeTentativa(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $chave = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($chave);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
