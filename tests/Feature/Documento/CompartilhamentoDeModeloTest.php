<?php

/*
 * Compartilhar um modelo de documento com outro PAEET.
 *
 * O que se manda é uma oferta. Nada entra na lista de quem recebeu antes
 * de ele aceitar — e ao aceitar ele vira dono de uma cópia no Eixo dele,
 * que pode editar sem mexer no original. É isso que deixa o escopo por
 * Eixo intacto: nenhum modelo é visto de fora do Eixo em que nasceu.
 */

use App\Actions\Documento\CompartilharModeloAction;
use App\Actions\Documento\ResponderCompartilhamentoAction;
use App\Enums\StatusCompartilhamento;
use App\Exceptions\RegraDeNegocioException;
use App\Models\CompartilhamentoDeModelo;
use App\Models\Eixo;
use App\Models\ModeloDocumento;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->tecnologia = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->administracao = Eixo::factory()->create(['codigo' => 'ADM']);

    $this->autora = paeet($this->tecnologia);
    $this->autora->update(['nome' => 'Helena Dias', 'email' => 'helena@exemplo.test']);

    $this->colega = paeet($this->administracao);
    $this->colega->update(['nome' => 'Rui Barros', 'email' => 'rui@exemplo.test']);

    $this->modelo = ModeloDocumento::factory()->noEixo($this->tecnologia)->create([
        'nome' => 'Autorização de visita técnica',
        'criado_por' => $this->autora->id,
    ]);

    $this->compartilhar = app(CompartilharModeloAction::class);
    $this->responder = app(ResponderCompartilhamentoAction::class);
});

it('cria a oferta como pendente, sem tocar na lista de quem recebeu', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega, 'Serve para você?');

    expect($oferta->status)->toBe(StatusCompartilhamento::Pendente)
        ->and($oferta->mensagem)->toBe('Serve para você?')
        ->and($oferta->copia_id)->toBeNull()
        ->and(ModeloDocumento::query()->visivelPara($this->colega)->count())->toBe(0);
});

it('dá ao destinatário uma cópia sua quando ele aceita', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $copia = $this->responder->aceitar($oferta, $this->colega);

    expect($copia->eixo_id)->toBe($this->administracao->id)
        ->and($copia->criado_por)->toBe($this->colega->id)
        ->and($copia->copia_de)->toBe($this->modelo->id)
        ->and($copia->corpo)->toBe($this->modelo->corpo)
        ->and($copia->id)->not->toBe($this->modelo->id)
        ->and(ModeloDocumento::query()->visivelPara($this->colega)->pluck('id')->all())
        ->toBe([$copia->id]);
});

it('registra no compartilhamento qual cópia o aceite criou', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $copia = $this->responder->aceitar($oferta, $this->colega);

    expect($oferta->refresh()->status)->toBe(StatusCompartilhamento::Aceito)
        ->and($oferta->copia_id)->toBe($copia->id)
        ->and($oferta->respondido_em)->not->toBeNull();
});

it('não cria nada quando o destinatário recusa', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $this->responder->recusar($oferta, $this->colega);

    expect($oferta->refresh()->status)->toBe(StatusCompartilhamento::Recusado)
        ->and($oferta->copia_id)->toBeNull()
        ->and($oferta->respondido_em)->not->toBeNull()
        ->and(ModeloDocumento::query()->visivelPara($this->colega)->count())->toBe(0);
});

/*
 * A recusa fica registrada. Apagar a linha faria o remetente reenviar sem
 * entender, e tiraria do histórico uma resposta que foi dada.
 */
it('guarda a recusa em vez de apagar a oferta', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $this->responder->recusar($oferta, $this->colega);

    expect(CompartilhamentoDeModelo::query()->count())->toBe(1);
});

it('mantém quem é o autor do modelo original na cópia', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $copia = $this->responder->aceitar($oferta, $this->colega);

    expect($copia->loadMissing('original.autor')->original->autor->nome)->toBe('Helena Dias')
        ->and($copia->loadMissing('autor')->autor->nome)->toBe('Rui Barros');
});

it('deixa o dono da cópia editá-la sem mexer no original', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);
    $copia = $this->responder->aceitar($oferta, $this->colega);

    $copia->update(['corpo' => 'Texto trocado pelo Rui.']);

    expect($this->modelo->refresh()->corpo)->not->toBe('Texto trocado pelo Rui.')
        ->and($this->colega->can('update', $copia))->toBeTrue()
        // O original continua sendo de outro Eixo para quem recebeu.
        ->and($this->colega->can('view', $this->modelo))->toBeFalse();
});

it('registra com quem o modelo foi compartilhado e em que pé está cada um', function () {
    $terceiro = paeet(Eixo::factory()->create(['codigo' => 'SAU']));
    $terceiro->update(['nome' => 'Ana Reis']);

    $aceita = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);
    $recusada = $this->compartilhar->executar($this->modelo, $this->autora, $terceiro);

    $this->responder->aceitar($aceita, $this->colega);
    $this->responder->recusar($recusada, $terceiro);

    $registro = $this->modelo->compartilhamentos()
        ->with('destinatario:id,nome')
        ->get()
        ->mapWithKeys(fn ($c) => [$c->destinatario->nome => $c->status->value]);

    expect($registro->all())->toBe(['Rui Barros' => 'aceito', 'Ana Reis' => 'recusado']);
});

it('recusa responder duas vezes, e diz quando foi a primeira', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $this->responder->aceitar($oferta, $this->colega);

    expect(fn () => $this->responder->recusar($oferta->refresh(), $this->colega))
        ->toThrow(RegraDeNegocioException::class, 'já foi respondido');
});

it('não deixa outro PAEET responder pelo destinatário', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    $intruso = paeet(Eixo::factory()->create(['codigo' => 'OUT']));

    expect(fn () => $this->responder->aceitar($oferta, $intruso))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->responder->recusar($oferta, $intruso))
        ->toThrow(AuthorizationException::class);
});

it('não deixa o remetente responder pelo destinatário', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    expect(fn () => $this->responder->aceitar($oferta, $this->autora))
        ->toThrow(AuthorizationException::class);
});

it('nega compartilhar modelo de outro Eixo', function () {
    expect(fn () => $this->compartilhar->executar($this->modelo, $this->colega, $this->autora))
        ->toThrow(AuthorizationException::class);
});

it('não deixa compartilhar com o professor', function () {
    expect(fn () => $this->compartilhar->executar(
        $this->modelo, $this->autora, professor($this->tecnologia)
    ))->toThrow(RegraDeNegocioException::class, 'coordenação PAEET');
});

it('não deixa compartilhar com conta inativa', function () {
    $this->colega->update(['ativo' => false]);

    expect(fn () => $this->compartilhar->executar($this->modelo, $this->autora, $this->colega))
        ->toThrow(RegraDeNegocioException::class, 'inativa');
});

it('não deixa compartilhar consigo mesmo', function () {
    expect(fn () => $this->compartilhar->executar($this->modelo, $this->autora, $this->autora))
        ->toThrow(RegraDeNegocioException::class, 'já tem este modelo');
});

it('não empilha duas ofertas do mesmo modelo para a mesma pessoa', function () {
    $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    expect(fn () => $this->compartilhar->executar($this->modelo, $this->autora, $this->colega))
        ->toThrow(RegraDeNegocioException::class, 'aguardando resposta');
});

it('não reenvia para quem já aceitou', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);
    $this->responder->aceitar($oferta, $this->colega);

    expect(fn () => $this->compartilhar->executar($this->modelo, $this->autora, $this->colega))
        ->toThrow(RegraDeNegocioException::class, 'já aceitou');
});

/*
 * Recusar não é para sempre: a pessoa pode ter recusado por não entender
 * para que servia, e o remetente pode explicar melhor e mandar de novo.
 */
it('deixa reenviar para quem recusou', function () {
    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);
    $this->responder->recusar($oferta, $this->colega);

    $segunda = $this->compartilhar->executar(
        $this->modelo, $this->autora, $this->colega, 'É para a visita técnica de maio.'
    );

    expect($segunda->status)->toBe(StatusCompartilhamento::Pendente)
        ->and(CompartilhamentoDeModelo::query()->count())->toBe(2);
});

it('não sugere como destinatário professor, conta inativa nem o próprio', function () {
    professor($this->tecnologia)->update(['nome' => 'Professor Silva']);
    $inativo = paeet($this->administracao);
    $inativo->update(['nome' => 'Inativo Souza', 'ativo' => false]);

    $nomes = $this->compartilhar->destinatariosPossiveis($this->autora)->pluck('nome');

    expect($nomes)->toContain('Rui Barros')
        ->and($nomes)->not->toContain('Professor Silva')
        ->and($nomes)->not->toContain('Inativo Souza')
        ->and($nomes)->not->toContain('Helena Dias');
});

it('não deixa a cópia nascer sem Eixo onde guardar', function () {
    $semEixo = paeet();

    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $semEixo);

    expect(fn () => $this->responder->aceitar($oferta, $semEixo))
        ->toThrow(RegraDeNegocioException::class, 'nenhum Eixo');
});

/*
 * Duas cópias com o mesmo nome na mesma lista são indistinguíveis na hora
 * de escolher qual gerar.
 */
it('dá outro nome à cópia quando o nome já existe no Eixo de destino', function () {
    ModeloDocumento::factory()->noEixo($this->administracao)->create([
        'nome' => 'Autorização de visita técnica',
    ]);

    $oferta = $this->compartilhar->executar($this->modelo, $this->autora, $this->colega);

    expect($this->responder->aceitar($oferta, $this->colega)->nome)
        ->toBe('Autorização de visita técnica (2)');
});
