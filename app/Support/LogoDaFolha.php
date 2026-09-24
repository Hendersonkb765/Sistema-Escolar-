<?php

namespace App\Support;

use App\Enums\OrigemDaLogo;
use App\Models\ModeloProva;
use Illuminate\Support\Facades\Storage;

/**
 * Uma das duas logos do cabeçalho, já resolvida.
 *
 * O sistema acompanha uma logo para cada lado (o brasão do estado à
 * esquerda, a da escola à direita), e é isso que um modelo novo usa —
 * ninguém precisa enviar as mesmas imagens a cada modelo. Quem tiver
 * outra imagem envia a sua; quem não quiser logo daquele lado escolhe
 * "nenhuma".
 *
 * A diferença entre as duas origens não é só o caminho: a padrão vem de
 * `public/`, versionada junto com o código, e a enviada vem do disco
 * público, que é dado.
 */
final class LogoDaFolha
{
    /** Arquivos que acompanham o sistema, relativos a `public/`. */
    public const PADRAO = [
        'esquerda' => 'marca/logo-estado.png',
        'direita' => 'marca/logo-escola.png',
    ];

    public const LADOS = ['esquerda', 'direita'];

    private function __construct(
        public readonly string $lado,
        public readonly OrigemDaLogo $origem,
        public readonly ?string $caminho,
    ) {}

    public static function de(ModeloProva $modelo, string $lado): self
    {
        $origem = $lado === 'esquerda'
            ? $modelo->origem_logo_esquerda
            : $modelo->origem_logo_direita;

        $caminho = $lado === 'esquerda'
            ? $modelo->logo_esquerda_path
            : $modelo->logo_direita_path;

        // Origem "enviada" sem arquivo no disco cai para a padrão: uma
        // moldura sem logo por causa de um arquivo sumido é pior do que
        // a logo do sistema.
        if ($origem === OrigemDaLogo::Enviada && ! self::existeNoDisco($caminho)) {
            $origem = OrigemDaLogo::Padrao;
        }

        return new self($lado, $origem ?? OrigemDaLogo::Padrao, $caminho);
    }

    /** Endereço para o navegador, ou null quando não há logo deste lado. */
    public function url(): ?string
    {
        return match ($this->origem) {
            OrigemDaLogo::Nenhuma => null,
            OrigemDaLogo::Enviada => Storage::disk('public')->url($this->caminho),
            OrigemDaLogo::Padrao => $this->arquivoPadraoExiste() ? '/'.self::PADRAO[$this->lado] : null,
        };
    }

    /** Caminho no disco, que o mPDF lê para embutir a imagem. */
    public function arquivo(): ?string
    {
        return match ($this->origem) {
            OrigemDaLogo::Nenhuma => null,
            OrigemDaLogo::Enviada => Storage::disk('public')->path($this->caminho),
            OrigemDaLogo::Padrao => $this->arquivoPadraoExiste() ? public_path(self::PADRAO[$this->lado]) : null,
        };
    }

    protected function arquivoPadraoExiste(): bool
    {
        return is_file(public_path(self::PADRAO[$this->lado]));
    }

    protected static function existeNoDisco(?string $caminho): bool
    {
        return $caminho !== null && Storage::disk('public')->exists($caminho);
    }
}
