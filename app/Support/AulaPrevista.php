<?php

namespace App\Support;

use App\Models\EscopoAula;
use App\Models\QuadroHorario;
use Illuminate\Support\Collection;

/**
 * Uma aula que o professor daria num dia, tirada do quadro, com a aula
 * do escopo que caberia nela.
 *
 * A sugestão é a k-ésima aula do escopo da semana, sendo k a posição
 * deste horário entre os horários semanais da disciplina na turma: a
 * primeira aula de Lógica da semana leva a primeira aula do escopo, a
 * segunda leva a segunda. É um palpite, e o professor pode trocá-lo.
 *
 * Sem sugestão, `motivo` diz por quê — calendário que falta, semana sem
 * escopo — e o conteúdo escrito passa a ser obrigatório.
 */
final readonly class AulaPrevista
{
    /**
     * @param  Collection<int, EscopoAula>  $opcoes  As aulas do escopo da semana, na ordem do documento.
     */
    public function __construct(
        public QuadroHorario $quadro,
        public SemanaLetiva $local,
        public Collection $opcoes,
        public ?EscopoAula $sugerida,
        public ?string $motivo,
    ) {}

    /** "1ª aula, 07:00–07:50 · Lógica · turma 1 A" */
    public function descricao(): string
    {
        return "{$this->quadro->aula_numero}ª aula, {$this->quadro->intervalo()} · "
            ."{$this->quadro->disciplina->nome} · turma {$this->quadro->turma->nome}";
    }

    /**
     * O que falta para esta aula entrar no pedido, ou null se nada.
     *
     * A aula do escopo, quando escolhida, precisa ser uma das oferecidas:
     * o id chega do navegador.
     */
    public function pendencia(?int $escopoAulaId, ?string $conteudo): ?string
    {
        if ($escopoAulaId !== null && ! $this->opcoes->contains('id', $escopoAulaId)) {
            return "{$this->descricao()}: a aula escolhida não é do escopo desta semana.";
        }

        if ($escopoAulaId === null && trim((string) $conteudo) === '') {
            return $this->opcoes->isEmpty()
                ? "{$this->descricao()}: descreva o conteúdo planejado."
                : "{$this->descricao()}: escolha a aula do escopo ou descreva o conteúdo planejado.";
        }

        return null;
    }
}
