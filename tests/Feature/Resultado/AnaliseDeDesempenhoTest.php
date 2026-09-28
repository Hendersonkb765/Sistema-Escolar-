<?php

/*
 * Onde a turma teve dificuldade.
 *
 * Por número de questão diz *onde* erraram; por habilidade diz *o quê* —
 * e é essa leitura que permite intervir. Duas questões da mesma
 * habilidade somam numa linha só.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Actions\Resultado\CalcularNotasAction;
use App\Enums\Bimestre;
use App\Enums\PerfilUsuario;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Livewire\Analises\Desempenho;
use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use App\Models\User;
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

    $this->alunos = collect(['Marina Alves', 'Caio Prado'])->map(
        fn (string $nome, int $i) => Aluno::factory()->naTurma($this->turma)
            ->create(['nome' => $nome, 'ra' => '100'.$i])
    );

    /**
     * Monta uma prova cujas questões de Lógica compartilham a mesma
     * habilidade, e grava um resultado por aluno.
     *
     * @param  array<int, array<int, bool>>  $acertos  índice do aluno => acertos por número
     */
    $this->provaCom = function (Bimestre $bimestre, string $titulo, array $acertos) {
        $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
            ['disciplina' => $this->disciplinas['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
            ['disciplina' => $this->disciplinas['Redes'], 'professor' => $this->profRedes, 'questoes' => 1],
        ], titulo: $titulo, bimestre: $bimestre);

        $partes = $solicitacao->partes()->orderBy('ordem')->get();

        // As duas de Lógica avaliam a MESMA habilidade.
        enviarParte($partes[0], $this->profLogica, [
            1 => 'Interpretar estruturas de repetição',
            2 => 'Interpretar estruturas de repetição',
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
            // Só as desta solicitação: a montagem juntaria também as
            // aprovadas de outros bimestres da mesma turma.
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

        foreach ($this->alunos->values() as $indice => $aluno) {
            $resultado = ResultadoAluno::create([
                'prova_id' => $prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $importacao->id,
            ]);

            foreach ($questoes as $questao) {
                $acertou = $acertos[$indice][$questao->numero] ?? false;

                $resultado->respostas()->create([
                    'prova_questao_id' => $questao->id, 'acertou' => $acertou,
                    'peso' => $questao->peso, 'pontuacao' => $acertou ? $questao->peso : 0,
                ]);
            }

            $calcular->executar($resultado->refresh());
        }

        return $prova;
    };
});

it('agrupa as questões que avaliam a mesma habilidade', function () {
    // Lógica: questões 1 e 2, mesma habilidade. Um aluno acerta as duas,
    // o outro erra as duas -> 2 de 4 respostas.
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porHabilidade(Prova::whereHas('resultados')->get(), $this->paeet);

    $repeticao = $linhas->firstWhere('habilidade', 'Interpretar estruturas de repetição');

    expect($repeticao['questoes'])->toBe([1, 2])
        ->and($repeticao['respostas'])->toBe(4)
        ->and($repeticao['acertos'])->toBe(2)
        ->and($repeticao['percentual'])->toBe(50.0)
        ->and($repeticao['atencao'])->toBeTrue();
});

it('ordena da habilidade que mais precisa de atenção', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => false, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porHabilidade(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($linhas->pluck('habilidade')->all())
        ->toBe(['Interpretar estruturas de repetição', 'Calcular endereçamento IP'])
        ->and($linhas->first()['percentual'])->toBe(0.0)
        ->and($linhas->last()['percentual'])->toBe(100.0);
});

it('traz a habilidade junto do número na leitura por questão', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => false],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porQuestao(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($linhas->first()['numero'])->toBe(2)
        ->and($linhas->first()['habilidade'])->toBe('Interpretar estruturas de repetição')
        ->and($linhas->first()['percentual'])->toBe(0.0)
        ->and($linhas->pluck('habilidade')->unique()->count())->toBe(2);
});

it('junta grafias que só diferem em caixa e espaço', function () {
    expect(AnalisarDesempenhoAction::chave('Interpretar  Gráficos'))
        ->toBe(AnalisarDesempenhoAction::chave('interpretar gráficos'));
});

it('congela a habilidade na montagem', function () {
    $prova = ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [0 => [], 1 => []]);

    $questao = $prova->questoes()->with('questao')->where('numero', 1)->sole();

    expect($questao->habilidade_snapshot)->toBe('Interpretar estruturas de repetição');

    // Mudar a questão original não reescreve a análise da prova antiga.
    $questao->questao->update(['habilidade' => 'Outra coisa']);

    expect($questao->refresh()->habilidade_snapshot)->toBe('Interpretar estruturas de repetição');
});

/*
|--------------------------------------------------------------------------
| A tela
|--------------------------------------------------------------------------
*/

it('separa a análise por bimestre', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Do segundo', [
        0 => [1 => false, 2 => false, 3 => false],
        1 => [1 => false, 2 => false, 3 => false],
    ]);
    ($this->provaCom)(Bimestre::Quarto, 'Do quarto', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);

    $componente = Livewire::actingAs($this->paeet)->test(Desempenho::class);

    // Sem filtro, os dois bimestres entram: 4 acertos em 8 respostas.
    expect($componente->viewData('porHabilidade')
        ->firstWhere('habilidade', 'Interpretar estruturas de repetição')['percentual'])->toBe(50.0);

    $componente->set('filtroBimestre', (string) Bimestre::Segundo->value);

    expect($componente->viewData('porHabilidade')
        ->firstWhere('habilidade', 'Interpretar estruturas de repetição')['percentual'])->toBe(0.0);

    $componente->set('filtroBimestre', (string) Bimestre::Quarto->value);

    expect($componente->viewData('porHabilidade')
        ->firstWhere('habilidade', 'Interpretar estruturas de repetição')['percentual'])->toBe(100.0);
});

it('mostra as duas leituras na tela', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => false, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertOk()
        ->assertSee('Por habilidade avaliada')
        ->assertSee('Interpretar estruturas de repetição')
        ->assertSee('Calcular endereçamento IP')
        ->assertSee('Precisa de atenção')
        // Três leituras: duas da turma e uma nominal.
        ->assertSee('Índice de acerto por questão')
        ->assertSee('Acerto de cada aluno');
});

/*
|--------------------------------------------------------------------------
| O acerto de cada aluno, questão a questão
|--------------------------------------------------------------------------
|
| Veio da tela de notas, onde atrapalhava quem só queria transcrever. É a
| única leitura nominal da página: as outras duas contam a turma.
*/

it('mostra o ✓ e o ✗ de cada aluno quando o recorte é de uma prova só', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('filtroBimestre', (string) Bimestre::Segundo->value)
        ->assertOk()
        ->assertSee('✓')
        ->assertSee('✗')
        ->assertSee('2/3')
        ->assertSee('1/3');
});

/*
 * O número da questão só quer dizer alguma coisa dentro da prova em que
 * ela saiu: duas provas lado a lado teriam duas questões "1" diferentes
 * na mesma coluna.
 */
it('pede uma prova quando o recorte junta mais de uma', function () {
    ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Segundo, 'Segunda', [
        0 => [1 => false, 2 => false, 3 => false],
        1 => [1 => false, 2 => false, 3 => false],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSee('Uma prova por vez')
        ->assertDontSee('✓');
});

it('volta a mostrar assim que o recorte aponta para uma prova', function () {
    ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Segundo, 'Segunda', [
        0 => [1 => false, 2 => false, 3 => false],
        1 => [1 => false, 2 => false, 3 => false],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSee('Uma prova por vez')
        ->set('filtroBimestre', (string) Bimestre::Segundo->value)
        ->assertDontSee('Uma prova por vez')
        ->assertSee('✗');
});

/*
 * Filtrar por disciplina tira colunas da tabela. O total precisa contar
 * só o que ficou à vista: "3/5" ao lado de duas colunas é número certo
 * para pergunta nenhuma.
 */
it('conta os acertos só das questões mostradas', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => true, 3 => false],
    ]);

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    $html = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('filtroBimestre', (string) Bimestre::Segundo->value)
        ->set('filtroDisciplina', (string) $logica)
        ->html();

    // Lógica tem 2 das 3 questões: os totais são sobre 2, não sobre 3.
    expect(tabelaNominal($html))
        ->toContain('1/2')
        ->toContain('2/2')
        ->not->toContain('/3');
});

/** A tabela nominal, sem o resto da página: "1/2" aparece em mais de um lugar. */
function tabelaNominal(string $html): string
{
    preg_match('/<table data-nominal.*?<\\/table>/s', $html, $achado);

    return strip_tags($achado[0] ?? '');
}

it('avisa quando o recorte não tem o que analisar', function () {
    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSee('Nada para analisar neste recorte');
});

it('mostra ao professor só as habilidades das questões dele', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => true],
    ]);

    $componente = Livewire::actingAs($this->profLogica)->test(Desempenho::class);

    expect($componente->viewData('porHabilidade')->pluck('habilidade')->all())
        ->toBe(['Interpretar estruturas de repetição']);

    $componente->assertDontSee('Calcular endereçamento IP');
});

it('não vaza a análise de outro Eixo', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [0 => [1 => true], 1 => [1 => true]]);

    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(Desempenho::class)
        ->assertSee('Nada para analisar neste recorte')
        ->assertDontSee('Interpretar estruturas de repetição');
});

it('nega a análise a quem não pode ver resultados', function () {
    $semNada = User::factory()->create(['perfil' => PerfilUsuario::Professor]);

    expect($semNada->can('viewAny', ResultadoAluno::class))->toBeTrue();
});
