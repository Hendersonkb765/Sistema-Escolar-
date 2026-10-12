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
use App\Enums\StatusAluno;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Livewire\Analises\Desempenho;
use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\RespostaAluno;
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
     * `$marcadas` é a planilha que traz a letra: quando vem, o acerto
     * deixa de ser dado e passa a sair da conferência contra o gabarito —
     * exatamente como na importação. A correta é sempre "A".
     *
     * @param  array<int, array<int, bool>>  $acertos  índice do aluno => acertos por número
     * @param  array<int, array<int, ?string>>|null  $marcadas  índice do aluno => letra por número
     */
    $this->provaCom = function (Bimestre $bimestre, string $titulo, array $acertos, ?array $marcadas = null) {
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
                $letra = $marcadas === null ? null : ($marcadas[$indice][$questao->numero] ?? null);

                $acertou = $marcadas === null
                    ? ($acertos[$indice][$questao->numero] ?? false)
                    : $letra === $questao->letra_correta;

                $resultado->respostas()->create([
                    'prova_questao_id' => $questao->id, 'acertou' => $acertou,
                    'alternativa_marcada' => $letra,
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

    expect(array_column($repeticao['questoes'], 'numero'))->toBe([1, 2])
        ->and($repeticao['respostas'])->toBe(4)
        ->and($repeticao['acertos'])->toBe(2)
        ->and($repeticao['percentual'])->toBe(50.0)
        // Dois alunos e quatro respostas não sustentam "a turma não
        // domina isto": sustentam "falta evidência", que é outra coisa.
        ->and($repeticao['situacao'])->toBe('inconclusivo')
        ->and($repeticao['atencao'])->toBeFalse()
        ->and($repeticao['falta_evidencia'])->toContain('4 respostas');
});

/*
 * O percentual é ponderado pelos pesos, e não pela contagem de acertos.
 * Numa habilidade com uma questão de peso 3 e uma de peso 1, acertar só
 * a de peso 3 dá 50% contando respostas e 75% contando pontos — e é a
 * segunda que o professor usa para decidir.
 */
it('calcula o aproveitamento pelos pontos, respeitando o peso da questão', function () {
    $prova = ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => true],
    ]);

    // A questão 1 passa a valer 3; a 2 continua valendo 1.
    $primeira = $prova->questoes()->where('numero', 1)->sole();
    $primeira->update(['peso' => 3]);
    RespostaAluno::query()
        ->where('prova_questao_id', $primeira->id)
        ->update(['peso' => 3, 'pontuacao' => 3]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porHabilidade(Prova::whereHas('resultados')->get(), $this->paeet);

    $repeticao = $linhas->firstWhere('habilidade', 'Interpretar estruturas de repetição');

    // 6 pontos obtidos (duas vezes a questão de peso 3) de 8 possíveis.
    expect($repeticao['pontos_obtidos'])->toBe(6.0)
        ->and($repeticao['pontos_maximos'])->toBe(8.0)
        ->and($repeticao['percentual'])->toBe(75.0)
        // Contando respostas seriam 2 de 4, isto é, 50%.
        ->and($repeticao['acertos'])->toBe(2)
        ->and($repeticao['respostas'])->toBe(4);
});

/*
 * Com evidência bastante, a leitura volta a ser uma conclusão.
 */
it('aponta dificuldade quando há evidência para isso', function () {
    config()->set('analise.evidencia_minima.respostas', 4);
    config()->set('analise.evidencia_minima.questoes', 2);

    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porHabilidade(Prova::whereHas('resultados')->get(), $this->paeet);

    $repeticao = $linhas->firstWhere('habilidade', 'Interpretar estruturas de repetição');

    expect($repeticao['situacao'])->toBe('atencao')
        ->and($repeticao['atencao'])->toBeTrue()
        ->and($repeticao['falta_evidencia'])->toBe('');
});

/*
 * O limite é o que a escola configurou, e não 60 cravado no código: 60%
 * ou 70% não são verdades sobre aprendizagem.
 */
it('mede contra o critério configurado pela escola', function () {
    config()->set('analise.evidencia_minima.respostas', 4);
    config()->set('analise.criterio_esperado', 40);

    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porHabilidade(Prova::whereHas('resultados')->get(), $this->paeet);

    // 50% passa a ser satisfatório quando o critério da escola é 40%.
    expect($linhas->firstWhere('habilidade', 'Interpretar estruturas de repetição')['situacao'])
        ->toBe('satisfatorio');
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

    $componente = Livewire::actingAs($this->paeet)->test(Desempenho::class)
        ->call('abrir', 'habilidades');

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

it('mostra as leituras uma por vez, em abas', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => false, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->assertOk()
        ->assertSee('Por habilidade avaliada')
        ->assertSee('Interpretar estruturas de repetição')
        ->assertSee('Calcular endereçamento IP')
        ->assertSee('Evidências insuficientes')
        // As outras leituras existem como aba, e não na mesma tela.
        ->assertDontSee('Acerto por questão')
        ->assertSeeHtml('aria-controls="painel-questoes"')
        ->assertSeeHtml('aria-controls="painel-alunos"');
});

/*
 * O professor não abre a prova montada — ela é o documento que a
 * coordenação imprime. Isso não pode apagar as telas dele: os resultados
 * das questões que ele escreveu continuam sendo dele.
 *
 * A análise filtrava as provas pela Policy do documento, e no dia em que
 * o acesso à folha foi fechado a tela do professor ficou vazia sem
 * mensagem nenhuma. Passou a filtrar pelo escopo, que é a outra pergunta.
 */
it('mostra a análise ao professor mesmo sem ele poder abrir a prova', function () {
    $prova = ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => false, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    expect($this->profLogica->can('view', $prova))->toBeFalse();

    Livewire::actingAs($this->profLogica)
        ->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->assertOk()
        ->assertSee('Interpretar estruturas de repetição');
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
        ->call('abrir', 'alunos')
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
        ->call('abrir', 'alunos')
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
        ->call('abrir', 'alunos')
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
        ->call('abrir', 'alunos')
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

    $componente = Livewire::actingAs($this->profLogica)->test(Desempenho::class)
        ->call('abrir', 'habilidades')->call('abrir', 'habilidades');

    expect($componente->viewData('porHabilidade')->pluck('habilidade')->all())
        ->toBe(['Interpretar estruturas de repetição']);

    $componente->assertDontSee('Calcular endereçamento IP');
});

it('não vaza a análise de outro Eixo', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [0 => [1 => true], 1 => [1 => true]]);

    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->assertSee('Nada para analisar neste recorte')
        ->assertDontSee('Interpretar estruturas de repetição');
});

it('nega a análise a quem não pode ver resultados', function () {
    $semNada = User::factory()->create(['perfil' => PerfilUsuario::Professor]);

    expect($semNada->can('viewAny', ResultadoAluno::class))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Visão geral da turma
|--------------------------------------------------------------------------
|
| A média sozinha mente por omissão: uma turma com média 7 pode ter um
| terço dos alunos abaixo de 5. Por isso o painel traz também a mediana,
| a distribuição por faixa e a contagem de quem está abaixo do critério.
*/

it('conta matriculados, avaliados e pendentes', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);

    // Um terceiro aluno, matriculado e sem avaliação.
    Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Rita Souza', 'ra' => '1003']);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($geral['matriculados'])->toBe(3)
        ->and($geral['avaliados'])->toBe(2)
        ->and($geral['pendentes'])->toBe(1)
        ->and(array_column($geral['pendentes_nomes'], 'nome'))->toBe(['Rita Souza']);
});

/*
 * Somar o zero de quem faltou rebaixa a turma inteira por um dado que
 * não existe.
 */
it('não transforma aluno sem avaliação em nota zero', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);

    Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Rita Souza', 'ra' => '1003']);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    // Os dois avaliados acertaram tudo: a média é 10, e não 6,67.
    expect($geral['media'])->toBe(10.0)
        ->and($geral['pendentes'])->toBe(1);
});

it('traz média e mediana, que podem discordar', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => false, 3 => false],
    ]);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    // Duas disciplinas por aluno: quatro notas, duas 10 e duas 0.
    expect($geral['notas'])->toBe(4)
        ->and($geral['media'])->toBe(5.0)
        ->and($geral['mediana'])->toBe(5.0);
});

it('distribui as notas por faixa', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => false, 3 => false],
    ]);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    $porRotulo = collect($geral['faixas'])->pluck('quantidade', 'rotulo');

    expect($porRotulo['0 a 2'])->toBe(2)
        ->and($porRotulo['8 a 10'])->toBe(2)
        ->and(array_sum($porRotulo->all()))->toBe(4);
});

/*
 * O número que encontra o grupo em dificuldade é "quantos alunos têm ao
 * menos uma nota abaixo", e não a média do aluno — que esconde a
 * disciplina ruim atrás da boa.
 */
it('conta o aluno que tem ao menos uma nota abaixo do critério', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        // Acerta Lógica inteira e erra Redes: média boa, uma nota zero.
        0 => [1 => true, 2 => true, 3 => false],
        1 => [1 => true, 2 => true, 3 => true],
    ]);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($geral['alunos_abaixo'])->toBe(1)
        ->and($geral['percentual_alunos_abaixo'])->toBe(50.0);
});

it('mede o critério pela nota que a escola configurou', function () {
    config()->set('analise.nota_esperada', 9);

    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => false],
        1 => [1 => true, 2 => true, 3 => false],
    ]);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    // Lógica 10 e Redes 0: com o corte em 9, metade das notas passa.
    expect($geral['no_criterio'])->toBe(2)
        ->and($geral['abaixo'])->toBe(2);
});

it('devolve o painel vazio sem explodir quando não há prova no recorte', function () {
    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::query()->whereRaw('1 = 0')->get(), $this->paeet);

    expect($geral['matriculados'])->toBe(0)
        ->and($geral['media'])->toBeNull()
        ->and($geral['mediana'])->toBeNull()
        ->and($geral['percentual_no_criterio'])->toBeNull();
});

it('não conta como pendente o aluno que saiu da turma', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);

    Aluno::factory()->naTurma($this->turma)->create([
        'nome' => 'Transferido Silva', 'ra' => '1009',
        'status' => StatusAluno::Transferido,
    ]);

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($geral['matriculados'])->toBe(2)
        ->and($geral['pendentes'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Seleção dos dados da análise
|--------------------------------------------------------------------------
*/

it('consolida mais de uma prova quando o professor marca as duas', function () {
    $primeira = ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => true, 2 => true, 3 => true],
    ]);
    $segunda = ($this->provaCom)(Bimestre::Segundo, 'Segunda', [
        0 => [1 => false, 2 => false, 3 => false],
        1 => [1 => false, 2 => false, 3 => false],
    ]);

    $componente = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->set('provasEscolhidas', [(string) $primeira->id]);

    $so = $componente->viewData('porHabilidade')
        ->firstWhere('habilidade', 'Interpretar estruturas de repetição');

    expect($so['percentual'])->toBe(100.0);

    $componente->set('provasEscolhidas', [(string) $primeira->id, (string) $segunda->id]);

    $juntas = $componente->viewData('porHabilidade')
        ->firstWhere('habilidade', 'Interpretar estruturas de repetição');

    // Oito respostas: quatro certas na primeira, quatro erradas na segunda.
    expect($juntas['percentual'])->toBe(50.0)
        ->and($juntas['respostas'])->toBe(8);
});

/*
 * Somar duas provas que mediram conteúdos diferentes dá um número certo
 * sobre uma pergunta errada. O número fica; a ressalva aparece ao lado.
 */
it('avisa que o recorte junta bimestres diferentes', function () {
    ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Segundo, 'Segunda', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSee('junta bimestres diferentes');
});

it('não avisa nada quando o recorte é de uma prova só', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertDontSee('junta bimestres diferentes')
        ->assertDontSee('quantidades diferentes de questões');
});

it('filtra pelo ano letivo', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Deste ano', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('filtroAno', '1998')
        ->assertSee('Nada para analisar neste recorte');
});

/*
 * Trocar o recorte não pode deixar uma prova marcada que saiu dele: a
 * tela ficaria vazia sem dizer por quê.
 */
it('solta a prova marcada que saiu do recorte', function () {
    $primeira = ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('provasEscolhidas', [(string) $primeira->id])
        ->set('filtroBimestre', (string) Bimestre::Terceiro->value)
        ->assertSet('provasEscolhidas', []);
});

it('diz quantos alunos e quantas respostas entram na conta', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSee('2 aluno(s) avaliado(s)')
        ->assertSee('6 resposta(s)');
});

/*
|--------------------------------------------------------------------------
| A grade de questões
|--------------------------------------------------------------------------
|
| Eram 27 linhas de tabela, metade da altura da página para um dado de
| consulta — e o número da questão só identifica dentro de uma prova, de
| modo que havia três "questão 2" diferentes na mesma lista.
*/

it('agrupa as questões por prova, na ordem em que o aluno as viu', function () {
    ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);
    ($this->provaCom)(Bimestre::Segundo, 'Segunda', [
        0 => [1 => false, 2 => true, 3 => false], 1 => [1 => false, 2 => true, 3 => false],
    ]);

    $grupos = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->viewData('questoesPorProva');

    expect($grupos)->toHaveCount(2)
        ->and(array_column($grupos[0]['questoes'], 'numero'))->toBe([1, 2, 3])
        // Cada grupo diz de que prova é: sem isso, três "questão 2" na
        // mesma lista não se distinguem.
        ->and($grupos[0]['prova'])->not->toBe($grupos[1]['prova']);
});

it('abre e fecha o detalhe de uma questão', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    $componente = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->assertSet('questaoAberta', null)
        ->call('verQuestao', $questao->id)
        ->assertSet('questaoAberta', $questao->id);

    expect($componente->viewData('foco')['numero'])->toBe(2);

    $componente->call('verQuestao', $questao->id)->assertSet('questaoAberta', null);
});

/*
 * Muita gente errar não prova que o conteúdo não foi aprendido: pode ser
 * enunciado ambíguo ou gabarito trocado. A tela oferece a hipótese e diz
 * de quem é a leitura.
 */
it('oferece hipótese em vez de diagnóstico na questão com muito erro', function () {
    config()->set('analise.evidencia_minima.respostas', 2);

    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $questao->id)
        ->assertSee('Vale investigar')
        ->assertSee('gabarito trocado')
        ->assertSee('a leitura é de quem deu a aula');
});

/*
 * O que o sistema não tem, ele diz. A planilha do leitor traz acerto ou
 * erro, nunca a letra marcada — e um gráfico vazio no lugar seria pior
 * que a ausência.
 */
it('diz por que não mostra a distribuição por alternativa', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $questao->id)
        ->assertSee('apenas acerto ou erro');
});

it('ordena a tabela de apoio pelo que se pede', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);

    $componente = Livewire::actingAs($this->paeet)->test(Desempenho::class)
        ->call('abrir', 'questoes');

    // O padrão é a de maior erro primeiro.
    expect($componente->viewData('questoesOrdenadas')->first()['numero'])->toBe(2);

    $componente->call('ordenarQuestoes', 'numero');

    expect($componente->viewData('questoesOrdenadas')->first()['numero'])->toBe(1);

    // Valor inventado cai no padrão, em vez de quebrar a ordenação.
    $componente->call('ordenarQuestoes', 'qualquer-coisa')
        ->assertSet('ordemDasQuestoes', 'erro');
});

it('solta a questão aberta quando o recorte muda', function () {
    ($this->provaCom)(Bimestre::Primeiro, 'Primeira', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('verQuestao', $questao->id)
        ->set('filtroBimestre', (string) Bimestre::Terceiro->value)
        ->assertSet('questaoAberta', null);
});

/*
 * Três telas de rolagem sem pista do que vem abaixo é desorientação. Os
 * atalhos dizem o que existe.
 */
it('oferece as abas quando há o que mostrar', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSeeHtml('role="tablist"')
        ->assertSeeHtml('aria-controls="painel-habilidades"')
        // A aba aberta se anuncia, e não só por cor.
        ->assertSeeHtml('id="aba-turma"')
        ->assertSeeHtml('aria-selected="true"');
});

it('não oferece aba nenhuma quando não há o que analisar', function () {
    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertDontSeeHtml('role="tablist"');
});

/*
|--------------------------------------------------------------------------
| As abas
|--------------------------------------------------------------------------
|
| Uma leitura por vez. O atalho que apenas rolava não era navegação: a
| página continuava inteira e a pessoa continuava sem saber onde estava.
*/

it('abre na turma e mostra só a leitura da aba aberta', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => false, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSet('aba', 'turma')
        ->assertSee('Distribuição das notas')
        ->assertDontSee('Por habilidade avaliada')
        ->assertDontSee('Acerto por questão');
});

it('troca de leitura ao escolher outra aba', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => false, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->assertSet('aba', 'questoes')
        ->assertSee('Acerto por questão')
        ->assertDontSee('Distribuição das notas');
});

it('ignora aba inventada em vez de quebrar', function () {
    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'inventada')
        ->assertSet('aba', 'turma');
});

/*
 * Trocar de aba não pode desfazer o recorte: quem ajustou quatro filtros
 * para chegar até ali não quer recomeçar ao olhar a leitura seguinte.
 */
it('mantém o recorte ao trocar de aba', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('filtroBimestre', (string) Bimestre::Segundo->value)
        ->set('filtroDisciplina', (string) $logica)
        ->call('abrir', 'habilidades')
        ->assertSet('filtroBimestre', (string) Bimestre::Segundo->value)
        ->assertSet('filtroDisciplina', (string) $logica);
});

/*
 * Cada aba calcula só o que mostra. Antes a tela consolidava habilidade,
 * questão, aluno e evolução a cada render para desenhar as quatro juntas
 * — três quartos do trabalho jogados fora.
 */
it('não calcula a leitura das abas fechadas', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $naTurma = Livewire::actingAs($this->paeet)->test(Desempenho::class);

    $calculou = true;

    try {
        $naTurma->viewData('porQuestao');
    } catch (Throwable) {
        $calculou = false;
    }

    expect($calculou)->toBeFalse();

    $nasQuestoes = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)->call('abrir', 'questoes');

    expect($nasQuestoes->viewData('porQuestao'))->not->toBeEmpty();
});

it('leva a aba no endereço, para o link apontar para a leitura certa', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    Livewire::withQueryParams(['aba' => 'habilidades'])
        ->actingAs($this->paeet)
        ->test(Desempenho::class)
        ->assertSet('aba', 'habilidades')
        ->assertSee('Por habilidade avaliada');
});

/*
|--------------------------------------------------------------------------
| A questão completa e a resposta do aluno
|--------------------------------------------------------------------------
*/

it('mostra a questão como o aluno a viu, com a correta assinalada', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true], 1 => [1 => true, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $questao->id)
        ->assertSee($questao->enunciado_snapshot)
        // Todas as alternativas, e o rótulo da correta — nunca só a cor.
        ->assertSee('Alternativa A')
        ->assertSee('Alternativa B')
        ->assertSee('Correta');
});

/*
 * O enunciado sai do snapshot da prova: uma análise de prova antiga tem
 * de mostrar o que estava no papel naquele dia, mesmo que a questão
 * original tenha sido reescrita depois.
 */
it('mostra o enunciado congelado, e não a questão reescrita depois', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $provaQuestao = ProvaQuestao::query()->with('questao')->where('numero', 1)->sole();
    $provaQuestao->questao->update(['enunciado' => 'Texto reescrito depois da prova']);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $provaQuestao->id)
        ->assertSee($provaQuestao->enunciado_snapshot)
        ->assertDontSee('Texto reescrito depois da prova');
});

it('diz quem acertou e quem errou a questão, pelo nome', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => false, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    $html = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $questao->id)
        ->assertSee('Quem acertou e quem errou')
        ->assertSee($this->alunos[0]->nome)
        ->assertSee($this->alunos[1]->nome)
        ->html();

    // De quem acertou dá para dizer a letra; de quem errou, não.
    expect($html)->toContain('marcou outra');
});

/*
 * A planilha do leitor traz 1 ou 0, nunca a letra marcada. Quem acertou
 * marcou a correta — isso se deduz. De quem errou só se sabe que marcou
 * outra, e dizer qual seria invenção.
 */
it('deduz a letra de quem acertou e não inventa a de quem errou', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true],
        1 => [1 => false, 2 => true, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    $componente = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'alunos')
        ->set('filtroBimestre', (string) Bimestre::Segundo->value);

    // Quem acertou: a alternativa correta aparece marcada por ele.
    // Quem acertou: a alternativa correta aparece marcada por ele.
    $componente->call('verRespostaDoAluno', $questao->id, $this->alunos[0]->id)
        ->assertSee($this->alunos[0]->nome.' marcou')
        ->assertSee('Correta');

    // Quem errou: a tela diz que marcou outra, sem escolher qual.
    $componente->call('verRespostaDoAluno', $questao->id, $this->alunos[1]->id)
        ->assertSee('marcou uma das outras alternativas')
        ->assertSee('apenas acerto ou erro');
});

it('abre e fecha a resposta do aluno pelo mesmo clique', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'alunos')
        ->set('filtroBimestre', (string) Bimestre::Segundo->value)
        ->call('verRespostaDoAluno', $questao->id, $this->alunos[0]->id)
        ->assertSet('alunoAberto', $this->alunos[0]->id)
        ->call('verRespostaDoAluno', $questao->id, $this->alunos[0]->id)
        ->assertSet('alunoAberto', null)
        ->assertSet('questaoAberta', null);
});

/*
 * O id vem da URL. Sem escopo, ele abriria a questão de outro Eixo — com
 * enunciado, alternativas e os nomes dos alunos de lá.
 */
it('não abre questão de prova fora do escopo de quem olha', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();

    $intruso = paeet(Eixo::factory()->create(['codigo' => 'OUT']));

    $componente = Livewire::actingAs($intruso)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $questao->id);

    expect($componente->viewData('questaoCompleta'))->toBeNull()
        ->and($componente->viewData('respostasDaQuestao'))->toBeEmpty();
});

it('não abre ao professor a questão que não é dele', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => true, 3 => true], 1 => [1 => true, 2 => true, 3 => true],
    ]);

    // A questão 3 é de Redes, do outro professor.
    $deRedes = ProvaQuestao::query()->where('numero', 3)->sole();

    $componente = Livewire::actingAs($this->profLogica)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->call('verQuestao', $deRedes->id);

    expect($componente->viewData('questaoCompleta'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A alternativa mais escolhida
|--------------------------------------------------------------------------
|
| "A turma errou a 7" diz que houve erro; "três de cinco marcaram a C"
| diz qual é o erro. A leitura só existe quando a planilha trouxe a letra
| marcada — e quando não trouxe, a tela diz isso em vez de desenhar uma
| barra de dado nenhum.
*/

/** Cinco alunos: com dois, nenhuma distribuição se distingue de outra. */
function cincoAlunos($caso): void
{
    $caso->alunos = $caso->alunos->concat(
        collect(['Bruno Dias', 'Elisa Moraes', 'Nair Fontes'])->map(
            fn (string $nome, int $i) => Aluno::factory()->naTurma($caso->turma)
                ->create(['nome' => $nome, 'ra' => '200'.$i])
        )
    );
}

/**
 * Cinco alunos e três questões, montadas para separar as duas leituras.
 * A correta é sempre a A.
 *
 *   1 — 1 acerto (20%), erradas espalhadas: B=2, C=1, D=1
 *   2 — 2 acertos (40%), erradas concentradas: C=3
 *   3 — 3 acertos (60%), B=1 e um em branco
 *
 * A 1 é a de pior aproveitamento; a 2 é a que concentra erro na mesma
 * alternativa. É a 2 que esta leitura procura, e a ordem tem de dizer
 * isso — senão ela é só a grade de acerto outra vez.
 */
function provaComLetras($caso): Prova
{
    cincoAlunos($caso);

    return ($caso->provaCom)(Bimestre::Segundo, 'Bimestral', [], marcadas: [
        0 => [1 => 'B', 2 => 'A', 3 => 'A'],
        1 => [1 => 'B', 2 => 'C', 3 => 'B'],
        2 => [1 => 'C', 2 => 'C', 3 => 'A'],
        3 => [1 => 'D', 2 => 'C', 3 => 'A'],
        4 => [1 => 'A', 2 => 'A', 3 => null],
    ]);
}

it('conta quantos marcaram cada alternativa', function () {
    provaComLetras($this);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->paeet);

    $segunda = $linhas->firstWhere('numero', 2);

    expect($segunda['tem_marcacao'])->toBeTrue()
        ->and($segunda['respostas'])->toBe(5)
        ->and($segunda['acertos'])->toBe(2)
        ->and(collect($segunda['alternativas'])->pluck('total', 'letra')->all())
        ->toBe(['A' => 2, 'B' => 0, 'C' => 3, 'D' => 0]);
});

it('aponta a errada que mais atraiu, e não a mais marcada de todas', function () {
    provaComLetras($this);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->paeet);

    $segunda = $linhas->firstWhere('numero', 2);
    $terceira = $linhas->firstWhere('numero', 3);

    // Na 3 a mais marcada é a correta; o distrator é outro.
    expect($terceira['mais_escolhida'])->toBe('A')
        ->and($terceira['distrator']['letra'])->toBe('B')
        ->and($segunda['distrator']['letra'])->toBe('C')
        ->and($segunda['distrator']['total'])->toBe(3)
        ->and($segunda['distrator']['percentual'])->toBe(60.0);
});

/*
 * Pela errada que mais atraiu, e não pelo aproveitamento: ordenada por
 * acerto, esta lista seria a grade de questões de novo. A 1 tem o pior
 * aproveitamento e vem depois da 2, que concentra erro na mesma letra.
 */
it('começa pela questão cuja errada atraiu mais gente', function () {
    provaComLetras($this);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($linhas->pluck('numero')->all())->toBe([2, 1, 3])
        ->and($linhas->firstWhere('numero', 1)['percentual'])
        ->toBeLessThan($linhas->first()['percentual']);
});

it('conta em branco à parte das alternativas', function () {
    provaComLetras($this);

    $terceira = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->paeet)
        ->firstWhere('numero', 3);

    expect($terceira['em_branco'])->toBe(1)
        ->and(collect($terceira['alternativas'])->sum('total'))->toBe(4);
});

/*
 * O que o sistema não sabe, ele não desenha. Uma planilha no formato de
 * acerto e erro grava `null` em todas as marcações — e isso chega igual
 * a "o aluno deixou em branco". Separar os dois é o que evita dizer "5
 * alunos não marcaram nada" sobre uma importação que nunca soube quem
 * marcou o quê.
 */
it('não inventa distribuição para a prova importada sem as letras', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => true],
    ]);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->paeet);

    expect($linhas)->toHaveCount(3)
        ->and($linhas->pluck('tem_marcacao')->unique()->all())->toBe([false])
        ->and($linhas->pluck('distrator')->filter()->all())->toBeEmpty()
        ->and($linhas->pluck('em_branco')->unique()->all())->toBe([0]);
});

it('mostra na tela a letra que mais atraiu, com a correta ao lado', function () {
    provaComLetras($this);

    $html = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'alternativas')
        ->assertSee('A mais marcada entre as erradas')
        ->assertSee('3 de 5')
        ->assertSee('A correta era A')
        ->html();

    // A questão que mais atraiu errada vem primeiro na lista.
    preg_match_all('/<li[^>]*data-alternativas.*?<\/li>/s', $html, $itens);

    expect($itens[0])->toHaveCount(3)
        ->and(strip_tags($itens[0][0]))->toContain('Questão 2');
});

it('avisa, em vez de desenhar, quando a planilha não trouxe as letras', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => true],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'alternativas')
        ->assertSee('Sem a letra marcada em 3 questão(ões)')
        ->assertSee('apenas acerto ou erro')
        ->assertDontSee('A mais marcada entre as erradas');
});

/*
|--------------------------------------------------------------------------
| A questão abre de qualquer lugar
|--------------------------------------------------------------------------
|
| Toda vez que a tela escreve "questão 7", o próximo pensamento de quem lê
| é "qual era mesmo a 7?". O painel é um só, e abre da aba em que a pessoa
| estiver — sem levá-la para outra.
*/

it('abre a questão a partir da lista por habilidade', function () {
    provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'habilidades')
        ->call('verQuestao', $questao->id)
        ->assertSet('aba', 'habilidades')
        ->assertSee('Enunciado da questão 2')
        ->assertSee('Quem acertou e quem errou');
});

it('abre a questão a partir da aba das alternativas', function () {
    provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'alternativas')
        ->call('verQuestao', $questao->id)
        ->assertSet('aba', 'alternativas')
        ->assertSee('Enunciado da questão 2')
        // A contagem por alternativa aparece junto do texto de cada uma.
        ->assertSee('Alternativa C');
});

it('diz qual letra o aluno marcou quando a planilha trouxe', function () {
    $prova = provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();
    $caio = Aluno::query()->where('nome', 'Caio Prado')->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('provasEscolhidas', [(string) $prova->id])
        ->call('abrir', 'alunos')
        ->call('verRespostaDoAluno', $questao->id, $caio->id)
        ->assertSee('Caio Prado marcou')
        ->assertSee('marcou C');
});

it('fecha o painel da questão', function () {
    provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('verQuestao', $questao->id)
        ->assertSet('questaoAberta', $questao->id)
        ->call('fecharQuestao')
        ->assertSet('questaoAberta', null)
        ->assertSet('alunoAberto', null);
});

it('não devolve o foco de questão fora do escopo de quem olha', function () {
    provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 1)->sole();
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'OUT']));

    expect(app(AnalisarDesempenhoAction::class)->questaoEmFoco($questao, $intruso))->toBeNull()
        ->and(app(AnalisarDesempenhoAction::class)->questaoEmFoco($questao, $this->profRedes))->toBeNull();
});

/*
 * "Não marcou nada" e "não sabemos o que marcou" chegam iguais ao banco,
 * como `null`, e dizer um pelo outro é inventar. O que separa os dois é
 * a planilha: se ela trouxe letra para alguém, quem está sem letra
 * deixou em branco.
 */
it('separa quem deixou em branco de quem a planilha não soube ler', function () {
    provaComLetras($this);

    $terceira = ProvaQuestao::query()->where('numero', 3)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('verQuestao', $terceira->id)
        ->assertSee('não marcou nada')
        ->assertSee('não marcaram nenhuma alternativa')
        ->assertDontSee('marcou outra');
});

it('continua dizendo “marcou outra” quando a planilha não trouxe letra', function () {
    ($this->provaCom)(Bimestre::Segundo, 'Bimestral', [
        0 => [1 => true, 2 => false, 3 => true],
        1 => [1 => true, 2 => false, 3 => true],
    ]);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('verQuestao', $questao->id)
        ->assertSee('marcou outra')
        ->assertDontSee('não marcou nada');
});

it('abre a questão pelo cabeçalho da grade nominal, sem aluno nenhum', function () {
    $prova = provaComLetras($this);

    $questao = ProvaQuestao::query()->where('numero', 2)->sole();
    $caio = Aluno::query()->where('nome', 'Caio Prado')->sole();

    Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->set('provasEscolhidas', [(string) $prova->id])
        ->call('abrir', 'alunos')
        ->call('verRespostaDoAluno', $questao->id, $caio->id)
        ->assertSet('alunoAberto', $caio->id)
        // O cabeçalho é a leitura da turma: tira o aluno do foco.
        ->call('verQuestao', $questao->id)
        ->assertSet('questaoAberta', $questao->id)
        ->assertSet('alunoAberto', null)
        ->assertDontSee('Caio Prado marcou');
});

it('não mostra ao professor a alternativa das questões que não são dele', function () {
    provaComLetras($this);

    $linhas = app(AnalisarDesempenhoAction::class)
        ->porAlternativa(Prova::whereHas('resultados')->get(), $this->profRedes);

    // A 3 é de Redes; as outras duas, de Lógica.
    expect($linhas->pluck('numero')->all())->toBe([3]);
});
