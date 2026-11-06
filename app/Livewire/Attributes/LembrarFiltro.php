<?php

namespace App\Livewire\Attributes;

use Attribute;

/**
 * Marca a propriedade de uma tela como filtro a lembrar: busca, filtro,
 * ordenação. O valor sobrevive à navegação até o navegador fechar
 * (ver App\Livewire\Concerns\LembraFiltros).
 *
 * Só filtro: o que é foco ou seleção do momento — a questão aberta, a
 * aba, o modelo escolhido para gerar documentos — não leva a marca, ou a
 * tela reabriria num ponto que a pessoa não escolheu agora.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class LembrarFiltro {}
