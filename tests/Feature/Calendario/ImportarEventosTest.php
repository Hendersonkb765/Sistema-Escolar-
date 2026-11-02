<?php

/*
 * Os eventos e feriados do ano chegam numa planilha: o PAEET Admin baixa
 * o modelo, a secretaria preenche, e a planilha volta. Como as outras
 * importações, em dois passos — conferir sem gravar, depois confirmar —,
 * e cada linha que não entra diz por quê.
 */

use App\Enums\TipoDeEvento;
use App\Livewire\Calendario\EventosDaEscola;
use App\Models\Eixo;
use App\Models\EventoEscolar;
use App\Support\PlanilhaDeEventos;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);

    /** Uma planilha XLSX a partir do modelo, com estas linhas. */
    $this->xlsx = function (array $linhas): UploadedFile {
        $planilha = PlanilhaDeEventos::modelo();
        $planilha->getSheet(0)->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'eventos').'.xlsx';
        (new Xlsx($planilha))->save($caminho);

        // `createWithContent`: o Livewire de teste só aceita o arquivo falso do Laravel.
        return UploadedFile::fake()->createWithContent('eventos.xlsx', file_get_contents($caminho));
    };

    $this->tela = fn ($quem = null) => Livewire::actingAs($quem ?? $this->admin)->test(EventosDaEscola::class, ['ano' => '2026']);
});

// --- O modelo ------------------------------------------------------------

it('baixa o modelo com as colunas a preencher e uma aba explicando cada uma', function () {
    $resposta = $this->actingAs($this->admin)->get(route('calendario.eventos.modelo'));

    $resposta->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('modelo-eventos-e-feriados.xlsx');

    $caminho = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
    file_put_contents($caminho, $resposta->streamedContent());
    $planilha = IOFactory::load($caminho);

    expect($planilha->getSheet(0)->getTitle())->toBe('Eventos')
        ->and($planilha->getSheet(0)->rangeToArray('A1:E1')[0])->toBe(['Título', 'Tipo', 'Data', 'Até', 'Descrição'])
        // Só o cabeçalho: exemplo na aba de eventos seria importado por engano.
        ->and($planilha->getSheet(0)->getHighestRow())->toBe(1)
        ->and($planilha->getSheet(1)->getTitle())->toBe('Como preencher')
        ->and($planilha->getSheet(1)->getCell('C3')->getValue())->toContain('Feriado, Recesso, Evento ou Reunião');
});

it('mostra o botão do modelo e a importação só ao PAEET Admin', function () {
    ($this->tela)()->assertSeeHtml(route('calendario.eventos.modelo'))->assertSee('Importar de uma planilha');

    foreach ([paeet($this->eixo), professor($this->eixo)] as $usuario) {
        ($this->tela)($usuario)->assertDontSeeHtml(route('calendario.eventos.modelo'))->assertDontSee('Importar de uma planilha');
        $this->actingAs($usuario)->get(route('calendario.eventos.modelo'))->assertForbidden();
    }
});

// --- Conferir e confirmar ------------------------------------------------

it('confere sem gravar, e importa ao confirmar', function () {
    $arquivo = ($this->xlsx)([
        ['Nossa Senhora Aparecida', 'Feriado', '12/10/2026', '', ''],
        ['Recesso de fim de ano', 'recesso', '21/12/2026', '31/12/2026', 'Sem aula.'],
        ['Conselho de classe', 'Reunião', '20/10/2026', '', 'Após a 7ª aula.'],
    ]);

    $tela = ($this->tela)()
        ->set('planilha', $arquivo)
        ->call('conferirImportacao')
        ->assertSeeHtml('<strong>3</strong> evento(s) entram no calendário.')
        ->assertSee('21/12/2026 a 31/12/2026');

    expect(EventoEscolar::query()->count())->toBe(0);

    $tela->call('confirmarImportacao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso'
            && $dados['mensagem'] === '3 evento(s) importado(s) para o calendário.')
        ->assertSet('relatorio', null);

    $recesso = EventoEscolar::query()->where('titulo', 'Recesso de fim de ano')->sole();

    expect(EventoEscolar::query()->count())->toBe(3)
        ->and($recesso->tipo)->toBe(TipoDeEvento::Recesso)
        ->and($recesso->periodo())->toBe('21/12/2026 a 31/12/2026')
        ->and($recesso->descricao)->toBe('Sem aula.')
        ->and(EventoEscolar::query()->where('titulo', 'Conselho de classe')->sole()->tipo)->toBe(TipoDeEvento::Reuniao);
});

it('aceita a data que o Excel guarda como data, e lê dia/mês sem trocar', function () {
    $arquivo = ($this->xlsx)([
        ['Finados', 'Feriado', Date::PHPToExcel(new DateTime('2026-11-02')), '', ''],
        // 10/12 é 10 de dezembro, nunca 12 de outubro.
        ['Formatura', 'Evento', '10/12/2026', '', ''],
    ]);

    ($this->tela)()->set('planilha', $arquivo)->call('conferirImportacao')->call('confirmarImportacao');

    expect(EventoEscolar::query()->where('titulo', 'Finados')->sole()->inicio->toDateString())->toBe('2026-11-02')
        ->and(EventoEscolar::query()->where('titulo', 'Formatura')->sole()->inicio->toDateString())->toBe('2026-12-10');
});

it('diz por que cada linha não entra, e importa as outras', function () {
    $arquivo = ($this->xlsx)([
        ['Proclamação da República', 'Feriado', '15/11/2026', '', ''],
        ['Festa junina', 'Festa', '20/06/2026', '', ''],
        ['Dia inexistente', 'Evento', '31/02/2026', '', ''],
        ['Semana de provas', 'Evento', '10/11/2026', '05/11/2026', ''],
        ['', 'Feriado', '25/12/2026', '', ''],
        ['Data no formato americano', 'Evento', '2026/12/01', '', ''],
    ]);

    $tela = ($this->tela)()->set('planilha', $arquivo)->call('conferirImportacao');

    expect($tela->get('relatorio')['recusadas'])->toBe([
        ['linha' => 3, 'titulo' => 'Festa junina', 'erro' => 'Tipo "Festa" não reconhecido. Use Feriado, Recesso, Evento ou Reunião.'],
        ['linha' => 4, 'titulo' => 'Dia inexistente', 'erro' => 'Data "31/02/2026" não reconhecida. Use dia/mês/ano, como 12/10/2026.'],
        ['linha' => 5, 'titulo' => 'Semana de provas', 'erro' => 'A data final (05/11/2026) vem antes da inicial (10/11/2026).'],
        ['linha' => 6, 'titulo' => null, 'erro' => 'Sem título.'],
        ['linha' => 7, 'titulo' => 'Data no formato americano', 'erro' => 'Data "2026/12/01" não reconhecida. Use dia/mês/ano, como 12/10/2026.'],
    ]);

    $tela->assertSee('Linha 3 (Festa junina): Tipo "Festa" não reconhecido.')
        ->call('confirmarImportacao');

    expect(EventoEscolar::query()->pluck('titulo')->all())->toBe(['Proclamação da República']);
});

it('deixa de fora o que já está no calendário e o repetido na planilha', function () {
    EventoEscolar::query()->create(['titulo' => 'Nossa Senhora Aparecida', 'tipo' => TipoDeEvento::Feriado, 'inicio' => '2026-10-12', 'fim' => '2026-10-12']);

    $arquivo = ($this->xlsx)([
        // Caixa diferente, mesmo evento.
        ['NOSSA SENHORA APARECIDA', 'Feriado', '12/10/2026', '', ''],
        ['Finados', 'Feriado', '02/11/2026', '', ''],
        ['Finados', 'Feriado', '02/11/2026', '', ''],
    ]);

    $tela = ($this->tela)()->set('planilha', $arquivo)->call('conferirImportacao')
        ->assertSeeHtml('<strong>1</strong> evento(s) entram no calendário.')
        ->assertSee('2 já estão nele e ficam de fora.');

    expect(array_column($tela->get('relatorio')['repetidos'], 'linha'))->toBe([2, 4]);

    $tela->call('confirmarImportacao');

    expect(EventoEscolar::query()->count())->toBe(2);

    // Reenviar a mesma planilha não duplica o ano.
    ($this->tela)()->set('planilha', ($this->xlsx)([['Finados', 'Feriado', '02/11/2026', '', '']]))
        ->call('conferirImportacao')
        ->assertSeeHtml('<strong>0</strong> evento(s) entram no calendário.')
        ->call('confirmarImportacao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['mensagem']
            === 'Nenhum evento novo para importar: as linhas ou já estão no calendário, ou não puderam ser lidas.');

    expect(EventoEscolar::query()->count())->toBe(2);
});

it('lê o CSV do Excel brasileiro: ponto e vírgula, acentos, colunas em outra ordem', function () {
    $conteudo = mb_convert_encoding(
        "DATA;TITULO;Tipo;Descrição\n12/10/2026;Nossa Senhora Aparecida;Feriado;\n20/10/2026;Conselho de classe;Reunião;Após a 7ª aula\n",
        'Windows-1252', 'UTF-8',
    );
    ($this->tela)()
        ->set('planilha', UploadedFile::fake()->createWithContent('eventos.csv', $conteudo))
        ->call('conferirImportacao')
        ->assertSeeHtml('<strong>2</strong> evento(s) entram no calendário.')
        ->call('confirmarImportacao');

    $conselho = EventoEscolar::query()->where('titulo', 'Conselho de classe')->sole();

    expect($conselho->tipo)->toBe(TipoDeEvento::Reuniao)
        ->and($conselho->descricao)->toBe('Após a 7ª aula');
});

it('recusa a planilha sem as colunas obrigatórias, dizendo quais', function () {
    ($this->tela)()
        ->set('planilha', UploadedFile::fake()->createWithContent('eventos.csv', "Nome;Quando\nFinados;02/11/2026\n"))
        ->call('conferirImportacao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'A planilha não tem a(s) coluna(s): Tipo, Data. Baixe o modelo e preencha sem mudar o cabeçalho.')
        ->assertSet('relatorio', null);
});

it('descarta a conferência sem gravar nada', function () {
    ($this->tela)()
        ->set('planilha', ($this->xlsx)([['Finados', 'Feriado', '02/11/2026', '', '']]))
        ->call('conferirImportacao')
        ->call('descartarImportacao')
        ->assertSet('relatorio', null)
        ->assertSet('planilha', null);

    expect(EventoEscolar::query()->count())->toBe(0);
});

it('não confirma sem conferir antes', function () {
    ($this->tela)()
        ->set('planilha', ($this->xlsx)([['Finados', 'Feriado', '02/11/2026', '', '']]))
        ->call('confirmarImportacao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['mensagem'] === 'Confira a planilha antes de confirmar.');

    expect(EventoEscolar::query()->count())->toBe(0);
});

it('não deixa o PAEET nem o professor importar', function () {
    $arquivo = ($this->xlsx)([['Finados', 'Feriado', '02/11/2026', '', '']]);

    foreach ([paeet($this->eixo), professor($this->eixo)] as $usuario) {
        ($this->tela)($usuario)->set('planilha', $arquivo)->call('conferirImportacao')->assertForbidden();
    }

    expect(EventoEscolar::query()->count())->toBe(0);
});
