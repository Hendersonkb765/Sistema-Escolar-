<?php

use App\Http\Controllers\SinalDeAvisosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas sem banco de dados
|--------------------------------------------------------------------------
|
| Fora do grupo `web`: sem sessão, sem cookie, sem usuário — e, por isso,
| sem conexão com o banco. É o que permite consultar algo a cada poucos
| segundos sem gastar a cota de conexões por hora da hospedagem.
|
*/

Route::get('/avisos/{usuario}/sinal', SinalDeAvisosController::class)
    ->whereNumber('usuario')
    ->name('avisos.sinal');
