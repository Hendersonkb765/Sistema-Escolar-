<?php

/*
 * O painel mostra a semana com o dia de hoje marcado.
 *
 * A coordenação vê o quadro das turmas ativas do seu Eixo, uma linha por
 * turma, agrupadas por curso. O professor vê só as próprias aulas.
 *
 * Hoje é terça, 12/05/2026.
 */

use App\Enums\DiaDaSemana;
use App\Enums\StatusTurma;
use App\Livewire\Horarios\EscalaDaSemana;
use App\Models\Eixo;
use App\Models\QuadroHorario;
use App\Models\Turma;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-05-12 08:00');

    $tec = Eixo::factory()->create(['codigo' => 'TEC']);
    $adm = Eixo::factory()->create(['codigo' => 'ADM']);
    $this->paeet = paeet($tec);

    $this->renato = professor($tec);
    $this->renato->update(['nome' => 'Renato Lima']);
    $this->marta = professor($tec);
    $this->marta->update(['nome' => 'Marta Reis']);
    $this->carlos = professor($adm);
    $this->carlos->update(['nome' => 'Carlos Dantas']);

    $desenvolvimento = cursoComGrade($tec, [1 => ['Lógica', 'Redes']], autor: $this->paeet);
    $desenvolvimento['curso']->update(['nome' => 'Desenvolvimento de Sistemas']);
    $informatica = cursoComGrade($tec, [1 => ['Hardware']], autor: $this->paeet);
    $informatica['curso']->update(['nome' => 'Informática']);
    $administracao = cursoComGrade($adm, [1 => ['Contabilidade']]);

    $this->turmaA = Turma::factory()->doCurso($desenvolvimento['curso'], $desenvolvimento['grade'])->create(['periodo' => 1, 'nome' => '1 A']);
    $this->turmaB = Turma::factory()->doCurso($informatica['curso'], $informatica['grade'])->create(['periodo' => 1, 'nome' => '1 B']);
    $this->turmaAdm = Turma::factory()->doCurso($administracao['curso'], $administracao['grade'])->create(['periodo' => 1, 'nome' => '2 C']);

    $this->cursoDesenvolvimento = $desenvolvimento['curso'];

    $aula = fn (Turma $turma, $disciplina, $professor, DiaDaSemana $dia, int $numero) => QuadroHorario::query()->create([
        'turma_id' => $turma->id, 'dia_semana' => $dia, 'aula_numero' => $numero,
        'hora_inicio' => sprintf('%02d:00', 6 + $numero), 'hora_fim' => sprintf('%02d:50', 6 + $numero),
        'disciplina_id' => $disciplina->id, 'professor_id' => $professor->id,
    ]);

    $aula($this->turmaA, $desenvolvimento['disciplinas']['Lógica'], $this->renato, DiaDaSemana::Terca, 1);
    $aula($this->turmaA, $desenvolvimento['disciplinas']['Redes'], $this->marta, DiaDaSemana::Terca, 2);
    $aula($this->turmaB, $informatica['disciplinas']['Hardware'], $this->renato, DiaDaSemana::Quinta, 1);
    $aula($this->turmaAdm, $administracao['disciplinas']['Contabilidade'], $this->carlos, DiaDaSemana::Terca, 1);
});

afterEach(fn () => Carbon::setTestNow());

it('mostra à coordenação a escala das turmas do Eixo, por curso, com professor', function () {
    $this->actingAs($this->paeet)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Escala da semana do Eixo')
        ->assertSeeInOrder(['Desenvolvimento de Sistemas', '1 A', 'Lógica', 'Renato Lima', 'Redes', 'Marta Reis', 'Informática', '1 B', 'Hardware'])
        // Outro Eixo fica de fora.
        ->assertDontSee('Contabilidade')
        ->assertDontSee('Carlos Dantas');
});

it('mostra ao professor só as aulas dele, com a turma', function () {
    $this->actingAs($this->renato)
        ->get(route('painel'))
        ->assertSee('Sua escala da semana')
        ->assertSeeInOrder(['Lógica', '· 1 A', 'Hardware', '· 1 B'])
        ->assertDontSee('Redes')
        ->assertDontSee('Marta Reis');
});

it('marca a coluna de hoje', function () {
    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->assertSee('A coluna marcada é hoje, terça.')
        ->html();

    preg_match_all('/<th[^>]*data-hoje[^>]*>\s*([^<]+)/', $html, $marcadas);

    expect($marcadas[1])->toHaveCount(1)
        ->and(trim($marcadas[1][0]))->toBe('Terça')
        ->and(substr_count($html, '>Hoje</span>'))->toBe(1);
});

it('move o marcador com o dia', function () {
    Carbon::setTestNow('2026-05-14 08:00');

    $html = Livewire::actingAs($this->renato)->test(EscalaDaSemana::class)->html();

    preg_match('/<th[^>]*data-hoje[^>]*>\s*([^<]+)/', $html, $marcada);

    expect(trim($marcada[1]))->toBe('Quinta');
});

it('diz que domingo não tem aula, sem marcar coluna nenhuma', function () {
    Carbon::setTestNow('2026-05-17 08:00');

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->assertSee('Hoje é domingo: não há aula.')
        ->html();

    expect($html)->not->toContain('data-hoje');
});

it('mostra o sábado quando é hoje, mesmo sem aula nele', function () {
    $semSabado = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)->html();
    expect($semSabado)->not->toContain('Sábado');

    Carbon::setTestNow('2026-05-16 08:00');

    expect(Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)->html())
        ->toMatch('/data-hoje[^>]*>\s*Sábado/');
});

it('filtra por curso, e marca o curso escolhido no seletor', function () {
    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->assertSee('Lógica')
        ->assertDontSee('Hardware')
        ->html();

    expect($html)->toMatch('/<option value="'.$this->cursoDesenvolvimento->id.'"\s+selected/')
        // O outro curso continua no seletor para voltar a ele.
        ->and($html)->toContain('Informática');
});

it('deixa de fora as turmas que não estão ativas', function () {
    $this->turmaB->update(['status' => StatusTurma::Concluida]);

    $this->actingAs($this->paeet)->get(route('painel'))
        ->assertSee('Lógica')
        ->assertDontSee('Hardware');
});

it('explica a escala vazia, para a coordenação e para o professor', function () {
    QuadroHorario::query()->delete();

    $this->actingAs($this->paeet)->get(route('painel'))
        ->assertSee('Nenhuma aula no quadro das turmas do seu Eixo')
        ->assertSee('pelo botão Quadro de horários');

    $this->actingAs($this->renato)->get(route('painel'))
        ->assertSee('Nenhuma aula sua no quadro de horários');
});

/*
 * A grade mostra da 1ª à 7ª aula — o dia da escola —, com as vagas em
 * branco: quem só dá a 3ª aula vê a 1ª e a 2ª vazias acima dela, e as
 * de depois também.
 */

/** Linhas vazias, da aula $de à $ate, com cinco dias cada. */
function vazias(int $de, int $ate): array
{
    return array_map(fn (int $n) => [$n, ['', '', '', '', '']], range($de, $ate));
}

/** As linhas da tabela, por número de aula: [número => [texto de cada dia]]. */
function linhasDaEscala(string $html): array
{
    preg_match_all('/<tr[^>]*data-aula="(\d+)"[^>]*>(.*?)<\/tr>/s', $html, $linhas, PREG_SET_ORDER);

    $resultado = [];

    foreach ($linhas as [, $numero, $conteudo]) {
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $conteudo, $celulas);

        $textos = array_map(fn (string $c) => trim(preg_replace('/\s+/', ' ', strip_tags($c))), $celulas[1]);

        // A célula da turma (rowspan) só vem na primeira linha do bloco; o número da aula, sempre.
        $resultado[] = [(int) $numero, array_slice($textos, array_search($numero.'ª', $textos, true) + 1)];
    }

    return $resultado;
}

it('mostra ao professor as aulas antes da dele em branco', function () {
    $joana = professor(Eixo::query()->where('codigo', 'TEC')->sole());
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Quarta, 'aula_numero' => 3,
        'hora_inicio' => '09:00', 'hora_fim' => '09:50',
        'disciplina_id' => QuadroHorario::query()->value('disciplina_id'), 'professor_id' => $joana->id,
    ]);

    $html = Livewire::actingAs($joana)->test(EscalaDaSemana::class)->html();

    // Colunas: segunda a sexta.
    expect(linhasDaEscala($html))->toBe([
        ...vazias(1, 2),
        [3, ['', '', '09:00 Lógica · 1 A', '', '']],
        ...vazias(4, 7),
    ]);
});

it('mostra a turma em linhas por aula, com os buracos do dia em branco', function () {
    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    expect(linhasDaEscala($html))->toBe([
        [1, ['', '07:00 Lógica · Renato Lima', '', '', '']],
        [2, ['', '08:00 Redes · Marta Reis', '', '', '']],
        ...vazias(3, 7),
    ]);

    // O nome da turma ocupa as sete aulas e os dois intervalos, uma vez só.
    expect($html)->toMatch('/<td rowspan="9"[^>]*>1 A<\/td>/')
        ->and(substr_count($html, '>1 A</td>'))->toBe(1);
});

it('põe na mesma célula as duas aulas de mesmo número do professor em turnos diferentes', function () {
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaB->id, 'dia_semana' => DiaDaSemana::Terca, 'aula_numero' => 1,
        'hora_inicio' => '13:00', 'hora_fim' => '13:50',
        'disciplina_id' => QuadroHorario::query()->where('turma_id', $this->turmaB->id)->value('disciplina_id'),
        'professor_id' => $this->renato->id,
    ]);

    $html = Livewire::actingAs($this->renato)->test(EscalaDaSemana::class)->html();

    expect(linhasDaEscala($html)[0])->toBe([1, ['', '07:00 Lógica · 1 A 13:00 Hardware · 1 B', '', '07:00 Hardware · 1 B', '']]);
});

it('estende a grade quando alguma turma tem aula além da 7ª', function () {
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Sexta, 'aula_numero' => 8,
        'hora_inicio' => '17:00', 'hora_fim' => '17:50',
        'disciplina_id' => QuadroHorario::query()->value('disciplina_id'), 'professor_id' => $this->renato->id,
    ]);

    $linhas = linhasDaEscala(Livewire::actingAs($this->renato)->test(EscalaDaSemana::class)->html());

    expect(array_column($linhas, 0))->toBe(range(1, 8))
        ->and($linhas[7])->toBe([8, ['', '', '', '', '17:00 Lógica · 1 A']]);
});

it('segue o número de aulas do dia configurado para a escola', function () {
    config(['instituicao.aulas_por_dia' => 5]);

    $linhas = linhasDaEscala(Livewire::actingAs($this->renato)->test(EscalaDaSemana::class)->html());

    expect(array_column($linhas, 0))->toBe(range(1, 5));
});

/*
 * Os intervalos são horários ("às 17h"), e a grade é por número de aula:
 * cada um entra depois da última aula que termina até o início dele,
 * quando há aula depois do fim dele.
 */

/** Põe na 1 A as aulas da tarde e da noite, em volta dos intervalos padrão. */
function aulasDaNoite(object $teste): void
{
    foreach ([[5, '16:10', '17:00'], [6, '17:20', '18:10'], [7, '19:50', '20:40']] as [$numero, $inicio, $fim]) {
        QuadroHorario::query()->create([
            'turma_id' => $teste->turmaA->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => $numero,
            'hora_inicio' => $inicio, 'hora_fim' => $fim,
            'disciplina_id' => QuadroHorario::query()->where('turma_id', $teste->turmaA->id)->value('disciplina_id'),
            'professor_id' => $teste->renato->id,
        ]);
    }
}

it('mostra os intervalos padrão da escola, às 17h e às 19h, entre as aulas certas', function () {
    aulasDaNoite($this);

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    expect($html)->toMatch('/data-aula="5".*?data-intervalo.*?Intervalo · 17:00–17:20.*?data-aula="6".*?data-intervalo.*?Intervalo · 19:00–19:50.*?data-aula="7"/s')
        ->and(substr_count($html, 'data-intervalo'))->toBe(2)
        // O nome da turma cobre as sete aulas e os dois intervalos.
        ->and($html)->toMatch('/<td rowspan="9"[^>]*>1 A<\/td>/');
});

it('mostra o intervalo mesmo sem aula logo depois dele, pela hora das aulas', function () {
    // Como na tela de verdade: a 3ª aula começa às 17:00, junto com o intervalo.
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Quarta, 'aula_numero' => 3,
        'hora_inicio' => '17:00', 'hora_fim' => '17:50',
        'disciplina_id' => QuadroHorario::query()->value('disciplina_id'), 'professor_id' => $this->renato->id,
    ]);

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    expect(substr_count($html, 'data-intervalo'))->toBe(2)
        ->and($html)->toMatch('/data-aula="2".*?Intervalo · 17:00–17:20.*?data-aula="3".*?Intervalo · 19:00–19:50.*?data-aula="4"/s');
});

it('situa o intervalo pela hora das outras turmas, onde a turma não tem aula', function () {
    // A 1 B só tem a 1ª aula; a 2ª e a 3ª da escola são as da 1 A.
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Quarta, 'aula_numero' => 3,
        'hora_inicio' => '17:30', 'hora_fim' => '18:20',
        'disciplina_id' => QuadroHorario::query()->value('disciplina_id'), 'professor_id' => $this->renato->id,
    ]);

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->turmaB->curso_id)
        ->html();

    expect($html)->toMatch('/data-aula="2".*?Intervalo · 17:00–17:20.*?data-aula="3"/s');
});

it('abre o dia com o intervalo que vem antes de qualquer aula', function () {
    config(['instituicao.intervalos' => [['nome' => 'Acolhida', 'inicio' => '06:30', 'minutos' => 20, 'alcance' => 'todas', 'turmas' => []]]]);

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    // O nome da turma vem na primeira linha do bloco, que agora é o intervalo.
    // Entre o <tr> e o <td>, só os comentários que o Livewire põe em volta de cada @if.
    expect($html)->toMatch('/<tr[^>]*data-intervalo[^>]*>(?:\s|<!--.*?-->)*<td rowspan="8"[^>]*>1 A<\/td>.*?Acolhida · 06:30–06:50.*?data-aula="1"/s');
});

// --- A aula de agora ------------------------------------------------------

it('marca a aula que está acontecendo agora, pelo relógio da escola', function (string $quem) {
    // 10:20 em UTC é 07:20 em Brasília: a 1ª aula de terça, das 07:00 às 07:50.
    Carbon::setTestNow(Carbon::parse('2026-05-12 10:20', 'UTC'));

    $html = Livewire::actingAs($quem === 'paeet' ? $this->paeet : $this->renato)->test(EscalaDaSemana::class)->html();

    preg_match_all('/<p[^>]*data-agora[^>]*>(.*?)<\/p>/s', $html, $agora);

    expect($agora[1])->toHaveCount(1)
        ->and(strip_tags($agora[1][0]))->toContain('Lógica')
        ->and(substr_count($html, '>Agora</span>'))->toBe(1);
})->with(['paeet', 'professor']);

it('não marca aula nenhuma fora do horário de aula, nem em outro dia', function (string $quando) {
    Carbon::setTestNow(Carbon::parse($quando, 'America/Sao_Paulo'));

    expect(Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)->html())->not->toContain('data-agora');
})->with([
    'entre a 1ª e a 2ª aula' => ['2026-05-12 07:55'],
    'no fim exato da aula' => ['2026-05-12 07:50'],
    'mesma hora, na quarta' => ['2026-05-13 07:20'],
]);

it('marca o intervalo que está acontecendo agora', function () {
    aulasDaNoite($this);
    Carbon::setTestNow(Carbon::parse('2026-05-11 17:10', 'America/Sao_Paulo'));

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    expect($html)->toMatch('/<tr[^>]*data-intervalo[^>]*data-agora[^>]*>.*?Intervalo · 17:00–17:20/s')
        ->and(substr_count($html, 'data-agora'))->toBe(1);
});

it('atualiza a escala sozinha a cada minuto', function () {
    expect(Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)->html())->toContain('wire:poll.60s');
});

it('usa o intervalo escolhido para a turma no lugar do padrão', function () {
    aulasDaNoite($this);
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => 3,
        'hora_inicio' => '09:20', 'hora_fim' => '10:10',
        'disciplina_id' => QuadroHorario::query()->value('disciplina_id'), 'professor_id' => $this->renato->id,
    ]);
    config(['instituicao.intervalos' => [
        ['nome' => 'Intervalo', 'inicio' => '17:00', 'minutos' => 20, 'alcance' => 'exceto', 'turmas' => [$this->turmaA->id]],
        ['nome' => 'Intervalo', 'inicio' => '19:00', 'minutos' => 50, 'alcance' => 'exceto', 'turmas' => [$this->turmaA->id]],
        ['nome' => 'Recreio', 'inicio' => '09:00', 'minutos' => 20, 'alcance' => 'somente', 'turmas' => [$this->turmaA->id]],
    ]]);

    $html = Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)
        ->set('curso', (string) $this->cursoDesenvolvimento->id)
        ->html();

    expect($html)->toMatch('/data-aula="2".*?data-intervalo.*?Recreio · 09:00–09:20.*?data-aula="3"/s')
        ->and($html)->not->toContain('17:00–17:20')
        ->and(substr_count($html, 'data-intervalo'))->toBe(1);
});

it('mostra ao professor só o intervalo que vale para todas as turmas dele', function () {
    aulasDaNoite($this);
    // A 1 B, onde o Renato também dá aula, fica fora do intervalo das 19h.
    config(['instituicao.intervalos' => [
        ['nome' => 'Intervalo', 'inicio' => '17:00', 'minutos' => 20, 'alcance' => 'todas', 'turmas' => []],
        ['nome' => 'Intervalo', 'inicio' => '19:00', 'minutos' => 50, 'alcance' => 'exceto', 'turmas' => [$this->turmaB->id]],
    ]]);

    $html = Livewire::actingAs($this->renato)->test(EscalaDaSemana::class)->html();

    expect($html)->toContain('Intervalo · 17:00–17:20')
        ->and($html)->not->toContain('19:00–19:50');
});
