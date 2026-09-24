<?php

namespace App\Models;

use App\Enums\LinguagemCodigo;
use App\Enums\TipoBlocoQuestao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Um pedaço do enunciado: parágrafo, trecho de código com linguagem, ou
 * imagem. A ordem define como aparecem na tela e na prova impressa.
 */
class QuestaoBloco extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'questao_blocos';

    protected $fillable = ['questao_id', 'ordem', 'tipo', 'conteudo', 'linguagem', 'caminho', 'legenda'];

    protected $attributes = ['tipo' => 'texto'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoBlocoQuestao::class,
            'linguagem' => LinguagemCodigo::class,
            'ordem' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'questao.solicitacao.curso.eixo';
    }

    /** @return BelongsTo<Questao, $this> */
    public function questao(): BelongsTo
    {
        return $this->belongsTo(Questao::class);
    }

    public function ehCodigo(): bool
    {
        return $this->tipo === TipoBlocoQuestao::Codigo;
    }

    public function ehImagem(): bool
    {
        return $this->tipo === TipoBlocoQuestao::Imagem;
    }

    /** URL pública da imagem, para exibir na tela. */
    public function urlDaImagem(): ?string
    {
        return $this->caminho === null ? null : Storage::disk('public')->url($this->caminho);
    }

    /** Caminho absoluto, que o mPDF precisa para embutir a imagem. */
    public function caminhoAbsolutoDaImagem(): ?string
    {
        if ($this->caminho === null || ! Storage::disk('public')->exists($this->caminho)) {
            return null;
        }

        return Storage::disk('public')->path($this->caminho);
    }

    /** Um bloco vazio não deve ser gravado nem impresso. */
    public function estaVazio(): bool
    {
        return match ($this->tipo) {
            TipoBlocoQuestao::Imagem => $this->caminho === null,
            default => blank($this->conteudo),
        };
    }
}
