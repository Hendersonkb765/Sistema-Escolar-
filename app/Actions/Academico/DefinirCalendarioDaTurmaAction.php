<?php

namespace App\Actions\Academico;

use App\Actions\Calendario\SalvarCalendarioAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;

/**
 * Dá à turma um calendário próprio, ou a devolve ao da escola.
 *
 * O calendário próprio substitui o da escola por inteiro: o bimestre
 * deixado em branco não volta a valer pelo da escola, ele fica sem data
 * — é a turma que funciona de outro jeito, e misturar os dois daria um
 * ano que ninguém cadastrou.
 */
class DefinirCalendarioDaTurmaAction
{
    /**
     * @param  array<int, array{inicio: string, fim: string}|null>  $datas  bimestre => datas, ou null
     */
    public function executar(Turma $turma, array $datas): void
    {
        $preenchidas = array_filter($datas);

        if ($preenchidas === []) {
            throw RegraDeNegocioException::porque(
                'Informe as datas de ao menos um bimestre, ou volte ao calendário da escola.'
            );
        }

        SalvarCalendarioAction::conferirSequencia($datas);

        DB::transaction(function () use ($turma, $preenchidas) {
            $this->apagar($turma);

            foreach ($preenchidas as $numero => $par) {
                $turma->bimestresProprios()->create([
                    'bimestre' => $numero,
                    'inicio' => $par['inicio'],
                    'fim' => $par['fim'],
                ]);
            }
        });
    }

    public function voltarAoDaEscola(Turma $turma): void
    {
        DB::transaction(fn () => $this->apagar($turma));
    }

    /** Um por um, para a auditoria registrar o que saiu. */
    private function apagar(Turma $turma): void
    {
        $turma->bimestresProprios()->get()->each->delete();
    }
}
