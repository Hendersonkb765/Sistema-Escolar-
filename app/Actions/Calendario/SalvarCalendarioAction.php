<?php

namespace App\Actions\Calendario;

use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Models\BimestreLetivo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Grava as datas dos bimestres de um ano letivo, todas de uma vez.
 *
 * O formulário garante que cada bimestre tem início e fim, nessa ordem.
 * Aqui se decide o que só faz sentido olhando o ano inteiro: um bimestre
 * não começa antes de o anterior terminar. Bimestre em branco é apagado
 * — o ano pode estar só em parte cadastrado.
 */
class SalvarCalendarioAction
{
    /**
     * @param  array<int, array{inicio: string, fim: string}|null>  $datas  bimestre => datas, ou null para apagar
     */
    public function executar(string $periodoLetivo, array $datas): void
    {
        self::conferirSequencia($datas);

        DB::transaction(function () use ($periodoLetivo, $datas) {
            foreach ($datas as $numero => $par) {
                $chave = ['periodo_letivo' => $periodoLetivo, 'bimestre' => $numero];

                if ($par === null) {
                    // Um por um, e não `delete()` em massa: assim a
                    // auditoria registra o que saiu.
                    BimestreLetivo::query()->where($chave)->get()->each->delete();

                    continue;
                }

                BimestreLetivo::query()->updateOrCreate($chave, [
                    'inicio' => $par['inicio'],
                    'fim' => $par['fim'],
                ]);
            }
        });
    }

    /**
     * Um bimestre não começa antes de o anterior terminar. Vale para o
     * calendário da escola e para o próprio de uma turma.
     *
     * @param  array<int, array{inicio: string, fim: string}|null>  $datas
     */
    public static function conferirSequencia(array $datas): void
    {
        ksort($datas);

        $anterior = null;

        foreach ($datas as $numero => $par) {
            if ($par === null) {
                continue;
            }

            $bimestre = Bimestre::from($numero);
            $inicio = Carbon::parse($par['inicio']);

            if ($anterior !== null && $inicio->lte($anterior['fim'])) {
                throw RegraDeNegocioException::porque(
                    "O {$bimestre->rotulo()} começa em {$inicio->format('d/m/Y')}, "
                    ."antes do fim do {$anterior['bimestre']->rotulo()} ({$anterior['fim']->format('d/m/Y')})."
                );
            }

            $anterior = ['bimestre' => $bimestre, 'fim' => Carbon::parse($par['fim'])];
        }
    }
}
