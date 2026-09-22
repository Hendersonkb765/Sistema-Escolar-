<?php

/*
 * Regressão: a tela de resposta precisa realmente renderizar os campos.
 *
 * `assertSee` de rótulos não pega uma tag de componente que ficou literal
 * no HTML — o Blade não compila `<x-input>` quando há uma diretiva
 * `@disabled(...)` dentro da tag, e o campo simplesmente não existe.
 * Aqui contamos os elementos de formulário.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->solicitacao = app(CriarSolicitacaoAction::class)->executar(
        autor: $this->paeet, turma: $turma,
        disciplina: $montagem['disciplinas']['Lógica de Programação'],
        professor: $this->professor, quantidadeQuestoes: 3,
        quantidadeAlternativas: 4, prazo: now()->addWeek(),
    );

    $this->html = fn () => Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao->refresh()])
        ->html();

    /**
     * Conta tags com o atributo booleano `disabled` de verdade.
     * Procurar só pela palavra casaria com as classes utilitárias
     * `disabled:cursor-not-allowed`, que existem sempre.
     */
    $this->desabilitados = function (string $html, string $tag, ?string $contendo = null): int {
        preg_match_all('/<'.$tag.'\b[^>]*>/', $html, $encontradas);

        return collect($encontradas[0])
            ->filter(fn (string $t) => preg_match('/\sdisabled(?=[\s>])/', $t) === 1)
            ->filter(fn (string $t) => $contendo === null || str_contains($t, $contendo))
            ->count();
    };
});

it('renderiza um campo de texto para cada alternativa de cada questão', function () {
    $html = ($this->html)();

    // 3 questões × 4 alternativas.
    expect(preg_match_all('/<input[^>]*alternativas\.\d+\.texto/', $html))->toBe(12);
});

it('não deixa nenhuma tag de componente sem compilar', function () {
    $html = ($this->html)();

    // Uma tag <x-...> no HTML final significa que o Blade não a compilou.
    preg_match_all('/<x-[a-z.-]+/', $html, $encontradas);

    expect($encontradas[0])->toBe([]);
});

it('renderiza um campo de enunciado por questão, habilitado', function () {
    $html = ($this->html)();

    expect(preg_match_all('/<textarea[^>]*enunciado/', $html))->toBe(3)
        ->and(($this->desabilitados)($html, 'textarea'))->toBe(0);
});

it('renderiza um botão de alternativa correta por letra', function () {
    $html = ($this->html)();

    expect(preg_match_all('/wire:click="marcarCorreta\(/', $html))->toBe(12);
});

it('não deixa atributo dinâmico de componente vazar em tag HTML pura', function () {
    $html = ($this->html)();

    // `:atributo="php"` só é interpretado em componentes; num <button>
    // ele viraria uma diretiva do Alpine e quebraria a página.
    expect($html)->not->toContain(':aria-pressed="$');
});

it('desabilita os campos quando a solicitação está encerrada', function () {
    app(EncerrarSolicitacaoAction::class)->encerrar($this->solicitacao, $this->paeet);

    expect($this->solicitacao->refresh()->aceitaEnvio())->toBeFalse();

    $html = ($this->html)();

    expect(($this->desabilitados)($html, 'textarea'))->toBe(3)
        ->and(($this->desabilitados)($html, 'input', 'alternativas.'))->toBe(12);
});

it('libera o botão de enviar quando todas as questões estão completas', function () {
    $salvar = app(SalvarQuestaoAction::class);

    foreach ($this->solicitacao->questoes()->get() as $questao) {
        $salvar->executar(
            questao: $questao,
            autor: $this->professor,
            enunciado: 'Enunciado',
            alternativas: [
                ['letra' => 'A', 'texto' => 'A', 'correta' => true],
                ['letra' => 'B', 'texto' => 'B', 'correta' => false],
                ['letra' => 'C', 'texto' => 'C', 'correta' => false],
                ['letra' => 'D', 'texto' => 'D', 'correta' => false],
            ],
            peso: 1,
        );
    }

    $html = ($this->html)();

    // O botão precisa estar clicável. Um `disabled` renderizado com valor
    // falso desabilita do mesmo jeito, e por isso o envio nunca liberava.
    expect(preg_match_all('/<button[^>]*confirmandoEnvio/', $html))->toBeGreaterThan(0)
        ->and(($this->desabilitados)($html, 'button', 'confirmandoEnvio'))->toBe(0);
});

it('mantém o botão de enviar bloqueado enquanto faltam questões', function () {
    $html = ($this->html)();

    expect(($this->desabilitados)($html, 'button', 'confirmandoEnvio'))->toBeGreaterThan(0);
});
