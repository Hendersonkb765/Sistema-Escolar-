<?php

namespace App\Models;

use App\Enums\StatusCompartilhamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A oferta de um modelo de documento a outro PAEET, e o que ele
 * respondeu.
 *
 * Não usa `AplicaEscopoDeEixo` de propósito: um compartilhamento existe
 * justamente porque atravessa Eixos. Quem o enxerga é quem mandou e quem
 * recebeu — nada mais —, e isso é o que `visivelPara` decide aqui.
 */
class CompartilhamentoDeModelo extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'compartilhamentos_de_modelo';

    protected $fillable = [
        'modelo_documento_id',
        'remetente_id',
        'destinatario_id',
        'status',
        'mensagem',
        'copia_id',
        'respondido_em',
    ];

    protected $attributes = [
        'status' => 'pendente',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusCompartilhamento::class,
            'respondido_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('compartilhamento');
    }

    /**
     * Quem mandou e quem recebeu veem; mais ninguém.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisivelPara(Builder $query, ?User $usuario): Builder
    {
        if (! $usuario instanceof User || ! $usuario->ativo) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $sub) => $sub
            ->where('remetente_id', $usuario->getKey())
            ->orWhere('destinatario_id', $usuario->getKey()));
    }

    /** @param  Builder<$this>  $query */
    public function scopeRecebidosPor(Builder $query, User $usuario): Builder
    {
        return $query->where('destinatario_id', $usuario->getKey());
    }

    /** @param  Builder<$this>  $query */
    public function scopeEnviadosPor(Builder $query, User $usuario): Builder
    {
        return $query->where('remetente_id', $usuario->getKey());
    }

    /** @param  Builder<$this>  $query */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', StatusCompartilhamento::Pendente);
    }

    /** @return BelongsTo<ModeloDocumento, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloDocumento::class, 'modelo_documento_id');
    }

    /** @return BelongsTo<User, $this> */
    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    /** A cópia que o aceite criou. Nula enquanto pendente, e para sempre se recusado. */
    /** @return BelongsTo<ModeloDocumento, $this> */
    public function copia(): BelongsTo
    {
        return $this->belongsTo(ModeloDocumento::class, 'copia_id');
    }

    public function pendente(): bool
    {
        return $this->status === StatusCompartilhamento::Pendente;
    }

    public function ehDestinatario(?User $usuario): bool
    {
        return $usuario !== null && $this->destinatario_id === $usuario->getKey();
    }
}
