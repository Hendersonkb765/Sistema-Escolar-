<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum EventoHistorico: string implements Rotulavel
{
    use DescreveOpcoes;

    case TurmaCriada = 'turma_criada';
    case TurmaAvancoAno = 'turma_avanco_ano';
    case TurmaAlterada = 'turma_alterada';
    case TurmaEncerrada = 'turma_encerrada';
    case AlunoMatriculado = 'aluno_matriculado';
    case AlunoTrocaTurma = 'aluno_troca_turma';
    case AlunoStatusAlterado = 'aluno_status_alterado';

    public function rotulo(): string
    {
        return match ($this) {
            self::TurmaCriada => 'Turma criada',
            self::TurmaAvancoAno => 'Avanço de ano',
            self::TurmaAlterada => 'Turma alterada',
            self::TurmaEncerrada => 'Turma encerrada',
            self::AlunoMatriculado => 'Aluno matriculado',
            self::AlunoTrocaTurma => 'Troca de turma',
            self::AlunoStatusAlterado => 'Status alterado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::TurmaCriada, self::AlunoMatriculado => 'verde',
            self::TurmaAvancoAno, self::AlunoTrocaTurma => 'azul',
            self::TurmaAlterada, self::AlunoStatusAlterado => 'amarelo',
            self::TurmaEncerrada => 'cinza',
        };
    }
}
