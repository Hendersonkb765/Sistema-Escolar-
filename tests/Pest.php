<?php

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\EnviarParteAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\PerfilUsuario;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => proibirLazyLoading())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Proteção contra lazy loading
|--------------------------------------------------------------------------
*/

/**
 * Proíbe lazy loading em TODO model hidratado, inclusive nos que vêm
 * sozinhos numa consulta.
 *
 * O `Model::preventLazyLoading()` do framework só marca a instância
 * quando a consulta devolve mais de um registro (Builder::hydrate), o que
 * faz um teste com uma linha só passar enquanto a tela de quem tem duas
 * quebra. Marcando no evento `retrieved`, qualquer relação esquecida no
 * eager load aparece já no primeiro registro.
 */
function proibirLazyLoading(): void
{
    Model::preventLazyLoading();

    Event::listen('eloquent.retrieved: *', function (string $evento, array $models) {
        foreach ($models as $model) {
            if ($model instanceof Model) {
                $model->preventsLazyLoading = true;
            }
        }
    });
}

/*
|--------------------------------------------------------------------------
| Helpers de domínio
|--------------------------------------------------------------------------
*/

/** Cria um usuário de gestão já vinculado aos Eixos indicados. */
function gestor(PerfilUsuario $perfil, Eixo ...$eixos): User
{
    $usuario = User::factory()->create(['perfil' => $perfil]);

    if ($eixos !== []) {
        $usuario->eixos()->sync(collect($eixos)->map->getKey()->all());
        $usuario->esquecerEscopo();
    }

    return $usuario;
}

function paeetAdmin(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::PaeetAdmin, ...$eixos);
}

function paeet(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Paeet, ...$eixos);
}

function professor(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Professor, ...$eixos);
}

/**
 * Monta um curso com disciplinas por período e publica a primeira versão
 * da grade a partir delas.
 *
 * @param  array<int, array<int, string>>  $porPeriodo  período => [nomes das disciplinas]
 * @return array{curso: Curso, grade: GradeCurricular, disciplinas: Collection<string, Disciplina>}
 */
function cursoComGrade(Eixo $eixo, array $porPeriodo, ?int $duracaoAnos = null, ?User $autor = null): array
{
    $curso = Curso::factory()->noEixo($eixo)->create([
        'duracao_anos' => $duracaoAnos ?? max(2, max(array_keys($porPeriodo))),
    ]);

    $disciplinas = collect();

    foreach ($porPeriodo as $periodo => $nomes) {
        foreach ($nomes as $nome) {
            $disciplinas->put($nome, Disciplina::factory()
                ->doCurso($curso)
                ->noPeriodo($periodo)
                ->create(['nome' => $nome]));
        }
    }

    $autor ??= paeetAdmin($eixo);

    $grade = app(PublicarVersaoDeGradeAction::class)
        ->executar($curso, $autor);

    return [
        'curso' => $curso,
        'grade' => $grade,
        'disciplinas' => $disciplinas,
    ];
}

/**
 * Abre uma solicitação com um ou mais pares disciplina + professor.
 *
 * @param  array<int, array{disciplina: Disciplina, professor: User, questoes?: int, observacoes?: ?string}>  $partes
 */
function solicitacaoCom(
    User $autor,
    Turma $turma,
    array $partes,
    int $alternativas = 4,
    ?DateTimeInterface $prazo = null,
    ?string $titulo = null,
): SolicitacaoProva {
    return app(CriarSolicitacaoAction::class)->executar(
        autor: $autor,
        turma: $turma,
        partes: array_map(fn (array $parte) => [
            'disciplina_id' => $parte['disciplina']->getKey(),
            'professor_id' => $parte['professor']->getKey(),
            'quantidade_questoes' => $parte['questoes'] ?? 3,
            'observacoes' => $parte['observacoes'] ?? null,
        ], $partes),
        quantidadeAlternativas: $alternativas,
        prazo: $prazo ?? now()->addWeek(),
        titulo: $titulo,
    );
}

/** Preenche uma questão de forma válida, para os testes de fluxo. */
function completarQuestao(
    Questao $questao,
    User $professor,
    ?string $enunciado = null,
    float $peso = 1,
): Questao {
    $quantidade = (int) $questao->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

    $alternativas = collect(range(0, $quantidade - 1))
        ->map(fn (int $indice) => [
            'letra' => chr(65 + $indice),
            'texto' => 'Alternativa '.chr(65 + $indice),
            'correta' => $indice === 0,
        ])
        ->all();

    return app(SalvarQuestaoAction::class)->executar(
        questao: $questao,
        autor: $professor,
        enunciado: $enunciado ?? "Enunciado da questão {$questao->ordem}",
        alternativas: $alternativas,
        peso: $peso,
    );
}

/** Preenche e envia uma parte inteira. */
function enviarParte(SolicitacaoParte $parte, User $professor): SolicitacaoParte
{
    foreach ($parte->questoes()->get() as $questao) {
        completarQuestao($questao, $professor);
    }

    return app(EnviarParteAction::class)->executar($parte->refresh(), $professor);
}
