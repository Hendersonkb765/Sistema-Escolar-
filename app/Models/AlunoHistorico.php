<?php

namespace App\Models;

use App\Enums\EventoHistorico;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro append-only das mudanças de turma e status de um aluno. */
class AlunoHistorico extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'aluno_historicos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'aluno_id',
        'evento',
        'turma_anterior_id',
        'turma_nova_id',
        'status_anterior',
        'status_novo',
        'motivo',
        'metadados',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'evento' => EventoHistorico::class,
            'metadados' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'aluno.turma.curso.eixo';
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return BelongsTo<Turma, $this> */
    public function turmaAnterior(): BelongsTo
    {
        // Registro do que aconteceu: mostra o nome mesmo com ele na Lixeira.
        return $this->belongsTo(Turma::class, 'turma_anterior_id')->withTrashed();
    }

    /** @return BelongsTo<Turma, $this> */
    public function turmaNova(): BelongsTo
    {
        // Registro do que aconteceu: mostra o nome mesmo com ele na Lixeira.
        return $this->belongsTo(Turma::class, 'turma_nova_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
