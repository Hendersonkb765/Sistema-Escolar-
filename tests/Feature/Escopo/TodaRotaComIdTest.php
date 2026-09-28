<?php

/*
 * Nenhuma rota com id na URL escapa do escopo por Eixo.
 *
 * Os testes de IDOR existentes cobrem um recurso cada. O que falta a eles
 * é notar a rota que ainda não existe: alguém acrescenta
 * `/relatorios/{prova}` amanhã, esquece a Policy, e nenhum teste reclama
 * — porque nenhum teste sabia daquela rota.
 *
 * Aqui a lista de rotas vem do roteador, não de um `->with([...])`
 * escrito à mão. Rota com id que ninguém classificou derruba a suíte, e
 * quem a criou precisa dizer em qual caso ela cai.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\ModeloDocumento;
use App\Models\ModeloProva;
use App\Models\Turma;
use Illuminate\Support\Facades\Route;

/*
 * Rotas com parâmetro que NÃO são recurso escopado por Eixo, e por quê.
 * Toda entrada aqui é uma decisão consciente.
 */
const FORA_DO_ESCOPO_DE_EIXO = [
    // Público por natureza: o token é a credencial, e quem o tem ainda
    // não está autenticado. Coberto em RecuperacaoDeSenhaTest.
    'password.reset',
    // Usuário não pertence a um Eixo: pertence a vários. O limite de quem
    // pode editar quem está em CriacaoDeUsuariosTest.
    'usuarios.editar',
];

beforeEach(function () {
    $this->tecnologia = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->administracao = Eixo::factory()->create(['codigo' => 'ADM']);

    $this->intruso = paeet($this->tecnologia);

    // Tudo o que segue pertence ao Eixo de Administração.
    $dono = paeet($this->administracao);
    $professor = professor($this->administracao);

    $montagem = cursoComGrade($this->administracao, [1 => ['Contabilidade']], autor: $dono);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $aluno = Aluno::factory()->naTurma($turma)->create();

    $modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->administracao->id,
        'criado_por' => $dono->id,
    ]);

    $documento = ModeloDocumento::factory()->noEixo($this->administracao)->create([
        'criado_por' => $dono->id,
    ]);

    $solicitacao = solicitacaoCom($dono, $turma, [
        ['disciplina' => $montagem['disciplinas']['Contabilidade'], 'professor' => $professor, 'questoes' => 2],
    ]);

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $parte) {
        enviarParte($parte, $professor);
    }

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $dono);
    }

    $prova = app(MontarProvaAction::class)->executar(
        autor: $dono,
        turma: $turma,
        modelo: $modelo,
        titulo: 'Avaliação alheia',
    );

    $this->alheios = [
        'alunos.editar' => $aluno,
        'cursos.show' => $montagem['curso'],
        'cursos.editar' => $montagem['curso'],
        'disciplinas.editar' => $montagem['disciplinas']['Contabilidade'],
        'eixos.editar' => $this->administracao,
        'grades.show' => $montagem['grade'],
        'modelos-prova.editar' => $modelo,
        'documentos.editar' => $documento,
        'provas.show' => $prova,
        'provas.pdf' => $prova,
        'provas.docx' => $prova,
        'provas.gabarito' => $prova,
        'solicitacoes.show' => $solicitacao,
        'solicitacoes.responder' => $solicitacao,
        'turmas.show' => $turma,
        'turmas.editar' => $turma,
    ];

    /*
     * O boletim recebe o id por query string (`?turma=`), e não no
     * caminho. Rota assim não aparece na varredura por `{`, então entra
     * aqui à mão — junto com o lembrete de que pode haver outras.
     */
    $this->porQueryString = [
        'resultados.boletim' => ['turma' => $turma->id],
    ];
});

/** Toda rota GET com parâmetro, tirando a infraestrutura do framework. */
function rotasComId(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($rota) => in_array('GET', $rota->methods(), true))
        ->filter(fn ($rota) => str_contains($rota->uri(), '{'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'livewire/'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'storage/'))
        ->reject(fn ($rota) => str_starts_with($rota->uri(), 'user/'))
        ->map(fn ($rota) => $rota->getName())
        ->filter()
        ->values()
        ->all();
}

it('não deixa rota com id fora da conta', function () {
    $classificadas = array_merge(array_keys($this->alheios), FORA_DO_ESCOPO_DE_EIXO);

    $esquecidas = array_diff(rotasComId(), $classificadas);

    expect($esquecidas)->toBeEmpty(
        'Rota com id sem classificação: '.implode(', ', $esquecidas).
        '. Ou ela é escopada por Eixo — e entra no fixture — ou não é, e entra '.
        'em FORA_DO_ESCOPO_DE_EIXO com o motivo.'
    );
});

it('responde 403 a quem é de outro Eixo', function () {
    foreach ($this->alheios as $rota => $registro) {
        $this->actingAs($this->intruso)
            ->get(route($rota, $registro))
            ->assertForbidden("A rota {$rota} deixou passar um PAEET de outro Eixo.");
    }
});

it('responde 403 a quem é de outro Eixo também com id na query string', function () {
    foreach ($this->porQueryString as $rota => $parametros) {
        $this->actingAs($this->intruso)
            ->get(route($rota, $parametros))
            ->assertForbidden("A rota {$rota} deixou passar um PAEET de outro Eixo.");
    }
});

it('responde 403 ao professor nas rotas de gestão', function () {
    $intruso = professor($this->tecnologia);

    foreach ($this->alheios as $rota => $registro) {
        $this->actingAs($intruso)
            ->get(route($rota, $registro))
            ->assertForbidden("A rota {$rota} deixou passar um professor de outro Eixo.");
    }

    foreach ($this->porQueryString as $rota => $parametros) {
        $this->actingAs($intruso)
            ->get(route($rota, $parametros))
            ->assertForbidden("A rota {$rota} deixou passar um professor de outro Eixo.");
    }
});
