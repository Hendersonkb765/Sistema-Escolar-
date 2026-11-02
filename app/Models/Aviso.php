<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Um aviso no sino: a notificação do Laravel, com a hora gravada em
 * microssegundos.
 *
 * O id da notificação é um UUID sorteado. Com a hora só até o segundo,
 * dois avisos do mesmo segundo empatam, e o MySQL os devolve na ordem do
 * UUID: o sino listaria e mostraria em balão uma sequência diferente a
 * cada vez. O SQLite devolve na ordem de inserção, e por isso o empate
 * não aparecia aqui.
 */
class Aviso extends DatabaseNotification
{
    protected $dateFormat = 'Y-m-d H:i:s.u';
}
