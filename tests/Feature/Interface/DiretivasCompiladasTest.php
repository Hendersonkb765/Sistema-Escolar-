<?php

/*
 * Nenhuma diretiva do Blade pode sobrar no HTML entregue.
 *
 * Dentro da tag de um componente (`<x-botao @js(...)>`) o Blade não
 * compila a diretiva: ela sai literal e o atributo não funciona. O
 * botão fica na tela, com a aparência certa, e não faz nada — foi assim
 * que o "Ver alunos" nasceu quebrado, e antes dele o `@disabled` das
 * alternativas.
 *
 * Nenhum `assertSee` pega isso, porque o rótulo continua lá. Este teste
 * varre o HTML das telas atrás da diretiva crua.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\CalcularNotasAction;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Illuminate\Support\Facades\Route;

/*
 * Telas sem parâmetro que a varredura cobre.
 *
 * Os formulários de criar entram por um motivo específico: é neles que
 * vivem `@disabled`, `@checked` e `@readonly`, as diretivas que o Blade
 * não compila dentro da tag de um componente. Uma listagem raramente tem
 * campo de formulário; um formulário só tem isso.
 */
const TELAS_VARRIDAS = [
    'painel', 'eixos.index', 'eixos.criar', 'cursos.index', 'cursos.criar',
    'disciplinas.index', 'disciplinas.criar', 'grades.index',
    'turmas.index', 'turmas.criar', 'alunos.index', 'alunos.criar',
    'usuarios.index', 'usuarios.criar', 'auditoria.index',
    'solicitacoes.index', 'solicitacoes.criar', 'questoes.index',
    'provas.index', 'provas.criar', 'modelos-prova.index', 'modelos-prova.criar',
    'importacoes.index', 'importacoes.criar', 'resultados.index', 'analises.index',
];

/*
 * Telas de fora da varredura, e por quê. Toda entrada é uma decisão.
 */
const TELAS_FORA_DA_VARREDURA = [
    // Redireciona para o painel ou para o login; não tem HTML próprio.
    'inicio',
    // Devolve PDF, não HTML — não há Blade para varrer.
    'resultados.boletim',
    // Telas de autenticação: varridas em DiretivasNasTelasPublicasTest,
    // porque exigem estar deslogado ou com a senha ainda provisória.
    'login', 'password.request', 'primeira-senha', 'two-factor.login',
];

/** As diretivas que se costuma escrever dentro de uma tag por engano. */
beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);

    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->outro = professor($this->eixo);
    $this->outro->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->admin);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    foreach ([['Marina Alves', '1001'], ['Caio Prado', '1002']] as [$nome, $ra]) {
        Aluno::factory()->naTurma($this->turma)->create(['nome' => $nome, 'ra' => $ra]);
    }

    $solicitacao = solicitacaoCom($this->admin, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->professor, 'questoes' => 2],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->outro, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    $professores = [$this->professor, $this->outro];

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
        enviarParte($parte, $professores[$indice], [
            1 => 'Aplicar condicionais', 2 => 'Aplicar condicionais',
        ]);
    }

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->admin);
    }

    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->admin, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Avaliação bimestral',
    );
    $this->prova->update(['status' => StatusProva::Aplicada]);

    $importacao = Importacao::create([
        'prova_id' => $this->prova->id, 'usuario_id' => $this->admin->id,
        'arquivo' => 'importacoes/x.xlsx', 'hash' => hash('sha256', 'x'),
        'status' => StatusImportacao::Confirmada, 'confirmada_em' => now(),
    ]);

    $calcular = app(CalcularNotasAction::class);

    foreach ($this->turma->alunos()->get() as $indice => $aluno) {
        $resultado = ResultadoAluno::create([
            'prova_id' => $this->prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $importacao->id,
        ]);

        foreach ($this->prova->questoes()->get() as $questao) {
            $acertou = ($questao->numero + $indice) % 2 === 0;

            $resultado->respostas()->create([
                'prova_questao_id' => $questao->id, 'acertou' => $acertou,
                'peso' => $questao->peso, 'pontuacao' => $acertou ? $questao->peso : 0,
            ]);
        }

        $calcular->executar($resultado->refresh());
    }

    /** Diretivas cruas que sobraram no HTML. A varredura mora em tests/Pest.php. */
    $this->sobras = fn (string $html): array => sobrasDeDiretiva($html);
});

it('não deixa diretiva crua no HTML de tela nenhuma', function (string $rota) {
    $html = $this->actingAs($this->admin)->get(route($rota))->assertOk()->getContent();

    expect(($this->sobras)($html))->toBe([]);
})->with(TELAS_VARRIDAS);

/*
 * A lista acima é escrita à mão, para poder levar comentário. O preço
 * disso é envelhecer calada: quem cria uma tela nova não é avisado de que
 * ela ficou fora. Este teste cobra.
 */
it('não deixa tela nenhuma fora da varredura', function () {
    $doRoteador = collect(Route::getRoutes())
        ->filter(fn ($rota) => in_array('GET', $rota->methods(), true))
        ->reject(fn ($rota) => str_contains($rota->uri(), '{'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'livewire/'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'storage/'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'user/'))
        ->map(fn ($rota) => $rota->getName())
        ->filter()
        ->values()
        ->all();

    $classificadas = array_merge(TELAS_VARRIDAS, TELAS_FORA_DA_VARREDURA);
    $esquecidas = array_diff($doRoteador, $classificadas);

    expect($esquecidas)->toBeEmpty(
        'Tela fora da varredura de diretivas: '.implode(', ', $esquecidas).
        '. Acrescente em TELAS_VARRIDAS, ou em TELAS_FORA_DA_VARREDURA com o motivo.'
    );
});

it('não deixa diretiva crua nas telas com parâmetro', function () {
    $como = fn (string $rota, $parametro) => $this->actingAs($this->admin)
        ->get(route($rota, $parametro))->assertOk()->getContent();

    foreach ([
        ['provas.show', $this->prova],
        ['resultados.index', ['prova' => $this->prova->id]],
        ['analises.index', ['bimestre' => $this->prova->bimestre->value]],
        ['turmas.show', $this->turma],
    ] as [$rota, $parametro]) {
        expect(($this->sobras)($como($rota, $parametro)))->toBe([]);
    }
});

it('não deixa diretiva crua na tela do professor', function () {
    $solicitacao = SolicitacaoProva::query()->sole();

    $html = $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $solicitacao))
        ->assertOk()
        ->getContent();

    expect(($this->sobras)($html))->toBe([]);
});

it('pega a diretiva quando ela realmente escapa', function () {
    // O teste acima só vale se este padrão de fato casar com o defeito.
    $quebrado = '<button wire:click="verAlunos(@js($item[\'habilidade\']))">Ver alunos</button>';

    expect(($this->sobras)($quebrado))->toBe(['@js(']);
});

it('deixa passar o HTML que compilou', function () {
    $correto = '<button wire:click="verAlunos(\'Aplicar condicionais\')">Ver alunos</button>';

    expect(($this->sobras)($correto))->toBe([]);
});
