<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
        Vite::prefetch(concurrency: 3);
        Date::use(Carbon::class);

        /*
         * Terceira barreira do `ativo`: nenhuma autorização é concedida a
         * uma conta desativada, mesmo que algum fluxo alcance o Gate sem
         * passar pelo middleware (jobs, comandos, chamadas internas).
         */
        Gate::before(function (User $usuario) {
            return $usuario->ativo ? null : false;
        });
    }
}
