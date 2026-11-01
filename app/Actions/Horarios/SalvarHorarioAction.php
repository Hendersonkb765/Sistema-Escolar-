<?php

namespace App\Actions\Horarios;

use App\Enums\DiaDaSemana;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Disciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Põe uma aula no quadro de horários da turma, ou altera uma que já
 * está lá.
 *
 * O formulário garante o formato (dia válido, fim depois do início).
 * Aqui fica o que depende do resto do quadro, e cada recusa diz o que
 * está no caminho:
 *
 * - a disciplina é do período em que a turma está;
 * - o professor tem vínculo com a disciplina;
 * - a turma não tem duas aulas na mesma hora;
 * - o professor não está em duas turmas na mesma hora. Só contam as
 *   turmas do mesmo ano letivo: o quadro do ano passado não prende
 *   ninguém.
 */
class SalvarHorarioAction
{
    /**
     * @param  array{dia: DiaDaSemana, aula_numero: int, hora_inicio: string, hora_fim: string, disciplina_id: int, professor_id: int}  $dados
     */
    public function executar(Turma $turma, array $dados, ?QuadroHorario $existente = null): QuadroHorario
    {
        $disciplina = Disciplina::query()->findOrFail($dados['disciplina_id']);
        $professor = User::query()->findOrFail($dados['professor_id']);
        $intervalo = "{$dados['hora_inicio']}–{$dados['hora_fim']}";

        $doPeriodo = $turma->disciplinasDoPeriodo()->pluck('disciplina_id')->all();

        if (! in_array($disciplina->id, $doPeriodo, true)) {
            throw RegraDeNegocioException::porque(
                "{$disciplina->nome} não faz parte do {$turma->periodo}º período da turma {$turma->nome}."
            );
        }

        $temVinculo = $professor->vinculosDocentes()
            ->where('disciplina_id', $disciplina->id)
            ->where('ativo', true)
            ->where(fn (Builder $q) => $q->whereNull('turma_id')->orWhere('turma_id', $turma->id))
            ->exists();

        if (! $temVinculo) {
            throw RegraDeNegocioException::porque(
                "{$professor->nome} não tem vínculo com {$disciplina->nome}. O vínculo é feito no cadastro do usuário."
            );
        }

        $outras = QuadroHorario::query()
            ->when($existente, fn (Builder $q) => $q->whereKeyNot($existente->getKey()))
            ->where('dia_semana', $dados['dia']);

        $mesmaAula = (clone $outras)
            ->with('disciplina:id,nome', 'professor:id,nome')
            ->where('turma_id', $turma->id)
            ->where('aula_numero', $dados['aula_numero'])
            ->first();

        if ($mesmaAula !== null) {
            throw RegraDeNegocioException::porque(
                "A {$mesmaAula->descricaoDaAula()} da turma {$turma->nome} já é "
                ."{$mesmaAula->disciplina->nome}, com {$mesmaAula->professor->nome}."
            );
        }

        $sobrepoe = fn (Builder $q) => $q
            ->where('hora_inicio', '<', $dados['hora_fim'])
            ->where('hora_fim', '>', $dados['hora_inicio']);

        $naTurma = (clone $outras)
            ->where('turma_id', $turma->id)
            ->where($sobrepoe)
            ->first();

        if ($naTurma !== null) {
            throw RegraDeNegocioException::porque(
                "O horário {$intervalo} se sobrepõe à {$naTurma->descricaoDaAula()} "
                ."da turma {$turma->nome} ({$naTurma->intervalo()})."
            );
        }

        $doProfessor = (clone $outras)
            ->with('turma:id,nome')
            ->where('professor_id', $professor->id)
            ->where('turma_id', '!=', $turma->id)
            ->whereHas('turma', fn (Builder $q) => $q->where('periodo_letivo', $turma->periodo_letivo))
            ->where($sobrepoe)
            ->first();

        if ($doProfessor !== null) {
            throw RegraDeNegocioException::porque(
                "{$professor->nome} já dá aula na turma {$doProfessor->turma->nome} na "
                .mb_strtolower($doProfessor->dia_semana->rotulo()).", {$doProfessor->intervalo()}."
            );
        }

        $campos = [
            'turma_id' => $turma->id,
            'dia_semana' => $dados['dia'],
            'aula_numero' => $dados['aula_numero'],
            'hora_inicio' => $dados['hora_inicio'],
            'hora_fim' => $dados['hora_fim'],
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor->id,
        ];

        if ($existente !== null) {
            $existente->update($campos);

            return $existente;
        }

        return QuadroHorario::query()->create($campos);
    }
}
