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

use App\Enums\PerfilUsuario;
use App\Enums\StatusQuestao;
use App\Livewire\Painel;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use App\Models\User;
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

    $this->indicadores = fn (User $usuario) => collect(
        Livewire::actingAs($usuario)->test(Painel::class)->viewData('indicadores')
    )->pluck('valor', 'rotulo');
});

/*
|--------------------------------------------------------------------------
| PAEET
|--------------------------------------------------------------------------
*/

it('conta ao PAEET só a estrutura do Eixo dele', function () {
    $valores = ($this->indicadores)($this->meu['paeet']);

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

    $noPainel = ($this->indicadores)($paeet)['Usuários'];
    $naTela = User::query()->visivelPara($paeet)->count();

    // O Eixo tem o PAEET da montagem e o professor, e mais ninguém.
    expect($noPainel)->toBe($naTela)
        ->and($noPainel)->toBe(2);
});

it('não mostra o cartão de Eixos a quem tem um só', function () {
    expect(($this->indicadores)($this->meu['paeet'])->keys())
        ->not->toContain('Eixos no meu escopo');

    $deDois = paeet($this->meuEixo);
    $deDois->eixos()->syncWithoutDetaching([$this->outroEixo->id]);

    expect(($this->indicadores)($deDois->fresh())->keys())
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

    $valores = ($this->indicadores)($dosDois->fresh());

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
        ->not->toContain('Turmas ativas');
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

    $valores = ($this->indicadores)($semEixo);

    expect($valores['Cursos'])->toBe(0)
        ->and($valores['Alunos'])->toBe(0)
        ->and($valores['Solicitações aguardando envio'])->toBe(0);

    Livewire::actingAs($semEixo)
        ->test(Painel::class)
        ->assertSee('Sua conta ainda não tem Eixo vinculado');
});

it('não conta nada para conta desativada', function () {
    $paeet = $this->meu['paeet'];
    $paeet->update(['ativo' => false]);

    $valores = ($this->indicadores)($paeet->fresh()->esquecerEscopo());

    expect($valores['Cursos'])->toBe(0)
        ->and($valores['Alunos'])->toBe(0)
        ->and($valores['Turmas ativas'])->toBe(0)
        // Sem a atribuição, o cartão de usuários nem aparece.
        ->and($valores->keys())->not->toContain('Usuários');
});
