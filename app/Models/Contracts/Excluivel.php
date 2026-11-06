<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Um registro que vai para a Lixeira levando o que depende dele.
 *
 * Quem decide o que vai junto é o próprio model, porque é ele que sabe o
 * que ficaria órfão. O que fica — histórico, fotos de grade, aulas de uma
 * ausência — não entra aqui: segue mostrando o nome do excluído.
 */
interface Excluivel
{
    /** "Turma 1 A (2026)": como o registro aparece na confirmação e na Lixeira. */
    public function rotuloParaExclusao(): string;

    /**
     * O que sai junto, por exclusão lógica. Cada um pode levar os seus.
     *
     * @return Collection<int, Model>
     */
    public function dependentesNaExclusao(): Collection;

    /**
     * O que sai junto de fato — não pode ficar ocupando lugar —, e é
     * recriado na restauração a partir de uma cópia.
     *
     * @return Collection<int, Model>
     */
    public function retiradosNaExclusao(): Collection;

    /**
     * O que muda sem sair: "140 notas lançadas deixam de aparecer",
     * "continua nas 2 grades já publicadas". Vai para a confirmação.
     *
     * @return array<int, string>
     */
    public function avisosDaExclusao(): array;

    /**
     * O que impede excluir agora, ou null. Não é falta de permissão (isso
     * é a Policy): é o registro que, saindo, deixaria outros sem sentido —
     * a grade usada por turmas, a conta com histórico. A confirmação mostra
     * o motivo no lugar do botão.
     */
    public function impedimentoDaExclusao(): ?string;

    /** Quantas confirmações a exclusão pede: 1, ou 2 para o que leva muita coisa. */
    public function confirmacoesParaExcluir(): int;

    /**
     * O registro de cima, que precisa estar fora da Lixeira para este
     * voltar. Nulo quando não há.
     */
    public function paiNaRestauracao(): ?Model;
}
