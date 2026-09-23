<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Snapshot imutável de uma questão dentro de uma prova. Alterações
 * posteriores na questão original nunca afetam a prova já montada.
 */
class ProvaQuestao extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'prova_questoes';

    protected $fillable = [
        'prova_id',
        'numero',
        'questao_id',
        'disciplina_id',
        'professor_id',
        'peso',
        'enunciado_snapshot',
        'blocos_snapshot',
        'alternativas_snapshot',
        'letra_correta',
        'versao_questao',
    ];

    protected function casts(): array
    {
        return [
            'alternativas_snapshot' => 'array',
            'blocos_snapshot' => 'array',
            'peso' => 'decimal:2',
            'numero' => 'integer',
            'versao_questao' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'prova.turma.curso.eixo';
    }

    /** @return BelongsTo<Prova, $this> */
    public function prova(): BelongsTo
    {
        return $this->belongsTo(Prova::class);
    }

    /** @return BelongsTo<Questao, $this> */
    public function questao(): BelongsTo
    {
        return $this->belongsTo(Questao::class);
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /** @return BelongsTo<User, $this> */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /** @return HasMany<RespostaAluno, $this> */
    public function respostas(): HasMany
    {
        return $this->hasMany(RespostaAluno::class, 'prova_questao_id');
    }

    /** @return array<int, string> letras aceitas nesta questão */
    public function letrasValidas(): array
    {
        return array_column($this->alternativas_snapshot ?? [], 'letra');
    }

    /**
     * Blocos do enunciado congelados: texto, código com linguagem e
     * imagens (pelo caminho relativo no disco público).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function blocos(): Collection
    {
        return collect($this->blocos_snapshot ?? []);
    }
}
