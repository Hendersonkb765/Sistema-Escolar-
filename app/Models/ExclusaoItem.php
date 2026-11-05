<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um registro que saiu num lote da Lixeira. */
class ExclusaoItem extends Model
{
    protected $table = 'exclusao_itens';

    protected $fillable = ['exclusao_id', 'registro_type', 'registro_id', 'dados'];

    protected function casts(): array
    {
        return ['dados' => 'array'];
    }

    /** @return BelongsTo<Exclusao, $this> */
    public function exclusao(): BelongsTo
    {
        return $this->belongsTo(Exclusao::class);
    }
}
