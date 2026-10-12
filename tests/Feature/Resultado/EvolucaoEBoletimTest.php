<?php

/*
 * As três leituras que fecham a análise:
 *
 * - a evolução das notas de um bimestre para o outro;
 * - quem, dentro da turma, acertou e errou uma habilidade;
 * - o boletim que vai para a mão do aluno.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Actions\Resultado\CalcularNotasAction;
use App\Actions\Resultado\GerarBoletimAction;
use App\Enums\Bimestre;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Analises\Desempenho;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use App\Support\GraficoDeEvolucao;
use Illuminate\Support\Arr;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);
    $this->disciplinas = $montagem['disciplinas'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves', 'ra' => '1001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '1002']);

    /**
     * Uma prova do bimestre pedido, com acertos por aluno.
     *
     * @param  array<string, array<int, bool>>  $acertos  RA => numero => acertou
     */
    $this->provaCom = function (Bimestre $bimestre, string $titulo, array $acertos) {
        $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
            ['disciplina' => $this->disciplinas['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
            ['disciplina' => $this->disciplinas['Redes'], 'professor' => $this->profRedes, 'questoes' => 1],
        ], titulo: $titulo, bimestre: $bimestre);

        $partes = $solicitacao->partes()->orderBy('ordem')->get();

        enviarParte($partes[0], $this->profLogica, [
            1 => 'Aplicar condicionais', 2 => 'Aplicar condicionais',
        ]);
        enviarParte($partes[1], $this->profRedes, [1 => 'Calcular endereçamento IP']);

        $analisar = app(AnalisarQuestaoAction::class);

        foreach ($solicitacao->questoes()->get() as $questao) {
            $analisar->aprovar($questao, $this->paeet);
        }

        $prova = app(MontarProvaAction::class)->executar(
            autor: $this->paeet, turma: $this->turma,
            modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
            titulo: $titulo, bimestre: $bimestre,
            questoesEscolhidas: $solicitacao->questoes()->pluck('id')->all(),
        );
        $prova->update(['status' => StatusProva::Aplicada]);

        $importacao = Importacao::create([
            'prova_id' => $prova->id, 'usuario_id' => $this->paeet->id,
            'arquivo' => 'importacoes/x.xlsx', 'hash' => hash('sha256', $titulo),
            'status' => StatusImportacao::Confirmada, 'confirmada_em' => now(),
        ]);

        $questoes = $prova->questoes()->orderBy('numero')->get();
        $calcular = app(CalcularNotasAction::class);

        foreach ([$this->marina, $this->caio] as $aluno) {
            $resultado = ResultadoAluno::create([
                'prova_id' => $prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $importacao->id,
            ]);

            foreach ($questoes as $questao) {
                $acertou = $acertos[$aluno->ra][$questao->numero] ?? false;

                $resultado->respostas()->create([
                    'prova_questao_id' => $questao->id, 'acertou' => $acertou,
                    'peso' => $questao->peso, 'pontuacao' => $acertou ? $questao->peso : 0,
                ]);
            }

            $calcular->executar($resultado->refresh());
        }

        return $prova;
    };

    $this->tudo = fn () => Prova::query()->whereHas('resultados')->get();
});

/*
|--------------------------------------------------------------------------
| Evolução por bimestre
|--------------------------------------------------------------------------
*/

it('mede a nota média de cada disciplina bimestre a bimestre', function () {
    // 2º: Lógica toda errada; 4º: Lógica toda certa.
    ($this->provaCom)(Bimestre::Segundo, 'Do segundo', [
        '1001' => [1 => false, 2 => false, 3 => true],
        '1002' => [1 => false, 2 => false, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Quarto, 'Do quarto', [
        '1001' => [1 => true, 2 => true, 3 => true],
        '1002' => [1 => true, 2 => true, 3 => true],
    ]);

    $evolucao = app(AnalisarDesempenhoAction::class)
        ->evolucaoPorBimestre(($this->tudo)(), $this->paeet);

    // Quatro posições, uma por bimestre; sem prova fica em branco.
    expect($evolucao['Lógica'])->toBe([null, 0.0, null, 10.0])
        ->and($evolucao['Redes'])->toBe([null, 10.0, null, 10.0]);
});

it('não transforma bimestre sem avaliação em zero', function () {
    ($this->provaCom)(Bimestre::Terceiro, 'Só o terceiro', [
        '1001' => [1 => true, 2 => true, 3 => true],
        '1002' => [1 => true, 2 => true, 3 => true],
    ]);

    $evolucao = app(AnalisarDesempenhoAction::class)
        ->evolucaoPorBimestre(($this->tudo)(), $this->paeet);

    // Zero diria "foram mal"; nulo diz "não houve prova".
    expect($evolucao['Lógica'])->toBe([null, null, 10.0, null]);
});

/*
|--------------------------------------------------------------------------
| O gráfico
|--------------------------------------------------------------------------
*/

/*
 * A forma mudou: era uma linha por disciplina, sobrepostas; passou a ser
 * um painel por disciplina, com uma coluna por bimestre. O motivo está no
 * docblock de GraficoDeEvolucao — a linha afirmava o caminho entre março
 * e junho, e a lacuna de uma disciplina virava um ponto solto.
 */

it('não passa do teto de painéis', function () {
    $muitas = collect(range(1, 12))->mapWithKeys(fn (int $i) => ["Disciplina {$i}" => [1.0]])->all();

    expect(GraficoDeEvolucao::de(['1º'], $muitas)->series)
        ->toHaveCount(GraficoDeEvolucao::MAXIMO_DE_SERIES);
});

/*
 * Com um painel por disciplina não há segunda série da qual se
 * distinguir, então sobra uma cor só — e foi isso que permitiu escolher
 * um tom que passa em contraste nos dois temas sem brigar com daltonismo.
 */
it('usa uma cor só, com o tom do tema escuro ao lado', function () {
    expect(GraficoDeEvolucao::COR)->toBe('#2a78d6')
        ->and(GraficoDeEvolucao::COR_ESCURA)->toBe('#3987e5')
        // Nenhuma série carrega cor: a identidade está no título do painel.
        ->and(GraficoDeEvolucao::de(['1º'], ['Lógica' => [5.0]])->series[0])
        ->toBe(['nome' => 'Lógica', 'pontos' => [5.0]]);
});

it('põe a nota dez no topo e mede a coluna do chão para cima', function () {
    $grafico = GraficoDeEvolucao::de(['1º', '2º'], ['Lógica' => [10.0, 5.0]]);

    $colunas = $grafico->colunas([10.0, 5.0], largura: 228, altura: 132);

    // 18 de margem no topo, 22 na base: 92 de altura útil.
    expect($colunas[0]['altura'])->toBe(92.0)
        ->and($colunas[0]['y'])->toBe(18.0)
        ->and($colunas[1]['altura'])->toBe(46.0)
        // As duas nascem na mesma linha de base.
        ->and($colunas[0]['y'] + $colunas[0]['altura'])->toBe($colunas[1]['y'] + $colunas[1]['altura'])
        ->and($grafico->base(132))->toBe(110.0);
});

/*
 * O bimestre sem avaliação não vira coluna de altura zero nem nota zero:
 * fica sem coluna, e a view escreve "—" no lugar. Ausência de prova não é
 * queda de desempenho.
 */
it('deixa sem coluna o bimestre sem prova', function () {
    $grafico = GraficoDeEvolucao::de(['1º', '2º', '3º'], ['Lógica' => [8.0, null, 6.0]]);

    $colunas = $grafico->colunas([8.0, null, 6.0]);

    expect($colunas)->toHaveCount(3)
        ->and($colunas[1]['valor'])->toBeNull()
        ->and($colunas[1]['altura'])->toBe(0.0)
        ->and($colunas[1]['rotulo'])->toBe('')
        // E as outras duas continuam nos lugares delas.
        ->and($colunas[0]['valor'])->toBe(8.0)
        ->and($colunas[2]['valor'])->toBe(6.0);
});

it('mantém o lugar de cada bimestre, com ou sem nota', function () {
    $grafico = GraficoDeEvolucao::de(['1º', '2º', '3º', '4º'], ['Lógica' => [8.0, null, 6.0, null]]);

    $colunas = $grafico->colunas([8.0, null, 6.0, null]);

    // O 3º bimestre não escorrega para a posição do 2º só porque ele
    // ficou vazio: o eixo é o calendário, não os dados.
    expect($colunas[2]['x'])->toBeGreaterThan($colunas[1]['x'])
        ->and($colunas[3]['x'])->toBeGreaterThan($colunas[2]['x'])
        ->and(array_column($colunas, 'bimestre'))->toBe(['1º', '2º', '3º', '4º']);
});

it('deixa respiro entre colunas vizinhas', function () {
    $colunas = GraficoDeEvolucao::de(['1º', '2º'], ['Lógica' => [5.0, 5.0]])
        ->colunas([5.0, 5.0], largura: 228, altura: 132);

    $fimDaPrimeira = $colunas[0]['x'] + $colunas[0]['largura'];

    // Sem vão, duas colunas vizinhas se leem como uma só.
    expect($colunas[1]['x'])->toBeGreaterThan($fimDaPrimeira + 1);
});

it('desenha a linha do critério na altura da nota esperada', function () {
    $grafico = GraficoDeEvolucao::de(['1º'], ['Lógica' => [5.0]], criterio: 6.0);

    // Mesma escala das colunas: 6 de 10 sobre 92 de altura útil.
    expect($grafico->linhaDoCriterio(132))->toBe(round(18.0 + 92.0 * 0.4, 2))
        ->and(GraficoDeEvolucao::de(['1º'], ['Lógica' => [5.0]])->linhaDoCriterio())->toBeNull();
});

it('reconhece quando não há o que desenhar', function () {
    expect(GraficoDeEvolucao::de(['1º'], [])->vazio())->toBeTrue()
        ->and(GraficoDeEvolucao::de(['1º'], ['Lógica' => [null]])->vazio())->toBeTrue()
        ->and(GraficoDeEvolucao::de(['1º'], ['Lógica' => [5.0]])->vazio())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Quem errou a habilidade
|--------------------------------------------------------------------------
*/

it('diz quem acertou e quem errou uma habilidade', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true, 2 => true, 3 => true],
        '1002' => [1 => false, 2 => true, 3 => true],
    ]);

    $alunos = app(AnalisarDesempenhoAction::class)
        ->porAluno(($this->tudo)(), $this->paeet, 'Aplicar condicionais');

    // Quem mais precisa de ajuda primeiro.
    expect($alunos->pluck('aluno')->all())->toBe(['Caio Prado', 'Marina Alves'])
        ->and($alunos->first()['percentual'])->toBe(50.0)
        // O id vai junto: na tela cada questão é um botão que a abre.
        ->and(array_map(
            fn (array $q) => Arr::except($q, 'id'),
            $alunos->first()['questoes'],
        ))->toBe([
            ['numero' => 1, 'prova' => 'Bimestral', 'acertou' => false],
            ['numero' => 2, 'prova' => 'Bimestral', 'acertou' => true],
        ])
        ->and($alunos->first()['questoes'][0]['id'])->toBeInt()
        ->and($alunos->last()['percentual'])->toBe(100.0);
});

it('encontra a habilidade mesmo com outra caixa', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true, 2 => true], '1002' => [1 => true, 2 => true],
    ]);

    expect(app(AnalisarDesempenhoAction::class)
        ->porAluno(($this->tudo)(), $this->paeet, 'APLICAR  CONDICIONAIS'))
        ->toHaveCount(2);
});

it('o botão da tela chama o método com a habilidade certa', function () {
    // O elo que faltava: `@js(...)` dentro da tag do componente saía
    // literal e o botão não chamava nada. Aqui o argumento é lido do
    // HTML renderizado e devolvido ao método.
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => false, 2 => false, 3 => true],
        '1002' => [1 => false, 2 => false, 3 => true],
    ]);

    $componente = Livewire::actingAs($this->paeet)->test(Desempenho::class)
        ->call('abrir', 'habilidades');

    preg_match_all('/wire:click="verAlunos\((.+?)\)"/', $componente->html(), $achados);

    expect($achados[1])->not->toBeEmpty();

    // O que o navegador entregaria ao Livewire, já sem o escape de HTML.
    $argumento = json_decode(str_replace("'", '"', html_entity_decode($achados[1][0])));

    expect($argumento)->toBeString();

    $componente->call('verAlunos', $argumento)
        ->assertSet('habilidadeAberta', $argumento)
        ->assertSee('Aluno a aluno');
});

it('abre e fecha o detalhe na tela', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => false, 2 => false, 3 => true],
        '1002' => [1 => false, 2 => false, 3 => true],
    ]);

    $componente = Livewire::actingAs($this->paeet)->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->assertSee('Ver alunos')
        ->assertDontSee('Aluno a aluno');

    $componente->call('verAlunos', 'Aplicar condicionais')
        ->assertSee('Aluno a aluno')
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado');

    $componente->call('verAlunos', 'Aplicar condicionais')
        ->assertDontSee('Aluno a aluno');
});

it('mostra o gráfico e a tabela equivalente', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true, 2 => true, 3 => true],
        '1002' => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'evolucao')
        ->assertSee('Evolução por bimestre')
        ->assertSee('<svg', escape: false)
        // A leitura em tabela não é opcional: quem não distingue as
        // cores precisa dos números.
        ->assertSee('Ver os mesmos números em tabela');
});

/*
|--------------------------------------------------------------------------
| O boletim
|--------------------------------------------------------------------------
*/

it('gera um PDF com uma página por aluno', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true, 2 => true, 3 => true],
        '1002' => [1 => false, 2 => false, 3 => false],
    ]);

    $pdf = app(GerarBoletimAction::class)->conteudo($this->turma, $this->paeet);

    preg_match('#/Count\s+(\d+)#', $pdf, $paginas);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        // Dois alunos, duas páginas: a folha de cada um sai sozinha.
        ->and((int) ($paginas[1] ?? 0))->toBe(2);
});

it('leva a nota de cada disciplina para o boletim', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true, 2 => true, 3 => false],
        '1002' => [1 => false, 2 => false, 3 => false],
    ]);

    $alunos = app(GerarBoletimAction::class)->alunosComNota($this->turma, $this->paeet);

    $marina = $alunos->firstWhere(fn (array $d) => $d['aluno']->nome === 'Marina Alves');

    expect($alunos)->toHaveCount(2)
        ->and($marina['linhas']->pluck('disciplina')->all())->toBe(['Lógica', 'Redes'])
        ->and($marina['linhas']->firstWhere('disciplina', 'Lógica')['nota'])->toBe(10.0)
        ->and($marina['linhas']->firstWhere('disciplina', 'Redes')['nota'])->toBe(0.0)
        ->and($marina['media'])->toBe(5.0);
});

it('ordena as linhas por disciplina e depois por bimestre', function () {
    // O 4º vinha antes do 2º: ordenar o enum como objeto não compara
    // pelo número.
    ($this->provaCom)(Bimestre::Quarto, 'Do quarto', [
        '1001' => [1 => true], '1002' => [1 => true],
    ]);
    ($this->provaCom)(Bimestre::Segundo, 'Do segundo', [
        '1001' => [1 => true], '1002' => [1 => true],
    ]);

    $linhas = app(GerarBoletimAction::class)
        ->alunosComNota($this->turma, $this->paeet)
        ->first()['linhas'];

    expect($linhas->map(fn (array $l) => $l['disciplina'].' '.$l['bimestre']->value)->all())
        ->toBe(['Lógica 2', 'Lógica 4', 'Redes 2', 'Redes 4']);
});

it('separa o boletim por bimestre', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Do segundo', [
        '1001' => [1 => true, 2 => true, 3 => true], '1002' => [1 => true, 2 => true, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Quarto, 'Do quarto', [
        '1001' => [1 => false, 2 => false, 3 => false], '1002' => [1 => false, 2 => false, 3 => false],
    ]);

    $acao = app(GerarBoletimAction::class);

    $doSegundo = $acao->alunosComNota($this->turma, $this->paeet, Bimestre::Segundo);
    $deTodos = $acao->alunosComNota($this->turma, $this->paeet);

    expect($doSegundo->first()['media'])->toBe(10.0)
        ->and($deTodos->first()['media'])->toBe(5.0)
        ->and($acao->nomeDoArquivo($this->turma, Bimestre::Segundo))->toContain('2o-bimestre');
});

it('explica quando não há nota para o boletim', function () {
    expect(fn () => app(GerarBoletimAction::class)->conteudo($this->turma, $this->paeet, Bimestre::Primeiro))
        ->toThrow(RegraDeNegocioException::class);
});

it('baixa o boletim pela rota', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        '1001' => [1 => true], '1002' => [1 => true],
    ]);

    $resposta = $this->actingAs($this->paeet)
        ->get(route('resultados.boletim', ['turma' => $this->turma->id, 'bimestre' => 2]));

    $resposta->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('application/pdf')
        ->and($resposta->headers->get('content-disposition'))->toContain('boletim-1-a');
});

it('nega o boletim de turma de outro Eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $this->actingAs($forasteiro)
        ->get(route('resultados.boletim', ['turma' => $this->turma->id]))
        ->assertForbidden();
});
