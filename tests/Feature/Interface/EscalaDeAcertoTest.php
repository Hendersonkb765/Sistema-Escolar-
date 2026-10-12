<?php

/*
 * A escala de cor da grade de acerto por questão.
 *
 * A cor das células não sai de uma classe utilitária: é uma regra escrita
 * à mão em `resources/css/app.css`, e as classes são montadas na view a
 * partir de uma lista. Já aconteceu de a lista ser construída em tempo de
 * execução (`'degrau-'.$n`): o Tailwind varre o código-fonte, não
 * encontrou a classe escrita, removeu a regra do bundle, e a grade saiu
 * inteira sem cor nenhuma — sem erro em lugar nenhum.
 *
 * Este teste amarra as duas pontas: toda classe que a tela usa tem regra
 * no CSS, nos dois temas, com fundo E texto declarados. Meia regra pinta
 * o fundo e deixa o texto na cor herdada, que é como se perde contraste
 * sem ninguém perceber.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\CalcularNotasAction;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Livewire\Analises\Desempenho;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    // Seis alunos: com menos, nem todo degrau da escala é alcançável.
    $alunos = collect(range(1, 6))->map(
        fn (int $i) => Aluno::factory()->naTurma($this->turma)
            ->create(['nome' => 'Aluno '.$i, 'ra' => '90'.$i])
    );

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $professor, 'questoes' => 5],
    ], titulo: 'Bimestral');

    enviarParte($solicitacao->partes()->sole(), $professor);

    foreach ($solicitacao->questoes()->get() as $questao) {
        app(AnalisarQuestaoAction::class)->aprovar($questao, $this->paeet);
    }

    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Bimestral',
    );
    $prova->update(['status' => StatusProva::Aplicada]);

    $importacao = Importacao::create([
        'prova_id' => $prova->id, 'usuario_id' => $this->paeet->id,
        'arquivo' => 'importacoes/x.xlsx', 'hash' => hash('sha256', 'escala'),
        'status' => StatusImportacao::Confirmada, 'confirmada_em' => now(),
    ]);

    $questoes = $prova->questoes()->orderBy('numero')->get();
    $calcular = app(CalcularNotasAction::class);

    /*
     * A questão N é acertada por N alunos: 1 de 6 (17%), 2 (33%), 3 (50%),
     * 4 (67%), 5 (83%). Cinco aproveitamentos, um em cada degrau.
     */
    foreach ($alunos->values() as $indice => $aluno) {
        $resultado = ResultadoAluno::create([
            'prova_id' => $prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $importacao->id,
        ]);

        foreach ($questoes as $questao) {
            $acertou = $indice < $questao->numero;

            $resultado->respostas()->create([
                'prova_questao_id' => $questao->id, 'acertou' => $acertou,
                'peso' => $questao->peso, 'pontuacao' => $acertou ? $questao->peso : 0,
            ]);
        }

        $calcular->executar($resultado->refresh());
    }

    $this->css = file_get_contents(resource_path('css/app.css'));
});

it('define no CSS todo degrau que a tela pinta', function () {
    $html = Livewire::actingAs($this->paeet)
        ->test(Desempenho::class)
        ->call('abrir', 'questoes')
        ->html();

    preg_match_all('/degrau-(\d+)/', $html, $achados);

    $usados = collect($achados[1])->unique()->sort()->values();

    // A grade e a legenda juntas alcançam os cinco.
    expect($usados->all())->toBe(['0', '1', '2', '3', '4']);

    /*
     * Escrita por extenso em algum .blade, que é a condição do Tailwind:
     * ele varre o código-fonte e remove do bundle toda classe que não
     * encontrar literal. Montada em tempo de execução (`'degrau-'.$n`), a
     * regra existe no arquivo, some do bundle, e a grade sai sem cor
     * nenhuma — sem erro em lugar nenhum.
     */
    $fontes = collect(glob(resource_path('views/**/*.blade.php'), GLOB_BRACE))
        ->merge(glob(resource_path('views/*.blade.php')))
        ->merge(glob(resource_path('views/*/*/*.blade.php')))
        ->map(fn (string $arquivo) => file_get_contents($arquivo))
        ->implode("\n");

    foreach ($usados as $degrau) {
        expect($fontes)->toContain("'degrau-".$degrau."'");

        foreach (['.degrau-'.$degrau, '.dark .degrau-'.$degrau] as $seletor) {
            expect($this->css)->toContain($seletor.' {');

            preg_match('/'.preg_quote($seletor, '/').' \{([^}]*)\}/', $this->css, $regra);

            expect($regra[1] ?? '')->toContain('background:')
                ->and($regra[1] ?? '')->toContain('color:');
        }
    }
});

/*
 * A escala vai de vermelho a verde, e não de um tom só: tudo vermelho
 * fazia uma questão com 90% de acerto parecer problema. O meio é o
 * amarelo, onde cai o critério da escola.
 */
it('vai do vermelho ao verde, passando pelo amarelo', function () {
    /*
     * Matiz e saturação, e não só matiz: o vermelho fica em 0°, que também
     * é 360°, e um rosa quase branco (#fff1f2, 356°) passaria por "mais
     * verde que o amarelo" numa escala que não desse a volta. Por isso o
     * matiz é trazido para perto de zero, e a saturação tem piso — tom
     * lavado não é um degrau da escala, é a escala apagada.
     */
    $cor = function (string $seletor): array {
        preg_match('/'.preg_quote($seletor, '/').' \{[^}]*background: (#[0-9a-f]{6})/i', $this->css, $achado);

        [$r, $g, $b] = array_map(fn (string $par) => hexdec($par) / 255, str_split(substr($achado[1], 1), 2));

        $maior = max($r, $g, $b);
        $menor = min($r, $g, $b);
        $amplitude = $maior - $menor;

        if ($amplitude == 0.0) {
            return ['matiz' => 0.0, 'saturacao' => 0.0];
        }

        $h = match (true) {
            $maior === $r => fmod((($g - $b) / $amplitude) + 6, 6),
            $maior === $g => (($b - $r) / $amplitude) + 2,
            default => (($r - $g) / $amplitude) + 4,
        } * 60;

        return [
            // 356° é vermelho, não "além do verde": a roda dá a volta.
            'matiz' => $h > 300 ? $h - 360 : $h,
            'saturacao' => $amplitude / $maior,
        ];
    };

    foreach (['', '.dark '] as $tema) {
        $cores = collect(range(0, 4))->map(fn (int $n) => $cor($tema.'.degrau-'.$n));
        $matizes = $cores->pluck('matiz');

        // Vermelho (0°) → laranja → amarelo (60°) → verde (120°+).
        expect($matizes[0])->toBeLessThan(20.0)
            ->and($matizes[2])->toBeGreaterThan(40.0)->toBeLessThan(70.0)
            ->and($matizes[4])->toBeGreaterThan(100.0);

        // E sempre subindo: nenhum degrau volta para trás na escala.
        foreach (range(0, 3) as $n) {
            expect($matizes[$n + 1])->toBeGreaterThan($matizes[$n]);
        }

        foreach ($cores as $n => $cada) {
            expect($cada['saturacao'])->toBeGreaterThan(
                0.3,
                "O degrau {$n} do tema '{$tema}' está lavado demais para a escala."
            );
        }
    }
});
