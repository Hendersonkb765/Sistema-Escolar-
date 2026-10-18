<?php

/*
 * A consulta ao escopo-sequência.
 *
 * Serve a dois usos. O professor chega para saber o que dar na semana
 * que vem, e abre já nos componentes dele. A coordenação chega para ver
 * o percurso inteiro. Os dois enxergam o mesmo documento — o que muda é
 * por onde a tela começa.
 */

use App\Livewire\Escopo\VisaoDoEscopo;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\EscopoAula;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia']);
    $this->paeet = paeet($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->redes = $montagem['disciplinas']['Redes'];

    // O professor leciona Lógica; Redes é do colega.
    $this->professor = professor($this->eixo);
    $this->professor->vinculosDocentes()->create([
        'disciplina_id' => $this->logica->id,
        'ativo' => true,
    ]);
    $this->professor->esquecerEscopo();

    /** Uma aula no escopo deste curso. */
    $this->aula = function (array $campos = []) {
        static $ordem = 0;

        return EscopoAula::query()->create([
            'curso_id' => $this->curso->id,
            'periodo' => 1,
            'disciplina_id' => $this->logica->id,
            'bimestre' => 1,
            'semana' => 1,
            'ordem' => ++$ordem,
            'componente_nome' => 'Lógica de Programação',
            'componente_codigo' => 'LLP',
            'titulo_aula' => 'Algoritmos',
            ...$campos,
        ]);
    };
});

/*
|--------------------------------------------------------------------------
| A leitura por semana
|--------------------------------------------------------------------------
*/

it('agrupa as aulas por bimestre e por semana', function () {
    ($this->aula)(['bimestre' => 1, 'semana' => 1, 'titulo_aula' => 'Algoritmos']);
    ($this->aula)(['bimestre' => 1, 'semana' => 1, 'titulo_aula' => 'Variáveis']);
    ($this->aula)(['bimestre' => 1, 'semana' => 2, 'titulo_aula' => 'Condicionais']);
    ($this->aula)(['bimestre' => 2, 'semana' => 9, 'titulo_aula' => 'Repetição']);

    $grupos = Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->viewData('porSemana');

    expect($grupos)->toHaveCount(2)
        ->and($grupos[0]['bimestre'])->toBe(1)
        ->and($grupos[0]['semanas'])->toHaveCount(2)
        ->and($grupos[0]['semanas'][0]['aulas'])->toHaveCount(2)
        ->and($grupos[1]['bimestre'])->toBe(2)
        ->and($grupos[1]['semanas'][0]['semana'])->toBe(9);
});

it('mostra o tema e os componentes da semana sem precisar abri-la', function () {
    ($this->aula)(['tema_semana' => 'Primeiros algoritmos']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'componente_codigo' => 'RED',
        'disciplina_id' => $this->redes->id, 'titulo_aula' => 'Cabeamento',
    ]);

    $semana = Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->viewData('porSemana')[0]['semanas'][0];

    expect($semana['tema'])->toBe('Primeiros algoritmos')
        ->and($semana['componentes'])->toBe(['Lógica de Programação (LLP)', 'Redes (RED)']);
});

it('abre e fecha a semana', function () {
    ($this->aula)();

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertDontSee('Objeto de conhecimento')
        ->call('abrirSemana', '1-1')
        ->assertSet('semanaAberta', '1-1')
        ->call('abrirSemana', '1-1')
        ->assertSet('semanaAberta', null);
});

it('mostra na aula aberta só os campos que vieram preenchidos', function () {
    ($this->aula)([
        'objetivos' => 'Reconhecer sequências',
        'objeto_conhecimento' => null,
    ]);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->call('abrirSemana', '1-1')
        ->assertSee('Objetivos da aula')
        ->assertSee('Reconhecer sequências')
        // Rótulo sem nada embaixo faz parecer que o sistema perdeu o conteúdo.
        ->assertDontSee('Objeto de conhecimento');
});

/*
|--------------------------------------------------------------------------
| Os filtros
|--------------------------------------------------------------------------
*/

it('filtra por bimestre, componente e período', function () {
    ($this->aula)(['bimestre' => 1, 'titulo_aula' => 'Do primeiro bimestre']);
    ($this->aula)(['bimestre' => 3, 'titulo_aula' => 'Do terceiro bimestre']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'disciplina_id' => $this->redes->id,
        'titulo_aula' => 'De Redes',
    ]);
    ($this->aula)(['periodo' => 2, 'titulo_aula' => 'Do segundo período']);

    $tela = fn (array $filtros) => collect(
        Livewire::actingAs($this->paeet)
            ->test(VisaoDoEscopo::class)
            ->set('filtroCurso', (string) $this->curso->id)
            ->set($filtros)
            ->viewData('porSemana')
    )->flatMap(fn (array $b) => $b['semanas'])
        ->flatMap(fn (array $s) => $s['aulas']->pluck('titulo_aula'))
        ->all();

    expect($tela(['filtroBimestre' => '3']))->toBe(['Do terceiro bimestre'])
        ->and($tela(['filtroComponente' => 'Redes']))->toBe(['De Redes'])
        ->and($tela(['filtroPeriodo' => '2']))->toBe(['Do segundo período']);
});

it('busca no título, no tema e no objeto de conhecimento', function () {
    ($this->aula)(['titulo_aula' => 'Funções em PHP']);
    ($this->aula)(['titulo_aula' => 'Vetores', 'tema_semana' => 'Estruturas de dados']);
    ($this->aula)(['titulo_aula' => 'Laços', 'objeto_conhecimento' => 'Repetição condicional']);
    ($this->aula)(['titulo_aula' => 'Cabeamento']);

    $busca = fn (string $termo) => collect(
        Livewire::actingAs($this->paeet)
            ->test(VisaoDoEscopo::class)
            ->set('filtroCurso', (string) $this->curso->id)
            ->set('busca', $termo)
            ->viewData('porSemana')
    )->flatMap(fn (array $b) => $b['semanas'])
        ->flatMap(fn (array $s) => $s['aulas']->pluck('titulo_aula'))
        ->all();

    expect($busca('Funções'))->toBe(['Funções em PHP'])
        ->and($busca('Estruturas'))->toBe(['Vetores'])
        ->and($busca('Repetição'))->toBe(['Laços'])
        ->and($busca('nada disso'))->toBe([]);
});

it('troca de curso limpa os filtros do curso anterior', function () {
    ($this->aula)(['bimestre' => 2]);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->set('filtroBimestre', '2')
        ->set('filtroComponente', 'Lógica de Programação')
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertSet('filtroBimestre', '')
        ->assertSet('filtroComponente', '');
});

/*
|--------------------------------------------------------------------------
| O professor
|--------------------------------------------------------------------------
*/

/*
 * Ele veio ver a aula dele. O escopo de um curso tem seis componentes e
 * mais de quinhentas aulas, e quem dá Programação Mobile não veio ler o
 * percurso de banco de dados.
 */
it('mostra ao professor só as matérias que ele leciona', function () {
    ($this->aula)(['titulo_aula' => 'Minha aula']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'disciplina_id' => $this->redes->id,
        'titulo_aula' => 'Aula do colega',
    ]);

    $titulos = collect(
        Livewire::actingAs($this->professor)
            ->test(VisaoDoEscopo::class)
            ->set('filtroCurso', (string) $this->curso->id)
            ->viewData('porSemana')
    )->flatMap(fn (array $b) => $b['semanas'])
        ->flatMap(fn (array $s) => $s['aulas']->pluck('titulo_aula'))
        ->all();

    expect($titulos)->toBe(['Minha aula']);
});

/*
 * Não é preferência que se desliga: é o recorte do model, e por isso a
 * caixa nem aparece para ele.
 */
it('não oferece ao professor a opção de ver o curso inteiro', function () {
    ($this->aula)();

    Livewire::actingAs($this->professor)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertDontSee('Só os componentes que eu leciono');

    // Nem forçando pelo endereço: quem filtra é a consulta, não a caixa.
    $titulos = collect(
        Livewire::actingAs($this->professor)
            ->test(VisaoDoEscopo::class)
            ->set('filtroCurso', (string) $this->curso->id)
            ->set('soOsMeus', false)
            ->viewData('porSemana')
    )->flatMap(fn (array $b) => $b['semanas'])
        ->flatMap(fn (array $s) => $s['aulas']->pluck('titulo_aula'))
        ->all();

    expect($titulos)->toBe(['Algoritmos']);
});

/*
 * A aula só chega ao professor quando o componente da planilha casou
 * com uma disciplina. Sem isso ele abre a tela vazia, e precisa saber
 * que o conserto é da coordenação.
 */
it('não mostra ao professor a aula cujo componente não casou', function () {
    ($this->aula)(['titulo_aula' => 'Minha aula']);
    ($this->aula)(['disciplina_id' => null, 'titulo_aula' => 'Componente órfão']);

    // Direto no escopo do model: a tela tem outros caminhos para ficar
    // vazia, e é este recorte que precisa valer sozinho.
    expect(EscopoAula::query()->visivelPara($this->professor)->pluck('titulo_aula')->all())
        ->toBe(['Minha aula'])
        // A gestão vê as duas, inclusive a órfã.
        ->and(EscopoAula::query()->visivelPara($this->paeet)->count())->toBe(2);
});

it('explica ao professor sem escopo nenhum por que a tela está vazia', function () {
    ($this->aula)(['disciplina_id' => null, 'titulo_aula' => 'Componente órfão']);

    Livewire::actingAs($this->professor)
        ->test(VisaoDoEscopo::class)
        ->assertSee('Nenhum escopo-sequência para você aqui')
        ->assertSee('não casou com a disciplina do cadastro');
});

it('abre a gestão com o percurso inteiro', function () {
    ($this->aula)(['titulo_aula' => 'Minha aula']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'disciplina_id' => $this->redes->id,
        'titulo_aula' => 'Aula do colega',
    ]);

    expect(Livewire::actingAs($this->paeet)->test(VisaoDoEscopo::class)->get('soOsMeus'))
        ->toBeFalse();
});

it('não oferece o botão de importar ao professor', function () {
    ($this->aula)();

    Livewire::actingAs($this->professor)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertDontSee('Importar escopo');

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertSee('Importar escopo');
});

/*
|--------------------------------------------------------------------------
| Escopo
|--------------------------------------------------------------------------
*/

it('não mostra escopo de curso de outro Eixo', function () {
    ($this->aula)(['titulo_aula' => 'Do meu Eixo']);

    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $outroCurso = Curso::factory()->noEixo($outroEixo)->create(['nome' => 'Administração']);

    EscopoAula::query()->create([
        'curso_id' => $outroCurso->id, 'periodo' => 1, 'bimestre' => 1, 'semana' => 1, 'ordem' => 1,
        'componente_nome' => 'Contabilidade', 'titulo_aula' => 'Do outro Eixo',
    ]);

    $componente = Livewire::actingAs($this->paeet)->test(VisaoDoEscopo::class);

    expect(collect($componente->viewData('cursos'))->pluck('id')->all())
        ->toBe([$this->curso->id]);

    // Nem por id no endereço.
    $titulos = collect(
        Livewire::actingAs($this->paeet)
            ->test(VisaoDoEscopo::class)
            ->set('filtroCurso', (string) $outroCurso->id)
            ->viewData('porSemana')
    )->flatMap(fn (array $b) => $b['semanas'])->all();

    expect($titulos)->toBe([]);
});

it('não mostra ao professor o escopo de curso em que ele não leciona', function () {
    ($this->aula)();

    $deFora = professor($this->eixo);

    expect(collect(
        Livewire::actingAs($deFora)->test(VisaoDoEscopo::class)->viewData('cursos')
    )->all())->toBe([]);
});

/*
 * Direto no escopo do model: a tela filtra o select pelo escopo de
 * Curso, que já barra o professor sem vínculo nenhum. O recorte das
 * AULAS é outro, e precisa valer sozinho — senão um curso que entrasse
 * pelo select traria o percurso inteiro junto.
 */
it('recorta as aulas do professor pelos cursos em que ele leciona', function () {
    ($this->aula)(['titulo_aula' => 'Do meu curso']);

    $outroCurso = Curso::factory()->noEixo($this->eixo)->create(['nome' => 'Curso vizinho']);

    EscopoAula::query()->create([
        'curso_id' => $outroCurso->id, 'periodo' => 1, 'bimestre' => 1, 'semana' => 1, 'ordem' => 1,
        'componente_nome' => 'Outra coisa', 'titulo_aula' => 'Do curso vizinho',
    ]);

    expect(EscopoAula::query()->visivelPara($this->professor)->pluck('titulo_aula')->all())
        ->toBe(['Do meu curso'])
        // A gestão do Eixo vê os dois.
        ->and(EscopoAula::query()->visivelPara($this->paeet)->pluck('titulo_aula')->sort()->values()->all())
        ->toBe(['Do curso vizinho', 'Do meu curso']);
});

it('nega a tela a quem não está ativo', function () {
    $this->paeet->update(['ativo' => false]);

    Livewire::actingAs($this->paeet->fresh())
        ->test(VisaoDoEscopo::class)
        ->assertForbidden();
});

/*
 * Abrir pedindo que a pessoa escolha a única opção existente é trabalho
 * que o sistema pode poupar.
 */
it('escolhe sozinho o curso quando só há um com escopo', function () {
    ($this->aula)();

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->assertSet('filtroCurso', (string) $this->curso->id);
});

it('não escolhe sozinho quando há mais de um curso com escopo', function () {
    ($this->aula)();

    $segundo = Curso::factory()->noEixo($this->eixo)->create(['nome' => 'Outro curso']);

    EscopoAula::query()->create([
        'curso_id' => $segundo->id, 'periodo' => 1, 'bimestre' => 1, 'semana' => 1, 'ordem' => 1,
        'componente_nome' => 'Qualquer', 'titulo_aula' => 'Qualquer aula',
    ]);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->assertSet('filtroCurso', '')
        ->assertSee('Escolha um curso');
});

it('diz que o curso ainda não tem escopo, em vez de uma tela vazia', function () {
    // Outro curso já tem escopo: o caso aqui é o curso escolhido não ter,
    // e não a escola inteira estar sem nada importado.
    ($this->aula)();

    $semEscopo = Curso::factory()->noEixo($this->eixo)->create(['nome' => 'Curso novo']);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $semEscopo->id)
        ->assertSee('ainda não teve o escopo-sequência importado');
});

/*
 * O estado do componente não chega ao HTML sozinho: sem `@selected`, um
 * link com filtro no endereço abre a tela filtrada e o select dizendo
 * "Escolha um curso". O controle mentindo sobre o que a página mostra é
 * o mesmo defeito que a caixa de disciplina tinha em Resultados.
 */
it('marca no select o filtro que veio do endereço', function () {
    ($this->aula)(['bimestre' => 2, 'semana' => 11]);

    $html = Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->set('filtroBimestre', '2')
        ->html();

    expect($html)->toContain('value="'.$this->curso->id.'" selected')
        ->and($html)->toContain('value="2" selected');
});

/*
|--------------------------------------------------------------------------
| Antes do primeiro arquivo
|--------------------------------------------------------------------------
|
| É o estado em que a tela nasce — nenhum escopo importado —, e ele não
| pode mandar escolher de uma lista vazia.
*/

it('convida a coordenação a importar quando não há escopo nenhum', function () {
    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->assertSee('Nenhum escopo-sequência para você aqui')
        ->assertSee('Importar escopo')
        ->assertDontSee('Escolha acima para vê-lo');
});

it('explica ao professor os dois motivos de a tela estar vazia', function () {
    Livewire::actingAs($this->professor)
        ->test(VisaoDoEscopo::class)
        ->assertSee('Nenhum escopo-sequência para você aqui')
        ->assertSee('ainda não foi importado')
        ->assertSee('não casou com a disciplina do cadastro')
        // Importar não é dele, e o botão não aparece.
        ->assertDontSee('>Importar escopo<');
});

/*
|--------------------------------------------------------------------------
| O tema é de cada componente
|--------------------------------------------------------------------------
|
| "Tema da semana" é o tema da semana DAQUELE componente. No escopo real
| a semana 1 tem seis — um por componente. Eleger o primeiro como tema
| da semana põe "Introdução a Aplicações Móveis" em cima de uma semana
| que também é de banco de dados e inteligência artificial.
*/

it('não elege o tema de um componente como tema da semana', function () {
    ($this->aula)(['tema_semana' => 'Introdução a aplicações móveis']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'componente_codigo' => 'RED',
        'disciplina_id' => $this->redes->id, 'titulo_aula' => 'Cabeamento',
        'tema_semana' => 'Fundamentos de redes',
    ]);

    $semana = Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->viewData('porSemana')[0]['semanas'][0];

    expect($semana['tema'])->toBeNull()
        ->and($semana['temasPorComponente'])->toBe([
            'Lógica de Programação (LLP)' => 'Introdução a aplicações móveis',
            'Redes (RED)' => 'Fundamentos de redes',
        ]);
});

it('mostra o tema na linha da semana quando só há um', function () {
    ($this->aula)(['tema_semana' => 'Primeiros algoritmos']);
    ($this->aula)(['tema_semana' => 'Primeiros algoritmos', 'titulo_aula' => 'Variáveis']);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertSee('Primeiros algoritmos')
        ->assertDontSee('temas, um por componente');
});

it('avisa quantos temas a semana tem, e põe cada um na sua aula', function () {
    ($this->aula)(['tema_semana' => 'Introdução a aplicações móveis']);
    ($this->aula)([
        'componente_nome' => 'Redes', 'disciplina_id' => $this->redes->id,
        'titulo_aula' => 'Cabeamento', 'tema_semana' => 'Fundamentos de redes',
    ]);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->assertSee('2 temas, um por componente')
        // Fechada, a linha não escolhe nenhum dos dois.
        ->assertDontSee('Fundamentos de redes')
        ->call('abrirSemana', '1-1')
        // Aberta, cada aula traz o seu.
        ->assertSee('Fundamentos de redes')
        ->assertSee('Introdução a aplicações móveis');
});

/*
|--------------------------------------------------------------------------
| Teoria ou prática
|--------------------------------------------------------------------------
|
| A coluna "CH teórica/prática" traz só a inicial — "T" ou "P", e um
| "p" minúsculo em três linhas do arquivo real. Na tela isso saía como
| "CH T", que não se lê: a sigla é do arquivo, não de quem dá a aula.
*/

it('escreve teoria e prática por extenso', function () {
    ($this->aula)(['carga_horaria' => 'T', 'titulo_aula' => 'Conceitos']);
    ($this->aula)(['carga_horaria' => 'P', 'titulo_aula' => 'Laboratório']);

    Livewire::actingAs($this->paeet)
        ->test(VisaoDoEscopo::class)
        ->set('filtroCurso', (string) $this->curso->id)
        ->call('abrirSemana', '1-1')
        ->assertSee('Teoria')
        ->assertSee('Prática')
        ->assertDontSee('CH T');
});

it('entende a inicial em minúscula, como vem no arquivo', function () {
    expect((new EscopoAula(['carga_horaria' => 'p']))->teoriaOuPratica())->toBe('Prática')
        ->and((new EscopoAula(['carga_horaria' => ' t ']))->teoriaOuPratica())->toBe('Teoria');
});

/*
 * A coluna é de fora: inventar tradução para o que não se conhece é
 * como o texto do arquivo vira um rótulo errado que parece certo.
 */
it('devolve como veio o valor que não é T nem P', function () {
    expect((new EscopoAula(['carga_horaria' => '2/1']))->teoriaOuPratica())->toBe('2/1')
        ->and((new EscopoAula(['carga_horaria' => '']))->teoriaOuPratica())->toBeNull()
        ->and((new EscopoAula(['carga_horaria' => null]))->teoriaOuPratica())->toBeNull();
});
