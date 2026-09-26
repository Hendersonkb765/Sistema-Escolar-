<?php

namespace App\Actions\Prova;

use App\Enums\Bimestre;
use App\Enums\StatusProva;
use App\Enums\StatusQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Monta a prova a partir das questões aprovadas da turma.
 *
 * A numeração é contínua e agrupada por disciplina — Lógica 1 a 5,
 * Processos 6 a 11, Banco de Dados 12 a 15 — e o aluno vê só o número. O
 * sistema guarda, por dentro, de qual disciplina e de qual professor cada
 * questão veio, e com que peso.
 *
 * Tudo entra como snapshot: enunciado, blocos de apoio, alternativas e
 * peso são copiados. Mexer na questão original depois não altera uma
 * prova já montada.
 */
class MontarProvaAction
{
    /**
     * @param  array<int, int>|null  $questoesEscolhidas  ids; null usa todas as aprovadas
     * @param  array<string, mixed>  $configuracao  colunas, gabarito, pesos
     */
    public function executar(
        User $autor,
        Turma $turma,
        ModeloProva $modelo,
        string $titulo,
        ?Bimestre $bimestre = null,
        ?\DateTimeInterface $dataAplicacao = null,
        ?array $questoesEscolhidas = null,
        array $configuracao = [],
        ?string $instrucoes = null,
    ): Prova {
        Gate::forUser($autor)->authorize('create', Prova::class);
        Gate::forUser($autor)->authorize('view', $turma);
        Gate::forUser($autor)->authorize('view', $modelo);

        if ((int) $modelo->eixo_id !== (int) $turma->loadMissing('curso')->curso->eixo_id) {
            throw RegraDeNegocioException::porque('O modelo de prova pertence a outro eixo.');
        }

        $questoes = $this->questoesElegiveis($turma, $questoesEscolhidas);

        if ($questoes->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Nenhuma questão aprovada disponível para esta turma. '
                .'Aprove as questões enviadas pelos professores antes de montar a prova.'
            );
        }

        // Sem escolha explícita, vale o bimestre das solicitações que
        // produziram estas questões — quando elas concordam.
        $bimestre ??= $this->bimestreDasQuestoes($questoes);

        return DB::transaction(function () use (
            $autor, $turma, $modelo, $titulo, $bimestre, $dataAplicacao, $questoes, $configuracao, $instrucoes
        ) {
            $versao = (int) Prova::query()
                ->withTrashed()
                ->where('turma_id', $turma->getKey())
                ->where('titulo', $titulo)
                ->max('versao') + 1;

            $prova = Prova::create([
                'turma_id' => $turma->getKey(),
                'modelo_prova_id' => $modelo->getKey(),
                'titulo' => $titulo,
                'bimestre' => $bimestre,
                'data_aplicacao' => $dataAplicacao,
                'versao' => $versao,
                'status' => StatusProva::Gerada,
                'instrucoes' => $instrucoes ?? $modelo->instrucoes,
                'configuracao' => [
                    'colunas' => (int) ($configuracao['colunas'] ?? 2),
                    'mostrar_pesos' => (bool) ($configuracao['mostrar_pesos'] ?? false),
                ],
                'gerada_por' => $autor->getKey(),
                'gerada_em' => now(),
            ]);

            $numero = 0;

            foreach ($this->agrupadasPorDisciplina($questoes) as $daDisciplina) {
                foreach ($daDisciplina as $questao) {
                    $numero++;

                    $prova->questoes()->create([
                        'numero' => $numero,
                        'questao_id' => $questao->getKey(),
                        'disciplina_id' => $questao->disciplina_id,
                        'professor_id' => $questao->professor_id,
                        'peso' => $questao->peso,
                        'versao_questao' => $questao->versao,
                        'enunciado_snapshot' => (string) $questao->enunciado,
                        'habilidade_snapshot' => $questao->habilidade,
                        'blocos_snapshot' => $this->congelarBlocos($questao),
                        'alternativas_snapshot' => $this->congelarAlternativas($questao),
                        'letra_correta' => $questao->alternativaCorreta()?->letra ?? 'A',
                    ]);
                }
            }

            activity('prova')
                ->performedOn($prova)
                ->causedBy($autor)
                ->withProperties([
                    'turma' => $turma->nome,
                    'questoes' => $numero,
                    'disciplinas' => $this->agrupadasPorDisciplina($questoes)->keys()->all(),
                    'soma_dos_pesos' => (float) $questoes->sum('peso'),
                    'colunas' => $prova->colunas(),
                ])
                ->log("Prova montada com {$numero} questão(ões)");

            return $prova->refresh();
        });
    }

    /**
     * Questões aprovadas das solicitações desta turma.
     *
     * @param  array<int, int>|null  $escolhidas
     * @return Collection<int, Questao>
     */
    /**
     * O bimestre que as questões trazem de origem. Se vierem de
     * solicitações de bimestres diferentes, não há o que herdar e o
     * primeiro serve de ponto de partida para quem monta escolher.
     *
     * @param  Collection<int, Questao>  $questoes
     */
    public function bimestreDasQuestoes(Collection $questoes): Bimestre
    {
        $bimestres = $questoes
            ->loadMissing('solicitacao:id,bimestre')
            ->map(fn (Questao $questao) => $questao->solicitacao->bimestre)
            ->unique();

        return $bimestres->count() === 1 ? $bimestres->first() : Bimestre::Primeiro;
    }

    public function questoesElegiveis(Turma $turma, ?array $escolhidas = null): Collection
    {
        return Questao::query()
            ->where('status', StatusQuestao::Aprovada)
            ->whereHas('solicitacao', fn ($q) => $q->where('turma_id', $turma->getKey()))
            ->when($escolhidas !== null, fn ($q) => $q->whereKey($escolhidas))
            ->with(['disciplina', 'alternativas', 'blocos', 'parte'])
            ->get();
    }

    /**
     * Agrupa por disciplina mantendo a ordem em que elas aparecem na
     * solicitação, e dentro de cada uma a ordem das questões.
     *
     * @param  Collection<int, Questao>  $questoes
     * @return Collection<string, Collection<int, Questao>>
     */
    protected function agrupadasPorDisciplina(Collection $questoes): Collection
    {
        // Chave composta: a disciplina entra na ordem em que foi pedida na
        // solicitação, e dentro dela as questões na ordem do professor.
        return $questoes
            ->sortBy(fn (Questao $questao) => sprintf(
                '%04d-%04d',
                $questao->parte?->ordem ?? 0,
                $questao->ordem,
            ))
            ->groupBy(fn (Questao $questao) => $questao->disciplina->nome);
    }

    /** @return array<int, array<string, mixed>> */
    protected function congelarBlocos(Questao $questao): array
    {
        return $questao->blocos
            ->map(fn ($bloco) => [
                'tipo' => $bloco->tipo->value,
                'conteudo' => $bloco->conteudo,
                'linguagem' => $bloco->linguagem?->value,
                'caminho' => $bloco->caminho,
                'legenda' => $bloco->legenda,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function congelarAlternativas(Questao $questao): array
    {
        return $questao->alternativas
            ->sortBy('letra')
            ->map(fn ($alternativa) => [
                'letra' => $alternativa->letra,
                'texto' => (string) $alternativa->texto,
                'correta' => (bool) $alternativa->correta,
            ])
            ->values()
            ->all();
    }
}
