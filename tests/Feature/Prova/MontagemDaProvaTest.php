<?php

/*
 * Critério de aceite 8:
 * prova com 5 + 6 + 4 questões gera numeração 1..15 com disciplina certa.
 *
 * E o snapshot: mexer na questão depois não altera a prova montada.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Enums\StatusProva;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->paeet->update(['nome' => 'Coordenação']);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profProcessos = professor($this->eixo);
    $this->profProcessos->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);
    $this->profBanco = professor($this->eixo);
    $this->profBanco->update(['nome' => 'Iara Melo', 'email' => 'iara@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica', 'Processos de Desenvolvimento', 'Banco de Dados'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->logica = $montagem['disciplinas']['Lógica'];
    $this->processos = $montagem['disciplinas']['Processos de Desenvolvimento'];
    $this->banco = $montagem['disciplinas']['Banco de Dados'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'nome' => 'Padrão institucional',
        'criado_por' => $this->paeet->id,
    ]);

    // O exemplo do enunciado: 5 + 6 + 4 questões.
    $this->prova = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->profLogica, 'questoes' => 5],
        ['disciplina' => $this->processos, 'professor' => $this->profProcessos, 'questoes' => 6],
        ['disciplina' => $this->banco, 'professor' => $this->profBanco, 'questoes' => 4],
    ], titulo: 'Avaliação bimestral');

    $this->partes = $this->prova->partes()->with(['disciplina', 'professor', 'solicitacao'])->get();

    $this->professorDe = fn (int $indice) => [
        $this->profLogica, $this->profProcessos, $this->profBanco,
    ][$indice];

    // Cada professor responde e entrega a sua parte.
    foreach ($this->partes as $indice => $parte) {
        enviarParte($parte, ($this->professorDe)($indice));
    }

    $this->aprovarTudo = function () {
        $analisar = app(AnalisarQuestaoAction::class);

        foreach ($this->prova->questoes()->get() as $questao) {
            $analisar->aprovar($questao, $this->paeet);
        }
    };

    $this->montar = app(MontarProvaAction::class);
});

it('numera 1 a 15 em blocos por disciplina', function () {
    ($this->aprovarTudo)();

    $prova = $this->montar->executar(
        autor: $this->paeet,
        turma: $this->turma,
        modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    $questoes = $prova->questoes()->with('disciplina')->orderBy('numero')->get();

    expect($questoes)->toHaveCount(15)
        ->and($questoes->pluck('numero')->all())->toBe(range(1, 15));

    $porDisciplina = $questoes->groupBy(fn (ProvaQuestao $q) => $q->disciplina->nome)
        ->map(fn ($grupo) => $grupo->pluck('numero')->all());

    expect($porDisciplina['Lógica'])->toBe([1, 2, 3, 4, 5])
        ->and($porDisciplina['Processos de Desenvolvimento'])->toBe([6, 7, 8, 9, 10, 11])
        ->and($porDisciplina['Banco de Dados'])->toBe([12, 13, 14, 15]);
});

it('guarda por dentro a disciplina, o professor e o peso de cada número', function () {
    ($this->aprovarTudo)();

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    $decima = $prova->questoes()->where('numero', 10)->with(['disciplina', 'professor'])->first();

    expect($decima->disciplina->nome)->toBe('Processos de Desenvolvimento')
        ->and($decima->professor->nome)->toBe('Marta Reis')
        ->and((float) $decima->peso)->toBeGreaterThan(0);

    $primeira = $prova->questoes()->where('numero', 1)->with('professor')->first();

    expect($primeira->professor->nome)->toBe('Renato Lima');
});

it('congela enunciado, alternativas e peso no momento da montagem', function () {
    ($this->aprovarTudo)();

    $origem = $this->prova->questoes()->where('ordem', 1)->first();
    $origem->update(['enunciado' => 'Enunciado original', 'peso' => 2.5]);

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    $naProva = $prova->questoes()->where('questao_id', $origem->id)->first();

    expect($naProva->enunciado_snapshot)->toBe('Enunciado original')
        ->and((float) $naProva->peso)->toBe(2.5)
        ->and($naProva->alternativas_snapshot)->toHaveCount(4)
        ->and($naProva->letra_correta)->toBe('A');

    // A questão original muda depois da montagem.
    $origem->update(['enunciado' => 'Enunciado alterado depois', 'peso' => 9]);
    $origem->alternativas()->where('letra', 'A')->update(['correta' => false, 'texto' => 'Outro texto']);

    $naProva->refresh();

    expect($naProva->enunciado_snapshot)->toBe('Enunciado original')
        ->and((float) $naProva->peso)->toBe(2.5)
        ->and($naProva->letra_correta)->toBe('A')
        ->and($naProva->alternativas_snapshot[0]['texto'])->not->toBe('Outro texto');
});

it('congela também o código e as imagens do enunciado', function () {
    ($this->aprovarTudo)();

    $questao = $this->prova->questoes()->where('ordem', 1)->first();

    $questao->blocos()->create([
        'ordem' => 1, 'tipo' => 'codigo',
        'conteudo' => "def soma(a, b):\n    return a + b",
        'linguagem' => 'python',
    ]);
    $questao->blocos()->create([
        'ordem' => 2, 'tipo' => 'texto', 'conteudo' => 'Qual é a saída?',
    ]);

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    $naProva = $prova->questoes()->where('questao_id', $questao->id)->first();

    expect($naProva->blocos())->toHaveCount(2)
        ->and($naProva->blocos()->first()['tipo'])->toBe('codigo')
        ->and($naProva->blocos()->first()['linguagem'])->toBe('python')
        ->and($naProva->blocos()->first()['conteudo'])->toContain('def soma');

    // Apagar o bloco original não mexe na prova.
    $questao->blocos()->delete();

    expect($naProva->refresh()->blocos())->toHaveCount(2);
});

it('deixa de fora questão não aprovada', function () {
    $analisar = app(AnalisarQuestaoAction::class);
    $questoes = $this->prova->questoes()->get();

    // Aprova todas menos duas: uma devolvida e uma sem decisão.
    foreach ($questoes->skip(2) as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $analisar->rejeitar($questoes->first(), $this->paeet, 'Refaça.');

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    expect($prova->questoes()->count())->toBe(13)
        ->and($prova->questoes()->pluck('numero')->all())->toBe(range(1, 13))
        ->and($prova->questoes()->where('questao_id', $questoes->first()->id)->exists())->toBeFalse();
});

it('recusa montar sem nenhuma questão aprovada', function () {
    expect(fn () => $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    ))->toThrow(RegraDeNegocioException::class, 'Nenhuma questão aprovada');

    expect(Prova::query()->count())->toBe(0);
});

it('permite escolher quais questões entram', function () {
    ($this->aprovarTudo)();

    $escolhidas = $this->prova->questoes()
        ->where('disciplina_id', $this->logica->id)
        ->pluck('id')
        ->all();

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Só de Lógica', questoesEscolhidas: $escolhidas,
    );

    expect($prova->questoes()->count())->toBe(5)
        ->and($prova->questoes()->with('disciplina')->get()->pluck('disciplina.nome')->unique()->all())
        ->toBe(['Lógica']);
});

it('guarda o layout escolhido na montagem', function () {
    ($this->aprovarTudo)();

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
        configuracao: ['colunas' => 2, 'mostrar_pesos' => true],
    );

    expect($prova->colunas())->toBe(2)
        ->and($prova->mostrarPesos())->toBeTrue()
        ->and($prova->status)->toBe(StatusProva::Gerada)
        ->and($prova->foiGerada())->toBeTrue()
        ->and($prova->gerada_por)->toBe($this->paeet->id);
});

it('versiona provas com o mesmo título na mesma turma', function () {
    ($this->aprovarTudo)();

    $primeira = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo, titulo: 'Bimestral',
    );
    $segunda = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo, titulo: 'Bimestral',
    );

    expect($primeira->versao)->toBe(1)
        ->and($segunda->versao)->toBe(2)
        // A primeira segue intacta.
        ->and($primeira->refresh()->questoes()->count())->toBe(15);
});

it('monta o gabarito com a letra correta de cada número', function () {
    ($this->aprovarTudo)();

    $prova = $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo, titulo: 'Bimestral',
    );

    $gabarito = $prova->gabarito();

    expect($gabarito)->toHaveCount(15)
        ->and($gabarito->keys()->all())->toBe(range(1, 15))
        ->and($gabarito->values()->unique()->all())->toBe(['A']);
});

it('recusa modelo de outro eixo', function () {
    ($this->aprovarTudo)();

    $modeloAlheio = ModeloProva::factory()->create([
        'eixo_id' => Eixo::factory()->create(['codigo' => 'ADM'])->id,
    ]);

    expect(fn () => $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $modeloAlheio, titulo: 'Bimestral',
    ))->toThrow(AuthorizationException::class);
});

it('nega a montagem ao professor', function () {
    ($this->aprovarTudo)();

    expect(fn () => $this->montar->executar(
        autor: $this->profLogica, turma: $this->turma, modelo: $this->modelo, titulo: 'Bimestral',
    ))->toThrow(AuthorizationException::class);
});

it('registra a montagem na auditoria', function () {
    ($this->aprovarTudo)();

    $this->montar->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo, titulo: 'Bimestral',
    );

    $registro = Activity::query()
        ->where('log_name', 'prova')->latest('id')->first();

    expect($registro->description)->toBe('Prova montada com 15 questão(ões)')
        ->and($registro->getProperty('disciplinas'))
        ->toContain('Lógica', 'Processos de Desenvolvimento', 'Banco de Dados');
});
