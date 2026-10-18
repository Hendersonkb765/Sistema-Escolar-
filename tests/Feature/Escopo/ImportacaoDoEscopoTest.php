<?php

/*
 * O escopo-sequência: o que se ensina em cada semana do curso.
 *
 * O arquivo vem pronto da rede e a escola o recebe — não o escreve. Daí
 * as duas regras que este teste cobre: o cabeçalho é procurado por nome
 * (sem depender de ordem, caixa ou acento) e o texto entra como veio,
 * porque inventar significado para uma coluna que ninguém daqui
 * controla é como o conteúdo do arquivo vira um dado errado que parece
 * certo.
 */

use App\Actions\Academico\ImportarEscopoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Escopo\ImportarEscopo;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\EscopoAula;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

/** O cabeçalho do arquivo, como a rede o publica — com a grafia torta e tudo. */
const CABECALHO_DO_ESCOPO = [
    'Aula complementar', 'Bimestre', 'CH teórica/prática', 'Competência técnica',
    'Competências socioemocionais', 'Nome do componente', 'Código componente',
    'Componente de 3 ou 4 aulas semanais?', 'Unidade curricular',
    'Código unidade curricular', 'Semana', 'Tema da semana', 'Título da aula',
    'Habilidades técnicas', 'Habildades socioemocionais', 'Objetivos da aula',
    'Objeto de conhecimento',
];

/**
 * Uma linha do escopo, na ordem do cabeçalho.
 *
 * @param  array<string, mixed>  $campos
 * @return array<int, mixed>
 */
function linhaDoEscopo(array $campos = []): array
{
    $padrao = [
        'Aula complementar' => 'Não',
        'Bimestre' => 1,
        'CH teórica/prática' => '2/1',
        'Competência técnica' => 'Desenvolver algoritmos',
        'Competências socioemocionais' => 'Organização',
        'Nome do componente' => 'Lógica de Programação',
        'Código componente' => 'LLP',
        'Componente de 3 ou 4 aulas semanais?' => '4',
        'Unidade curricular' => 'Introdução à lógica',
        'Código unidade curricular' => 'UC01',
        'Semana' => 1,
        'Tema da semana' => 'Primeiros algoritmos',
        'Título da aula' => 'O que é um algoritmo',
        'Habilidades técnicas' => 'Identificar passos',
        'Habildades socioemocionais' => 'Escuta ativa',
        'Objetivos da aula' => 'Reconhecer sequências',
        'Objeto de conhecimento' => 'Sequência lógica',
    ];

    return array_values(array_merge($padrao, $campos));
}

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->disciplinas = $montagem['disciplinas'];

    // O código da disciplina é o que casa com o componente da planilha.
    $this->disciplinas['Lógica de Programação']->update(['codigo' => 'LLP']);

    $this->action = app(ImportarEscopoAction::class);

    /**
     * Escreve um arquivo do escopo e devolve o caminho.
     *
     * @param  array<int, array<int, mixed>>  $linhas
     */
    $this->arquivo = function (array $linhas, ?array $cabecalho = null, string $formato = 'xlsx'): string {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();

        $aba->fromArray($cabecalho ?? CABECALHO_DO_ESCOPO, null, 'A1');
        $aba->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'escopo').'.'.$formato;

        $escritor = match ($formato) {
            'csv' => new Csv($planilha),
            'ods' => new Ods($planilha),
            default => new Xlsx($planilha),
        };

        $escritor->save($caminho);

        return $caminho;
    };
});

/*
|--------------------------------------------------------------------------
| A leitura
|--------------------------------------------------------------------------
*/

it('importa as aulas da planilha e as guarda como vieram', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Semana' => 2, 'Título da aula' => 'Variáveis']),
    ]);

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    $aulas = EscopoAula::query()->orderBy('semana')->get();

    expect($aulas)->toHaveCount(2);

    $primeira = $aulas->first();

    expect($primeira->curso_id)->toBe($this->curso->id)
        ->and($primeira->periodo)->toBe(1)
        ->and($primeira->bimestre)->toBe(1)
        ->and($primeira->semana)->toBe(1)
        ->and($primeira->titulo_aula)->toBe('O que é um algoritmo')
        ->and($primeira->tema_semana)->toBe('Primeiros algoritmos')
        ->and($primeira->objetivos)->toBe('Reconhecer sequências')
        ->and($primeira->objeto_conhecimento)->toBe('Sequência lógica')
        ->and($primeira->habilidades_tecnicas)->toBe('Identificar passos')
        // A coluna com a grafia torta do arquivo também é lida.
        ->and($primeira->habilidades_socioemocionais)->toBe('Escuta ativa')
        ->and($primeira->competencia_tecnica)->toBe('Desenvolver algoritmos')
        ->and($primeira->competencias_socioemocionais)->toBe('Organização')
        ->and($primeira->unidade_nome)->toBe('Introdução à lógica')
        ->and($primeira->unidade_codigo)->toBe('UC01')
        // Guardadas como vieram: "2/1" e "Não" não viram número nem booleano.
        ->and($primeira->carga_horaria)->toBe('2/1')
        ->and($primeira->aula_complementar)->toBe('Não')
        ->and($primeira->aulas_semanais)->toBe('4');
});

it('lê CSV do mesmo jeito que XLSX', function () {
    $caminho = ($this->arquivo)([linhaDoEscopo()], formato: 'csv');

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    expect(EscopoAula::query()->sole()->titulo_aula)->toBe('O que é um algoritmo');
});

/*
 * O arquivo é de fora: ninguém aqui controla se virá "BIMESTRE" ou
 * "Bimestre", nem em que ordem. Recusar por isso seria devolver à
 * escola um problema que o sistema sabe resolver.
 */
it('acha as colunas sem depender de ordem, caixa ou acento', function () {
    $embaralhado = ['TÍTULO DA AULA', 'semana', 'Bimestre', 'nome do componente', 'Codigo Componente'];

    $caminho = ($this->arquivo)([
        ['Funções', 3, 2, 'Lógica de Programação', 'LLP'],
    ], cabecalho: $embaralhado);

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    $aula = EscopoAula::query()->sole();

    expect($aula->titulo_aula)->toBe('Funções')
        ->and($aula->semana)->toBe(3)
        ->and($aula->bimestre)->toBe(2);
});

it('preserva a ordem das aulas dentro da semana', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(['Título da aula' => 'Primeira']),
        linhaDoEscopo(['Título da aula' => 'Segunda']),
        linhaDoEscopo(['Título da aula' => 'Terceira']),
    ]);

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    expect(EscopoAula::query()->orderBy('ordem')->pluck('titulo_aula')->all())
        ->toBe(['Primeira', 'Segunda', 'Terceira']);
});

it('recusa a planilha sem as colunas que dão lugar à aula', function () {
    $caminho = ($this->arquivo)([['Lógica', 'Só isso']], cabecalho: ['Nome do componente', 'Observação']);

    expect(fn () => $this->action->conferir($caminho, $this->curso, 1, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'não tem a(s) coluna(s)');
});

it('deixa de fora a linha sem bimestre válido, e diz qual', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Bimestre' => 'quando der', 'Título da aula' => 'Perdida']),
    ]);

    $relatorio = $this->action->conferir($caminho, $this->curso, 1, $this->paeet);

    expect($relatorio['aulas'])->toBe(1)
        ->and($relatorio['recusadas'])->toHaveCount(1)
        ->and($relatorio['recusadas'][0]['linha'])->toBe(3)
        ->and($relatorio['recusadas'][0]['erro'])->toContain('Bimestre inválido');

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    expect(EscopoAula::query()->pluck('titulo_aula')->all())->toBe(['O que é um algoritmo']);
});

it('ignora a linha em branco do fim da planilha', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        array_fill(0, count(CABECALHO_DO_ESCOPO), ''),
    ]);

    expect($this->action->conferir($caminho, $this->curso, 1, $this->paeet)['aulas'])->toBe(1);
});

it('recusa a planilha com cabeçalho e nenhuma aula', function () {
    $caminho = ($this->arquivo)([]);

    expect(fn () => $this->action->conferir($caminho, $this->curso, 1, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'nenhuma aula');
});

/*
|--------------------------------------------------------------------------
| A ligação com as disciplinas
|--------------------------------------------------------------------------
*/

it('liga a aula à disciplina quando o código do componente bate', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Nome do componente' => 'Outra coisa', 'Código componente' => 'ZZZ']),
    ]);

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    $aulas = EscopoAula::query()->orderBy('ordem')->get();

    expect($aulas[0]->disciplina_id)->toBe($this->disciplinas['Lógica de Programação']->id)
        // Sem correspondência não é erro: a aula entra assim mesmo.
        ->and($aulas[1]->disciplina_id)->toBeNull();
});

it('avisa na conferência quais componentes não casaram', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Nome do componente' => 'Eletiva', 'Código componente' => 'ELE']),
    ]);

    $relatorio = $this->action->conferir($caminho, $this->curso, 1, $this->paeet);

    expect($relatorio['sem_disciplina'])->toBe(['Eletiva (ELE)'])
        ->and(collect($relatorio['componentes'])->firstWhere('codigo', 'LLP')['disciplina'])
        ->toBe('Lógica de Programação');
});

/*
 * Sem código não há como casar, e casar no escuro é pior que não casar.
 * A disciplina de código vazio existe no cenário de propósito: é com
 * ela que uma chave "nada" colidiria, se a ausência virasse string
 * vazia em vez de um valor que nenhuma disciplina tem.
 */
it('não casa componente sem código com disciplina nenhuma', function () {
    $this->disciplinas['Redes']->update(['codigo' => ' ']);

    $caminho = ($this->arquivo)([linhaDoEscopo(['Código componente' => ''])]);

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    expect(EscopoAula::query()->sole()->disciplina_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Substituir o período
|--------------------------------------------------------------------------
*/

/*
 * O escopo é publicado por ano inteiro. Mesclar linha a linha deixaria
 * no banco uma mistura de duas versões que ninguém saberia desfazer.
 */
it('substitui o escopo do período ao importar de novo', function () {
    $this->action->executar(($this->arquivo)([
        linhaDoEscopo(['Título da aula' => 'Versão antiga']),
        linhaDoEscopo(['Título da aula' => 'Também antiga', 'Semana' => 2]),
    ]), $this->curso, 1, $this->paeet);

    $relatorio = $this->action->conferir(
        ($this->arquivo)([linhaDoEscopo(['Título da aula' => 'Versão nova'])]),
        $this->curso, 1, $this->paeet
    );

    expect($relatorio['substituira'])->toBe(2);

    $resultado = $this->action->executar(
        ($this->arquivo)([linhaDoEscopo(['Título da aula' => 'Versão nova'])]),
        $this->curso, 1, $this->paeet
    );

    expect($resultado['substituidas'])->toBe(2)
        ->and(EscopoAula::query()->pluck('titulo_aula')->all())->toBe(['Versão nova']);
});

it('não encosta no escopo do outro período', function () {
    $this->action->executar(($this->arquivo)([
        linhaDoEscopo(['Título da aula' => 'Do primeiro']),
    ]), $this->curso, 1, $this->paeet);

    $this->action->executar(($this->arquivo)([
        linhaDoEscopo(['Título da aula' => 'Do segundo']),
    ]), $this->curso, 2, $this->paeet);

    expect(EscopoAula::query()->where('periodo', 1)->pluck('titulo_aula')->all())->toBe(['Do primeiro'])
        ->and(EscopoAula::query()->where('periodo', 2)->pluck('titulo_aula')->all())->toBe(['Do segundo']);
});

it('recusa período além da duração do curso', function () {
    $caminho = ($this->arquivo)([linhaDoEscopo()]);

    expect(fn () => $this->action->conferir($caminho, $this->curso, 3, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'tem 2 período(s)');
});

it('registra a importação na auditoria', function () {
    $this->action->executar(($this->arquivo)([linhaDoEscopo()]), $this->curso, 1, $this->paeet);

    $registro = Activity::query()->where('log_name', 'escopo')->latest('id')->sole();

    expect($registro->description)->toContain('Escopo-sequência do 1º período importado')
        ->and($registro->properties['aulas'])->toBe(1)
        ->and($registro->causer_id)->toBe($this->paeet->id);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega a importação ao professor', function () {
    expect(fn () => $this->action->conferir(
        ($this->arquivo)([linhaDoEscopo()]), $this->curso, 1, $this->professor
    ))->toThrow(AuthorizationException::class);
});

it('nega a importação em curso de outro Eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->action->conferir(
        ($this->arquivo)([linhaDoEscopo()]), $this->curso, 1, $forasteiro
    ))->toThrow(AuthorizationException::class);
});

/*
|--------------------------------------------------------------------------
| A tela de importação
|--------------------------------------------------------------------------
*/

it('confere pela tela sem gravar nada', function () {
    $arquivo = UploadedFile::fake()->createWithContent(
        'escopo.xlsx',
        (string) file_get_contents(($this->arquivo)([linhaDoEscopo(), linhaDoEscopo(['Semana' => 2])]))
    );

    Livewire::actingAs($this->paeet)
        ->test(ImportarEscopo::class)
        ->set('curso_id', $this->curso->id)
        ->set('periodo', 1)
        ->set('planilha', $arquivo)
        ->call('conferir')
        ->assertHasNoErrors()
        ->assertSee('Lógica de Programação')
        ->assertSee('O que será gravado');

    expect(EscopoAula::query()->count())->toBe(0);
});

it('grava só no segundo passo e leva para a consulta', function () {
    $arquivo = UploadedFile::fake()->createWithContent(
        'escopo.xlsx',
        (string) file_get_contents(($this->arquivo)([linhaDoEscopo()]))
    );

    Livewire::actingAs($this->paeet)
        ->test(ImportarEscopo::class)
        ->set('curso_id', $this->curso->id)
        ->set('periodo', 1)
        ->set('planilha', $arquivo)
        ->call('conferir')
        ->call('confirmar')
        ->assertRedirect(route('escopo.index', ['curso' => $this->curso->id, 'periodo' => 1]));

    expect(EscopoAula::query()->count())->toBe(1);
});

it('recusa confirmar o que não foi conferido', function () {
    Livewire::actingAs($this->paeet)
        ->test(ImportarEscopo::class)
        ->set('curso_id', $this->curso->id)
        ->set('periodo', 1)
        ->call('confirmar')
        ->assertDispatched('notificar');

    expect(EscopoAula::query()->count())->toBe(0);
});

it('nega a tela de importação ao professor', function () {
    Livewire::actingAs($this->professor)
        ->test(ImportarEscopo::class)
        ->assertForbidden();
});

it('não oferece ao PAEET curso de outro Eixo', function () {
    $outro = Curso::factory()->noEixo(Eixo::factory()->create(['codigo' => 'ADM']))->create();

    $componente = Livewire::actingAs($this->paeet)->test(ImportarEscopo::class);

    expect(collect($componente->viewData('cursos'))->pluck('id')->all())
        ->toBe([$this->curso->id])
        ->and($outro->id)->not->toBe($this->curso->id);
});

/*
 * A tela lista as colunas esperadas para quem for conferir a planilha
 * antes de enviar. Mostrar a forma normalizada ("titulo da aula") seria
 * pedir que a pessoa procurasse no arquivo por algo que não está
 * escrito lá.
 */
it('mostra as colunas como elas vêm escritas no arquivo', function () {
    Livewire::actingAs($this->paeet)
        ->test(ImportarEscopo::class)
        ->assertSee('Título da aula')
        ->assertSee('CH teórica/prática')
        ->assertSee('Componente de 3 ou 4 aulas semanais?')
        // A grafia do arquivo, torta e tudo: é por ela que se procura.
        ->assertSee('Habildades socioemocionais');
});

it('nomeia a coluna que falta como ela aparece no arquivo', function () {
    $caminho = ($this->arquivo)([['Lógica']], cabecalho: ['Nome do componente']);

    expect(fn () => $this->action->conferir($caminho, $this->curso, 1, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'Título da aula');
});

/*
|--------------------------------------------------------------------------
| Colunas deslocadas na planilha
|--------------------------------------------------------------------------
|
| Num arquivo real, três colunas estavam rodadas uma casa em parte das
| linhas: "Nome do componente" trazia o texto da competência
| socioemocional, e o nome da matéria tinha ido parar na competência
| técnica. O sistema importou tudo sem reclamar, e o erro só apareceu na
| tela — um parágrafo de competência onde devia estar "Programação
| Mobile".
|
| O sinal que denuncia isso não é o tamanho do texto: é o código. Um
| código identifica um componente, e as 112 linhas do C1 dizem todas o
| mesmo nome. Quando o nome muda de linha para linha, o que mudou foi a
| coluna.
*/

it('recusa a planilha em que um código traz vários nomes', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Semana' => 2]),
        // Mesmo código, nome vindo da coluna errada.
        linhaDoEscopo([
            'Semana' => 3,
            'Nome do componente' => 'Curiosidade: Interessar-se por ideias novas e inovadoras.',
        ]),
    ]);

    expect(fn () => $this->action->conferir($caminho, $this->curso, 1, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'código com vários nomes');

    expect(EscopoAula::query()->count())->toBe(0);
});

it('diz qual código e mostra os nomes que encontrou', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Nome do componente' => 'Tolerância ao estresse: ter eficácia na modulação.']),
    ]);

    try {
        $this->action->conferir($caminho, $this->curso, 1, $this->paeet);
    } catch (RegraDeNegocioException $excecao) {
        expect($excecao->getMessage())->toContain('LLP (2 nomes')
            ->toContain('Lógica de Programação')
            ->toContain('Tolerância ao estresse')
            // Uma pista do que costuma ser, sem afirmar o que está certo.
            ->toContain('colunas costumam estar deslocadas');

        return;
    }

    $this->fail('A planilha com nomes trocados foi aceita.');
});

/*
 * Acento e caixa diferentes entre duas linhas é desencontro de
 * digitação, não troca de coluna — recusar o arquivo por isso seria
 * devolver à escola um problema que o sistema sabe absorver.
 */
it('não recusa por diferença de acento ou caixa no nome', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(['Nome do componente' => 'Lógica de Programação']),
        linhaDoEscopo(['Semana' => 2, 'Nome do componente' => 'LOGICA DE PROGRAMACAO']),
    ]);

    $relatorio = $this->action->conferir($caminho, $this->curso, 1, $this->paeet);

    expect($relatorio['aulas'])->toBe(2);
});

it('não confunde códigos diferentes com nomes diferentes', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(),
        linhaDoEscopo(['Nome do componente' => 'Redes', 'Código componente' => 'RED']),
    ]);

    expect($this->action->conferir($caminho, $this->curso, 1, $this->paeet)['aulas'])->toBe(2);
});

it('deixa passar a planilha sem código de componente nenhum', function () {
    $caminho = ($this->arquivo)([
        linhaDoEscopo(['Código componente' => '']),
        linhaDoEscopo(['Código componente' => '', 'Nome do componente' => 'Outro componente']),
    ]);

    // Sem código não há o que conferir: dois nomes são dois componentes.
    expect($this->action->conferir($caminho, $this->curso, 1, $this->paeet)['aulas'])->toBe(2);
});

it('aceita o escopo em ODS', function () {
    $caminho = ($this->arquivo)([linhaDoEscopo()], formato: 'ods');

    $this->action->executar($caminho, $this->curso, 1, $this->paeet);

    expect(EscopoAula::query()->sole()->titulo_aula)->toBe('O que é um algoritmo');
});

/*
 * Cortados no mesmo ponto, dois parágrafos de competência saem
 * idênticos na mensagem — e quem lê conclui que o sistema se enganou.
 */
it('escolhe exemplos que se distinguem depois do corte', function () {
    $comum = str_repeat('Texto longo que se repete no começo das duas. ', 3);

    $caminho = ($this->arquivo)([
        linhaDoEscopo(['Nome do componente' => $comum.'Primeira ponta']),
        linhaDoEscopo(['Semana' => 2, 'Nome do componente' => $comum.'Segunda ponta']),
        linhaDoEscopo(['Semana' => 3, 'Nome do componente' => 'Começa diferente de todas']),
    ]);

    try {
        $this->action->conferir($caminho, $this->curso, 1, $this->paeet);
    } catch (RegraDeNegocioException $excecao) {
        // O segundo exemplo é o que se distingue, não o que vem depois.
        expect($excecao->getMessage())->toContain('Começa diferente de todas')
            ->and(substr_count($excecao->getMessage(), 'Texto longo que se repete'))->toBe(1);

        return;
    }

    $this->fail('A planilha com nomes trocados foi aceita.');
});
