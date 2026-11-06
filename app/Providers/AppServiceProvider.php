<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ConfiguracaoDaEscola;
use App\Support\CriteriosDaAnalise;
use App\Support\SinalDeAvisos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Os critérios da análise vêm da configuração, num lugar só. O
         * container injeta o mesmo objeto na Action e na tela, de modo
         * que o número contra o qual um resultado foi medido é o número
         * que a tela mostra ao lado dele.
         */
        $this->app->singleton(CriteriosDaAnalise::class, fn () => CriteriosDaAnalise::daConfiguracao());
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

        // A configuração da escola só se altera pelo PAEET Admin: nome nos
        // documentos, aulas do dia e intervalos de todas as turmas.
        Gate::define('configurarEscola', fn (User $usuario) => $usuario->ehPaeetAdmin());

        // A Lixeira desfaz exclusões de qualquer pessoa: é do PAEET Admin.
        Gate::define('administrarLixeira', fn (User $usuario) => $usuario->ehPaeetAdmin());

        ConfiguracaoDaEscola::aplicar();

        /*
         * Todo aviso gravado no banco muda o contador do sino de quem o
         * recebe — venha de onde vier —, e só depois do commit. Fora de
         * transação, `afterCommit` roda na hora.
         */
        Event::listen(NotificationSent::class, function (NotificationSent $enviada) {
            if ($enviada->channel === 'database' && $enviada->notifiable instanceof User) {
                $id = $enviada->notifiable->getKey();

                DB::afterCommit(fn () => SinalDeAvisos::avisar([$id]));
            }
        });
    }
}
