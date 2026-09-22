<?php

namespace App\Models;

use App\Enums\PerfilUsuario;
use App\Enums\StatusQuestao;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable
{
    use HasActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome',
        'email',
        'password',
        'perfil',
        'ativo',
        'telefone',
        'criado_por',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @var array<int, int>|null */
    protected ?array $eixoIdsCache = null;

    /** @var array<int, int>|null */
    protected ?array $disciplinaIdsCache = null;

    protected ?int $questoesDevolvidasCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_login_em' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'perfil' => PerfilUsuario::class,
            'ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'email', 'perfil', 'ativo', 'telefone'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('usuario');
    }

    /**
     * Alias para integrações que esperam o atributo `name` (Fortify,
     * notificações do Laravel). A coluna canônica é `nome`.
     */
    public function getNameAttribute(): string
    {
        return (string) $this->attributes['nome'];
    }

    // ------------------------------------------------------------------
    // Relações
    // ------------------------------------------------------------------

    /** @return BelongsToMany<Eixo, $this> */
    public function eixos(): BelongsToMany
    {
        return $this->belongsToMany(Eixo::class, 'eixo_usuario', 'usuario_id', 'eixo_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Disciplina, $this> */
    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'professor_disciplina', 'usuario_id', 'disciplina_id')
            ->withPivot(['turma_id', 'ativo'])
            ->withTimestamps();
    }

    /** @return HasMany<ProfessorDisciplina, $this> */
    public function vinculosDocentes(): HasMany
    {
        return $this->hasMany(ProfessorDisciplina::class, 'usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'criado_por');
    }

    /** @return HasMany<SolicitacaoProva, $this> */
    public function solicitacoesComoProfessor(): HasMany
    {
        return $this->hasMany(SolicitacaoProva::class, 'professor_id');
    }

    /** @return HasMany<Questao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(Questao::class, 'professor_id');
    }

    // ------------------------------------------------------------------
    // Perfil
    // ------------------------------------------------------------------

    public function ehPaeetAdmin(): bool
    {
        return $this->perfil === PerfilUsuario::PaeetAdmin;
    }

    public function ehPaeet(): bool
    {
        return $this->perfil === PerfilUsuario::Paeet;
    }

    public function ehProfessor(): bool
    {
        return $this->perfil === PerfilUsuario::Professor;
    }

    /** PAEET Admin e PAEET: equipe de gestão. */
    public function ehGestao(): bool
    {
        return $this->perfil->ehGestao();
    }

    // ------------------------------------------------------------------
    // Escopo
    // ------------------------------------------------------------------

    /** @return array<int, int> */
    public function eixoIds(): array
    {
        return $this->eixoIdsCache ??= $this->eixos()->pluck('eixos.id')->all();
    }

    public function temAcessoAoEixo(?int $eixoId): bool
    {
        return $eixoId !== null
            && $this->ativo
            && in_array($eixoId, $this->eixoIds(), true);
    }

    /** Disciplinas em que o usuário atua como docente (qualquer perfil). */
    public function disciplinaIds(): array
    {
        return $this->disciplinaIdsCache ??= $this->vinculosDocentes()
            ->where('ativo', true)
            ->pluck('disciplina_id')
            ->unique()
            ->values()
            ->all();
    }

    public function lecionaDisciplina(?int $disciplinaId): bool
    {
        return $disciplinaId !== null && in_array($disciplinaId, $this->disciplinaIds(), true);
    }

    /**
     * Conta inativa não recebe link de redefinição de senha: a rota de
     * recuperação existe, mas nunca é auto-executável para quem não tem
     * acesso válido. A resposta ao visitante permanece genérica.
     */
    public function sendPasswordResetNotification($token): void
    {
        if (! $this->ativo) {
            activity('autenticacao')
                ->performedOn($this)
                ->withProperties(['motivo' => 'conta_inativa'])
                ->log('Pedido de redefinição de senha ignorado');

            return;
        }

        parent::sendPasswordResetNotification($token);
    }

    /**
     * Quantas questões deste professor voltaram para correção. Fica em
     * cache de requisição porque o menu lateral consulta em toda página.
     */
    public function questoesDevolvidas(): int
    {
        return $this->questoesDevolvidasCache ??= Questao::query()
            ->where('professor_id', $this->getKey())
            ->where('status', StatusQuestao::Rejeitada)
            ->count();
    }

    /** Invalida os caches de escopo após alterar vínculos. */
    public function esquecerEscopo(): static
    {
        $this->eixoIdsCache = null;
        $this->disciplinaIdsCache = null;
        $this->questoesDevolvidasCache = null;

        return $this;
    }
}
