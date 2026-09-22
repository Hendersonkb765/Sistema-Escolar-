<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Disciplina;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Abre uma solicitação de questões a um professor.
 *
 * Cada questão pedida vira um item e uma questão em rascunho ligada a
 * ele — o professor abre a solicitação e encontra os campos prontos. O
 * peso de cada questão é escolhido por ele ao escrevê-la.
 */
class CriarSolicitacaoAction
{
    public function executar(
        User $autor,
        Turma $turma,
        Disciplina $disciplina,
        User $professor,
        int $quantidadeQuestoes,
        int $quantidadeAlternativas,
        \DateTimeInterface $prazo,
        ?string $observacoes = null,
    ): SolicitacaoProva {
        Gate::forUser($autor)->authorize('create', SolicitacaoProva::class);
        Gate::forUser($autor)->authorize('view', $turma);

        $this->validar($turma, $disciplina, $professor, $quantidadeQuestoes, $quantidadeAlternativas);

        return DB::transaction(function () use (
            $autor, $turma, $disciplina, $professor, $quantidadeQuestoes,
            $quantidadeAlternativas, $prazo, $observacoes
        ) {
            $solicitacao = SolicitacaoProva::create([
                'curso_id' => $turma->curso_id,
                'turma_id' => $turma->getKey(),
                'disciplina_id' => $disciplina->getKey(),
                'professor_id' => $professor->getKey(),
                'criado_por' => $autor->getKey(),
                'quantidade_questoes' => $quantidadeQuestoes,
                'quantidade_alternativas' => $quantidadeAlternativas,
                'prazo' => $prazo,
                'status' => StatusSolicitacao::Aberta,
                'observacoes' => $observacoes,
            ]);

            foreach (range(1, $quantidadeQuestoes) as $ordem) {
                $item = $solicitacao->itens()->create(['ordem' => $ordem]);

                // A questão nasce em rascunho, com peso 1 como ponto de
                // partida; o professor ajusta ao escrevê-la.
                $solicitacao->questoes()->create([
                    'solicitacao_item_id' => $item->getKey(),
                    'disciplina_id' => $disciplina->getKey(),
                    'professor_id' => $professor->getKey(),
                    'peso' => 1,
                ]);
            }

            // Designar alguém para uma disciplina cria o vínculo docente se
            // ele ainda não existir — é o que dá ao professor acesso aos
            // resultados dela mais adiante.
            $professor->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplina->getKey(), 'turma_id' => $turma->getKey()],
                ['ativo' => true],
            );
            $professor->esquecerEscopo();

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties([
                    'turma' => $turma->nome,
                    'disciplina' => $disciplina->nome,
                    'professor' => $professor->nome,
                    'questoes' => $quantidadeQuestoes,
                    'prazo' => $prazo->format('Y-m-d H:i'),
                ])
                ->log("Solicitação de {$solicitacao->quantidade_questoes} questão(ões) aberta");

            return $solicitacao->refresh();
        });
    }

    protected function validar(
        Turma $turma,
        Disciplina $disciplina,
        User $professor,
        int $quantidadeQuestoes,
        int $quantidadeAlternativas,
    ): void {
        if ($quantidadeQuestoes < 1) {
            throw RegraDeNegocioException::porque('Peça ao menos uma questão.');
        }

        if ($quantidadeAlternativas < 2 || $quantidadeAlternativas > 6) {
            throw RegraDeNegocioException::porque('A prova deve ter de 2 a 6 alternativas por questão.');
        }

        if ((int) $disciplina->curso_id !== (int) $turma->curso_id) {
            throw RegraDeNegocioException::porque(
                "A disciplina {$disciplina->nome} não pertence ao curso desta turma."
            );
        }

        // A turma cursa as disciplinas do seu período, segundo a foto de
        // grade que congelou.
        $noPeriodo = $turma->disciplinasDoPeriodo()
            ->contains(fn ($item) => (int) $item->disciplina_id === (int) $disciplina->getKey());

        if (! $noPeriodo) {
            throw RegraDeNegocioException::porque(
                "A turma {$turma->nome} não cursa {$disciplina->nome} no {$turma->periodo}º período."
            );
        }

        if (! $professor->ativo) {
            throw RegraDeNegocioException::porque('A conta do professor está inativa.');
        }
    }
}
