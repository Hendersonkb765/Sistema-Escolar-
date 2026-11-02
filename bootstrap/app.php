<?php

use App\Http\Middleware\GarantirPerfil;
use App\Http\Middleware\GarantirSenhaPropria;
use App\Http\Middleware\GarantirUsuarioAtivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sem o grupo `web`: ver routes/sinal.php.
        then: fn () => Route::group([], base_path('routes/sinal.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ativo' => GarantirUsuarioAtivo::class,
            'senha-propria' => GarantirSenhaPropria::class,
            'perfil' => GarantirPerfil::class,
        ]);

        // Roda em toda requisição web: conta desativada perde o acesso
        // na hora, sem esperar o próximo login.
        $middleware->web(append: [
            GarantirUsuarioAtivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
