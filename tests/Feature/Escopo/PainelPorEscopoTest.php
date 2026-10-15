<?php

/*
 * O painel conta apenas o que é de quem olha.
 *
 * Cada número daqui é um atalho para uma tela, e um número maior que a
 * tela para onde ele aponta é pior que número nenhum: quem vê 12 alunos
 * e encontra 5 conclui que a tela está escondendo, não que o painel
 * estava somando o Eixo do colega.
 *
 * Por isso o cenário tem dados nos DOIS Eixos, e sempre em quantidades
 * diferentes — com 2 e 2, um vazamento passaria despercebido.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Enums\PerfilUsuario;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Enums\StatusQuestao;
use App\Livewire\Painel;
use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    $this->meuEixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia']);
    $this->outroEixo = Eixo::factory()->create(['codigo' => 'ADM', 'nome' => 'Gestão']);

    /**
     * Monta um Eixo inteiro: curso, disciplinas, turma, alunos,
     * professor e uma solicitação aberta.
     */
    $this->montarEixo = function (Eixo $eixo, int $alunos): array {
        $paeet = paeet($eixo);
        $professor = professor($eixo);

        $montagem = cursoComGrade($eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $paeet);

        $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
            ->create(['periodo' => 1, 'nome' => $eixo->codigo.' 1 A']);

        foreach (range(1, $alunos) as $n) {
            Aluno::factory()->naTurma($turma)->create(['ra' => $eixo->codigo.$n]);
        }

        $solicitacao = solicitacaoCom($paeet, $turma, [
            ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $professor, 'questoes' => 2],
        ], titulo: 'Bimestral do '.$eixo->codigo);

        return [
            'paeet' => $paeet,
            'professor' => $professor,
            'turma' => $turma,
            'solicitacao' => $solicitacao,
        ];
    };

    // Quantidades diferentes de propósito: 3 alunos aqui, 8 lá.
    $this->meu = ($this->montarEixo)($this->meuEixo, 3);
    $this->outro = ($this->montarEixo)($this->outroEixo, 8);

    // Os números de ação ficam em `indicadores`; a estrutura, que é
    // referência, desceu para `estrutura`.
    $this->indicadores = fn (User $usuario) => collect(
        Livewire::actingAs($usuario)->test(Painel::class)->viewData('indicadores')
    )->pluck('valor', 'rotulo');

    $this->estrutura = fn (User $usuario) => collect(
        Livewire::actingAs($usuario)->test(Painel::class)->viewData('estrutura')
    )->pluck('valor', 'rotulo');
});

/*
|--------------------------------------------------------------------------
| PAEET
|--------------------------------------------------------------------------
*/

it('conta ao PAEET só a estrutura do Eixo dele', function () {
    $valores = ($this->estrutura)($this->meu['paeet']);

    expect($valores['Cursos'])->toBe(1)
        ->and($valores['Disciplinas'])->toBe(2)
        ->and($valores['Turmas ativas'])->toBe(1)
        // Três aqui, oito no outro Eixo: o número denuncia o vazamento.
        ->and($valores['Alunos'])->toBe(3);
});

it('conta ao PAEET só as solicitações do Eixo dele', function () {
    $valores = ($this->indicadores)($this->meu['paeet']);

    expect($valores['Solicitações aguardando envio'])->toBe(1);
});

it('conta ao PAEET só as questões devolvidas do Eixo dele', function () {
    foreach ([$this->meu, $this->outro] as $lado) {
        $lado['solicitacao']->questoes()->get()
            ->each(fn (Questao $questao) => $questao->update(['status' => StatusQuestao::Rejeitada]));
    }

    $valores = ($this->indicadores)($this->meu['paeet']);

    // Duas de cada lado; só as daqui entram.
    expect($valores['Devolvidas aos professores'])->toBe(2);
});

/*
 * O número leva para a tela de usuários. Se as duas regras divergirem,
 * o painel diz um total e a tela mostra outro — e quem olha conclui que
 * a tela esconde contas.
 */
it('conta usuários pela mesma regra da tela para onde o número leva', function () {
    $paeet = $this->meu['paeet'];

    $noPainel = ($this->estrutura)($paeet)['Usuários'];
    $naTela = User::query()->visivelPara($paeet)->count();

    // O Eixo tem o PAEET da montagem e o professor, e mais ninguém.
    expect($noPainel)->toBe($naTela)
        ->and($noPainel)->toBe(2);
});

it('não mostra o cartão de Eixos a quem tem um só', function () {
    expect(($this->estrutura)($this->meu['paeet'])->keys())
        ->not->toContain('Eixos no meu escopo');

    $deDois = paeet($this->meuEixo);
    $deDois->eixos()->syncWithoutDetaching([$this->outroEixo->id]);

    expect(($this->estrutura)($deDois->fresh())->keys())
        ->toContain('Eixos no meu escopo');
});

it('diz na tela qual é o Eixo de quem está olhando', function () {
    // O subtítulo mora no layout, que só existe na resposta inteira.
    $this->actingAs($this->meu['paeet'])
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Eixo: Tecnologia')
        ->assertSee('contam apenas o que pertence')
        ->assertSee('ao seu Eixo');

    // Com dois Eixos o rótulo vai para o plural.
    $dosDois = paeet($this->meuEixo);
    $dosDois->eixos()->syncWithoutDetaching([$this->outroEixo->id]);

    $this->actingAs($dosDois->fresh())
        ->get(route('painel'))
        ->assertSee('Eixos: Gestão, Tecnologia')
        ->assertSee('aos seus Eixos');
});

/*
|--------------------------------------------------------------------------
| PAEET de dois Eixos
|--------------------------------------------------------------------------
*/

it('soma os dois Eixos de quem responde pelos dois', function () {
    $dosDois = paeet($this->meuEixo);
    $dosDois->eixos()->syncWithoutDetaching([$this->outroEixo->id]);

    $valores = ($this->estrutura)($dosDois->fresh());

    expect($valores['Cursos'])->toBe(2)
        ->and($valores['Alunos'])->toBe(11)
        ->and($valores['Eixos no meu escopo'])->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Professor
|--------------------------------------------------------------------------
*/

it('conta ao professor só o que foi pedido a ele', function () {
    $valores = ($this->indicadores)($this->meu['professor']);

    expect($valores['A responder'])->toBe(1)
        ->and($valores['Disciplinas que leciono'])->toBe(1);
});

it('não mostra ao professor a estrutura do Eixo', function () {
    $chaves = ($this->indicadores)($this->meu['professor'])->keys();

    expect($chaves)->not->toContain('Cursos')
        ->not->toContain('Alunos')
        ->not->toContain('Usuários')
        ->not->toContain('Turmas ativas')
        // Nem no bloco de estrutura, que é da gestão.
        ->and(($this->estrutura)($this->meu['professor'])->all())->toBe([]);
});

it('conta ao professor só as questões devolvidas a ele', function () {
    foreach ([$this->meu, $this->outro] as $lado) {
        $lado['solicitacao']->questoes()->get()
            ->each(fn (Questao $questao) => $questao->update(['status' => StatusQuestao::Rejeitada]));
    }

    expect(($this->indicadores)($this->meu['professor'])['Questões devolvidas'])->toBe(2)
        ->and(($this->indicadores)($this->outro['professor'])['Questões devolvidas'])->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Sem Eixo
|--------------------------------------------------------------------------
*/

/*
 * Conta de gestão sem Eixo vinculado não vê nada — e precisa saber que é
 * por isso, senão o painel zerado parece sistema vazio.
 */
it('mostra zero e explica para quem não tem Eixo vinculado', function () {
    $semEixo = User::factory()->create([
        'perfil' => PerfilUsuario::Paeet,
        'ativo' => true,
    ]);

    $estrutura = ($this->estrutura)($semEixo);

    expect($estrutura['Cursos'])->toBe(0)
        ->and($estrutura['Alunos'])->toBe(0)
        ->and(($this->indicadores)($semEixo)['Solicitações aguardando envio'])->toBe(0);

    Livewire::actingAs($semEixo)
        ->test(Painel::class)
        ->assertSee('Sua conta ainda não tem Eixo vinculado');
});

it('não conta nada para conta desativada', function () {
    $paeet = $this->meu['paeet'];
    $paeet->update(['ativo' => false]);

    $valores = ($this->estrutura)($paeet->fresh()->esquecerEscopo());

    expect($valores['Cursos'])->toBe(0)
        ->and($valores['Alunos'])->toBe(0)
        ->and($valores['Turmas ativas'])->toBe(0)
        // Sem a atribuição, o item de usuários nem aparece.
        ->and($valores->keys())->not->toContain('Usuários');
});

/*
|--------------------------------------------------------------------------
| O que precisa de alguém
|--------------------------------------------------------------------------
|
| O número diz quantos; a lista diz quais. É a diferença entre o painel
| ser um lugar por onde se passa e o lugar onde se começa — e, como todo
| o resto da tela, ela é recortada por quem olha.
*/

/** As pendências que o painel lista para este usuário. */
function pendenciasDe(User $usuario): Collection
{
    return collect(Livewire::actingAs($usuario)->test(Painel::class)->viewData('pendencias'));
}

it('lista para a gestão as solicitações que esperam alguém', function () {
    $itens = pendenciasDe($this->meu['paeet']);

    expect($itens)->toHaveCount(1)
        ->and($itens->first()['titulo'])->toBe('Bimestral do TEC')
        ->and($itens->first()['turma'])->toContain('TEC 1 A')
        // Uma parte pendente nesta solicitação.
        ->and($itens->first()['faltam'])->toBe(1)
        ->and($itens->first()['href'])->toContain('/solicitacoes/'.$this->meu['solicitacao']->id);
});

it('não lista à gestão a pendência do outro Eixo', function () {
    expect(pendenciasDe($this->meu['paeet'])->pluck('titulo')->all())
        ->toBe(['Bimestral do TEC']);

    expect(pendenciasDe($this->outro['paeet'])->pluck('titulo')->all())
        ->toBe(['Bimestral do ADM']);
});

it('põe a atrasada na frente e ordena pelo prazo', function () {
    $paeet = $this->meu['paeet'];

    // Mais duas solicitações na mesma turma, com prazos diferentes.
    $vencida = $this->meu['solicitacao'];
    $vencida->update(['prazo' => now()->subWeek(), 'titulo' => 'Vencida']);

    /*
     * A distante é criada ANTES da de amanhã: assim a ordem de id é
     * diferente da ordem de prazo, e ordenar errado aparece.
     */
    $distante = solicitacaoCom($paeet, $this->meu['turma'], [
        ['disciplina' => Disciplina::query()->where('nome', 'Lógica')->where('curso_id', $this->meu['turma']->curso_id)->sole(),
            'professor' => $this->meu['professor'], 'questoes' => 1],
    ], prazo: now()->addMonth(), titulo: 'Mês que vem');

    $logo = solicitacaoCom($paeet, $this->meu['turma'], [
        ['disciplina' => Disciplina::query()->where('nome', 'Redes')->where('curso_id', $this->meu['turma']->curso_id)->sole(),
            'professor' => $this->meu['professor'], 'questoes' => 1],
    ], prazo: now()->addDay(), titulo: 'Amanhã');

    $itens = pendenciasDe($paeet);

    expect($itens->pluck('titulo')->all())->toBe(['Vencida', 'Amanhã', 'Mês que vem'])
        ->and($itens->first()['atrasada'])->toBeTrue()
        ->and($itens->last()['atrasada'])->toBeFalse()
        // A de id menor é a mais distante: ordenar por id daria outra lista.
        ->and($distante->id)->toBeLessThan($logo->id);
});

it('mostra no máximo cinco, com atalho para a lista inteira', function () {
    $paeet = $this->meu['paeet'];
    $disciplina = Disciplina::query()->where('nome', 'Redes')
        ->where('curso_id', $this->meu['turma']->curso_id)->sole();

    foreach (range(1, 6) as $n) {
        solicitacaoCom($paeet, $this->meu['turma'], [
            ['disciplina' => $disciplina, 'professor' => $this->meu['professor'], 'questoes' => 1],
        ], prazo: now()->addDays($n), titulo: 'Extra '.$n);
    }

    expect(pendenciasDe($paeet))->toHaveCount(5);

    Livewire::actingAs($paeet)->test(Painel::class)->assertSee('Ver todas');
});

/*
 * A parte do colega não é pendência de quem já entregou a sua — e é por
 * disciplina que o professor reconhece o que falta, não pelo título da
 * prova.
 */
it('lista ao professor as disciplinas que ele ainda não enviou', function () {
    $itens = pendenciasDe($this->meu['professor']);

    expect($itens)->toHaveCount(1)
        ->and($itens->first()['titulo'])->toBe('Lógica')
        ->and($itens->first()['faltam'])->toBe(2)
        ->and($itens->first()['href'])->toContain('/responder');
});

it('tira da lista do professor a parte que ele já enviou', function () {
    enviarParte($this->meu['solicitacao']->partes()->sole(), $this->meu['professor']);

    expect(pendenciasDe($this->meu['professor']))->toBeEmpty();
});

/*
 * Os dois na MESMA solicitação: com professores separados, o filtro
 * por `professor_id` some sem ninguém notar — o escopo da solicitação
 * já esconderia a do colega.
 */
it('na solicitação de duas disciplinas, cada professor vê só a dele', function () {
    $colega = professor($this->meuEixo);

    $compartilhada = solicitacaoCom($this->meu['paeet'], $this->meu['turma'], [
        ['disciplina' => Disciplina::query()->where('nome', 'Lógica')
            ->where('curso_id', $this->meu['turma']->curso_id)->sole(),
            'professor' => $this->meu['professor'], 'questoes' => 1],
        ['disciplina' => Disciplina::query()->where('nome', 'Redes')
            ->where('curso_id', $this->meu['turma']->curso_id)->sole(),
            'professor' => $colega, 'questoes' => 1],
    ], titulo: 'A quatro mãos');

    expect(pendenciasDe($colega)->pluck('titulo')->all())->toBe(['Redes'])
        ->and(pendenciasDe($this->meu['professor'])->pluck('titulo')->sort()->values()->all())
        ->toBe(['Lógica', 'Lógica']);

    expect($compartilhada->partes()->count())->toBe(2);
});

it('não lista nada ao professor sem solicitação nenhuma', function () {
    expect(pendenciasDe(professor($this->meuEixo)))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| As pontas do ciclo que ninguém via
|--------------------------------------------------------------------------
*/

it('conta as provas geradas ou aplicadas sem resultado importado', function () {
    $paeet = $this->meu['paeet'];

    expect(($this->indicadores)($paeet)['Provas sem resultado'])->toBe(0);

    $prova = provaDaTurma($this->meu, $paeet);

    expect(($this->indicadores)($paeet)['Provas sem resultado'])->toBe(1)
        // E é do Eixo de quem olha, não do vizinho.
        ->and(($this->indicadores)($this->outro['paeet'])['Provas sem resultado'])->toBe(0);

    // Rascunho não conta: ainda não existe folha para importar.
    $prova->update(['status' => StatusProva::Rascunho]);

    expect(($this->indicadores)($paeet)['Provas sem resultado'])->toBe(0);
});

it('conta as importações conferidas e não confirmadas', function () {
    $paeet = $this->meu['paeet'];

    expect(($this->indicadores)($paeet)['Importações a confirmar'])->toBe(0);

    $prova = provaDaTurma($this->meu, $paeet);

    $importacao = Importacao::create([
        'prova_id' => $prova->id, 'usuario_id' => $paeet->id,
        'arquivo' => 'importacoes/x.xlsx', 'hash' => hash('sha256', 'a'),
        'status' => StatusImportacao::Validada,
    ]);

    expect(($this->indicadores)($paeet)['Importações a confirmar'])->toBe(1)
        ->and(($this->indicadores)($this->outro['paeet'])['Importações a confirmar'])->toBe(0);

    // Confirmada sai da conta: não espera mais ninguém.
    $importacao->update(['status' => StatusImportacao::Confirmada, 'confirmada_em' => now()]);

    expect(($this->indicadores)($paeet)['Importações a confirmar'])->toBe(0);
});

/** Monta uma prova aplicada na turma do Eixo dado. */
function provaDaTurma(array $eixo, User $paeet): Prova
{
    foreach ($eixo['solicitacao']->partes()->get() as $parte) {
        enviarParte($parte, $eixo['professor']);
    }

    foreach ($eixo['solicitacao']->questoes()->get() as $questao) {
        app(AnalisarQuestaoAction::class)->aprovar($questao, $paeet);
    }

    $prova = app(MontarProvaAction::class)->executar(
        autor: $paeet,
        turma: $eixo['turma'],
        modelo: ModeloProva::factory()->create(['eixo_id' => $eixo['turma']->curso->eixo_id]),
        titulo: 'Prova sem resultado',
    );

    $prova->update(['status' => StatusProva::Aplicada]);

    return $prova->refresh();
}

it('cumprimenta pelo nome, e não pelo tratamento', function () {
    $this->meu['professor']->update(['nome' => 'Prof. Renato Lima']);

    Livewire::actingAs($this->meu['professor']->fresh())
        ->test(Painel::class)
        ->assertSee('Bem-vindo, Renato')
        ->assertDontSee('Bem-vindo, Prof.');
});
