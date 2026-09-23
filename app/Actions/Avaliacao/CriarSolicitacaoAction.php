<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Disciplina;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Abre a solicitação das questões de uma prova.
 *
 * A prova é uma só e reúne várias disciplinas: cada par disciplina +
 * professor entra como uma parte, com sua própria cota de questões. Cada
 * professor responde e entrega apenas a parte dele.
 */
class CriarSolicitacaoAction
{
    /**
     * @param  array<int, array{disciplina_id: int, professor_id: int, quantidade_questoes: int, observacoes?: ?string}>  $partes
     */
    public function executar(
        User $autor,
        Turma $turma,
        array $partes,
        int $quantidadeAlternativas,
        \DateTimeInterface $prazo,
        ?string $titulo = null,
        ?string $observacoes = null,
    ): SolicitacaoProva {
        Gate::forUser($autor)->authorize('create', SolicitacaoProva::class);
        Gate::forUser($autor)->authorize('view', $turma);

        $partes = $this->normalizar($partes);

        $this->validar($turma, $partes, $quantidadeAlternativas);

        return DB::transaction(function () use (
            $autor, $turma, $partes, $quantidadeAlternativas, $prazo, $titulo, $observacoes
        ) {
            $solicitacao = SolicitacaoProva::create([
                'curso_id' => $turma->curso_id,
                'turma_id' => $turma->getKey(),
                'criado_por' => $autor->getKey(),
                'titulo' => $titulo,
                'quantidade_alternativas' => $quantidadeAlternativas,
                'prazo' => $prazo,
                'status' => StatusSolicitacao::Aberta,
                'observacoes' => $observacoes,
            ]);

            foreach ($partes->values() as $indice => $dados) {
                $parte = $solicitacao->partes()->create([
                    'disciplina_id' => $dados['disciplina_id'],
                    'professor_id' => $dados['professor_id'],
                    'ordem' => $indice + 1,
                    'quantidade_questoes' => $dados['quantidade_questoes'],
                    'observacoes' => $dados['observacoes'] ?? null,
                ]);

                foreach (range(1, $dados['quantidade_questoes']) as $ordem) {
                    // A questão nasce em rascunho, com peso 1 como ponto
                    // de partida; o professor ajusta ao escrevê-la.
                    $solicitacao->questoes()->create([
                        'solicitacao_parte_id' => $parte->getKey(),
                        'ordem' => $ordem,
                        'disciplina_id' => $dados['disciplina_id'],
                        'professor_id' => $dados['professor_id'],
                        'peso' => 1,
                    ]);
                }

                // Designar alguém para uma disciplina cria o vínculo
                // docente se ele ainda não existir.
                $professor = User::query()->findOrFail($dados['professor_id']);

                $professor->vinculosDocentes()->firstOrCreate(
                    ['disciplina_id' => $dados['disciplina_id'], 'turma_id' => $turma->getKey()],
                    ['ativo' => true],
                );
                $professor->esquecerEscopo();
            }

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties([
                    'turma' => $turma->nome,
                    'partes' => $partes->count(),
                    'questoes' => $partes->sum('quantidade_questoes'),
                    'disciplinas' => Disciplina::query()
                        ->whereIn('id', $partes->pluck('disciplina_id'))
                        ->pluck('nome')
                        ->all(),
                    'prazo' => $prazo->format('Y-m-d H:i'),
                ])
                ->log(
                    "Solicitação aberta com {$partes->count()} disciplina(s) e "
                    .$partes->sum('quantidade_questoes').' questão(ões)'
                );

            return $solicitacao->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $partes
     * @return Collection<int, array{disciplina_id: int, professor_id: int, quantidade_questoes: int, observacoes: ?string}>
     */
    protected function normalizar(array $partes): Collection
    {
        return collect($partes)
            ->map(fn (array $parte) => [
                'disciplina_id' => (int) ($parte['disciplina_id'] ?? 0),
                'professor_id' => (int) ($parte['professor_id'] ?? 0),
                'quantidade_questoes' => (int) ($parte['quantidade_questoes'] ?? 0),
                'observacoes' => $parte['observacoes'] ?? null,
            ])
            ->filter(fn (array $parte) => $parte['disciplina_id'] > 0 && $parte['professor_id'] > 0)
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $partes */
    protected function validar(Turma $turma, Collection $partes, int $quantidadeAlternativas): void
    {
        if ($partes->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Informe ao menos uma disciplina com o professor responsável.'
            );
        }

        if ($quantidadeAlternativas < 2 || $quantidadeAlternativas > 6) {
            throw RegraDeNegocioException::porque('A prova deve ter de 2 a 6 alternativas por questão.');
        }

        $repetidas = $partes->pluck('disciplina_id')->duplicates();

        if ($repetidas->isNotEmpty()) {
            $nomes = Disciplina::query()->whereIn('id', $repetidas)->pluck('nome')->join(', ');

            throw RegraDeNegocioException::porque(
                "A mesma disciplina aparece mais de uma vez: {$nomes}. Some as questões em uma única linha."
            );
        }

        // A turma cursa as disciplinas do seu período, segundo a foto de
        // grade que congelou.
        $doPeriodo = $turma->disciplinasDoPeriodo()->pluck('disciplina_id');

        $disciplinas = Disciplina::query()
            ->whereIn('id', $partes->pluck('disciplina_id'))
            ->get()
            ->keyBy('id');

        foreach ($partes as $parte) {
            $disciplina = $disciplinas->get($parte['disciplina_id']);

            if ($disciplina === null) {
                throw RegraDeNegocioException::porque('Disciplina inexistente na solicitação.');
            }

            if ((int) $disciplina->curso_id !== (int) $turma->curso_id) {
                throw RegraDeNegocioException::porque(
                    "A disciplina {$disciplina->nome} não pertence ao curso desta turma."
                );
            }

            if (! $doPeriodo->contains($disciplina->getKey())) {
                throw RegraDeNegocioException::porque(
                    "A turma {$turma->nome} não cursa {$disciplina->nome} no {$turma->periodo}º período."
                );
            }

            if ($parte['quantidade_questoes'] < 1) {
                throw RegraDeNegocioException::porque(
                    "Peça ao menos uma questão de {$disciplina->nome}."
                );
            }

            $professor = User::query()->find($parte['professor_id']);

            if ($professor === null || ! $professor->ativo) {
                throw RegraDeNegocioException::porque(
                    "O professor indicado para {$disciplina->nome} está inativo ou não existe."
                );
            }
        }
    }
}
