<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Um lote da Lixeira: o registro excluído e tudo o que saiu com ele.
 */
class Exclusao extends Model
{
    protected $table = 'exclusoes';

    protected $fillable = [
        'registro_type', 'registro_id', 'rotulo', 'eixo_id', 'resumo',
        'excluido_por', 'restaurado_em', 'restaurado_por',
    ];

    protected function casts(): array
    {
        return [
            'resumo' => 'array',
            'restaurado_em' => 'datetime',
        ];
    }

    /** O registro principal, mesmo na Lixeira. */
    public function registro(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** @return HasMany<ExclusaoItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(ExclusaoItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function excluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluido_por')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function restauradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restaurado_por')->withTrashed();
    }

    /** @param  Builder<Exclusao>  $query */
    public function scopeNaLixeira(Builder $query): Builder
    {
        return $query->whereNull('restaurado_em');
    }

    /** "2 aluno(s), 1 prova(s) e 2 questão(ões)": o que saiu junto, na ordem da exclusão. Vazio se nada. */
    public function oQueSaiuJunto(): string
    {
        return collect($this->resumo['contagens'] ?? [])
            ->map(fn (array $par) => "{$par['quantos']} {$par['rotulo']}")
            ->join(', ', ' e ');
    }
}
