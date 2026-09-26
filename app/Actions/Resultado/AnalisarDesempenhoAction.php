<?php

namespace App\Actions\Resultado;

use App\Models\Prova;
use App\Models\RespostaAluno;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Onde a turma teve dificuldade.
 *
 * A leitura por número de questão diz *onde* erraram; a leitura por
 * habilidade diz *o quê* — e é essa que permite intervir. Duas questões
 * de habilidades diferentes com o mesmo índice de acerto pedem aulas
 * diferentes.
 *
 * A habilidade vem do snapshot da prova, não da questão original: uma
 * análise de prova antiga tem de continuar dizendo o que aquela questão
 * avaliava na época.
 */
class AnalisarDesempenhoAction
{
    /** Abaixo disto a turma não domina o conteúdo. */
    public const LIMITE_DE_ATENCAO = 60.0;

    /**
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porHabilidade(Collection $provas, User $leitor, ?int $disciplinaId = null): Collection
    {
        return $this->respostas($provas, $leitor, $disciplinaId)
            ->groupBy(fn (RespostaAluno $resposta) => self::chave($resposta->provaQuestao->habilidade_snapshot))
            ->map(function (Collection $respostas) {
                $questao = $respostas->first()->provaQuestao;

                return [
                    'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
                    'disciplinas' => $respostas
                        ->map(fn (RespostaAluno $r) => $r->provaQuestao->disciplina->nome)
                        ->unique()->sort()->values()->all(),
                    'questoes' => $respostas
                        ->map(fn (RespostaAluno $r) => $r->provaQuestao->numero)
                        ->unique()->sort()->values()->all(),
                    ...$this->contagem($respostas),
                ];
            })
            // A que mais precisa de atenção primeiro.
            ->sortBy('percentual')
            ->values();
    }

    /**
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porQuestao(Collection $provas, User $leitor, ?int $disciplinaId = null): Collection
    {
        return $this->respostas($provas, $leitor, $disciplinaId)
            ->groupBy('prova_questao_id')
            ->map(function (Collection $respostas) {
                $questao = $respostas->first()->provaQuestao;

                return [
                    'numero' => $questao->numero,
                    'prova' => $questao->prova->titulo,
                    'disciplina' => $questao->disciplina->nome,
                    'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
                    'peso' => (float) $questao->peso,
                    'enunciado' => $questao->enunciado_snapshot,
                    ...$this->contagem($respostas),
                ];
            })
            ->sortBy('percentual')
            ->values();
    }

    /**
     * As respostas das provas escolhidas, já com o que a análise lê.
     *
     * O escopo é o do leitor: o professor enxerga as questões dele, a
     * coordenação as do seu Eixo.
     *
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, RespostaAluno>
     */
    protected function respostas(Collection $provas, User $leitor, ?int $disciplinaId): Collection
    {
        if ($provas->isEmpty()) {
            return collect();
        }

        $visiveis = $provas->filter(fn (Prova $prova) => Gate::forUser($leitor)->allows('view', $prova));

        if ($visiveis->isEmpty()) {
            return collect();
        }

        return RespostaAluno::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $visiveis->modelKeys()))
            ->when(
                ! $leitor->ehGestao(),
                // O professor analisa o que é dele.
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('professor_id', $leitor->getKey()))
            )
            ->when(
                $disciplinaId !== null,
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('disciplina_id', $disciplinaId))
            )
            ->with(['provaQuestao:id,prova_id,numero,disciplina_id,habilidade_snapshot,peso,enunciado_snapshot,professor_id',
                'provaQuestao.disciplina:id,nome', 'provaQuestao.prova:id,titulo,turma_id'])
            ->get();
    }

    /**
     * @param  Collection<int, RespostaAluno>  $respostas
     * @return array{respostas: int, acertos: int, erros: int, percentual: float, atencao: bool}
     */
    protected function contagem(Collection $respostas): array
    {
        $total = $respostas->count();
        $acertos = $respostas->where('acertou', true)->count();
        $percentual = $total === 0 ? 0.0 : round(100 * $acertos / $total, 1);

        return [
            'respostas' => $total,
            'acertos' => $acertos,
            'erros' => $total - $acertos,
            'percentual' => $percentual,
            'atencao' => $percentual < self::LIMITE_DE_ATENCAO,
        ];
    }

    /** Agrupa "Interpretar Gráficos" e "interpretar  gráficos" juntas. */
    public static function chave(?string $habilidade): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $habilidade)) ?? '');
    }
}
