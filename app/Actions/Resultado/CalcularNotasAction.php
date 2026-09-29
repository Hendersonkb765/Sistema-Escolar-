<?php

namespace App\Actions\Resultado;

use App\Models\NotaDisciplina;
use App\Models\RespostaAluno;
use App\Models\ResultadoAluno;

/**
 * A nota de cada disciplina, de 0 a 10.
 *
 * O peso é **da disciplina**, não da prova inteira: uma prova com
 * Lógica, Redes e Banco de Dados produz três notas independentes, cada
 * uma medida contra a soma dos pesos da sua própria disciplina.
 *
 *     nota = 10 × (soma dos pesos acertados ÷ soma dos pesos da disciplina)
 *
 * Somar questões de disciplinas diferentes numa nota só apagaria
 * exatamente a informação que a escola quer ver.
 */
class CalcularNotasAction
{
    public function executar(ResultadoAluno $resultado): ResultadoAluno
    {
        $porDisciplina = $resultado
            ->respostas()
            ->with('provaQuestao:id,disciplina_id')
            ->get()
            ->groupBy(fn (RespostaAluno $resposta) => $resposta->provaQuestao->disciplina_id);

        $resultado->notas()->delete();

        foreach ($porDisciplina as $disciplinaId => $respostas) {
            $total = (float) $respostas->sum('peso');
            $acertos = (float) $respostas->where('acertou', true)->sum('peso');

            NotaDisciplina::create([
                'resultado_aluno_id' => $resultado->getKey(),
                'disciplina_id' => $disciplinaId,
                'soma_pesos_acertos' => $acertos,
                'soma_pesos_total' => $total,
                'nota' => self::nota($acertos, $total),
            ]);
        }

        return $resultado->refresh();
    }

    /** Disciplina sem peso nenhum não vira divisão por zero. */
    public static function nota(float $acertos, float $total): float
    {
        return $total <= 0 ? 0.0 : round(10 * $acertos / $total, 2);
    }
}
