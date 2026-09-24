<?php

namespace App\Actions\Avaliacao;

use App\Enums\LinguagemCodigo;
use App\Enums\TipoBlocoQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\QuestaoBloco;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Grava os blocos que compõem o enunciado: parágrafos, trechos de código
 * com a linguagem declarada e imagens.
 *
 * Blocos vazios são descartados em silêncio — o professor costuma criar
 * um bloco e mudar de ideia, e isso não deveria virar erro de validação.
 */
class SalvarBlocosDaQuestaoAction
{
    public const TAMANHO_MAXIMO_IMAGEM_KB = 4096;

    /**
     * @param  array<int, array{tipo: string, conteudo?: ?string, linguagem?: ?string, caminho?: ?string, legenda?: ?string}>  $blocos
     */
    public function executar(Questao $questao, User $autor, array $blocos): Questao
    {
        Gate::forUser($autor)->authorize('update', $questao);

        return DB::transaction(function () use ($questao, $blocos) {
            $mantidos = [];
            $ordem = 0;

            foreach ($blocos as $dados) {
                $tipo = TipoBlocoQuestao::tryFrom($dados['tipo'] ?? '');

                if ($tipo === null) {
                    continue;
                }

                $bloco = new QuestaoBloco([
                    'tipo' => $tipo->value,
                    'conteudo' => $dados['conteudo'] ?? null,
                    'linguagem' => $tipo === TipoBlocoQuestao::Codigo
                        ? (LinguagemCodigo::tryFrom($dados['linguagem'] ?? '') ?? LinguagemCodigo::Texto)->value
                        : null,
                    'caminho' => $tipo === TipoBlocoQuestao::Imagem ? ($dados['caminho'] ?? null) : null,
                    'legenda' => $tipo === TipoBlocoQuestao::Imagem ? ($dados['legenda'] ?? null) : null,
                ]);

                if ($bloco->estaVazio()) {
                    continue;
                }

                $ordem++;

                $mantidos[] = $questao->blocos()->updateOrCreate(
                    ['ordem' => $ordem],
                    [
                        'tipo' => $bloco->tipo,
                        'conteudo' => $bloco->conteudo,
                        'linguagem' => $bloco->linguagem,
                        'caminho' => $bloco->caminho,
                        'legenda' => $bloco->legenda,
                    ],
                )->getKey();
            }

            // As sobras saem, e com elas as imagens que ninguém mais usa.
            $questao->blocos()
                ->whereKeyNot($mantidos ?: [0])
                ->get()
                ->each(function (QuestaoBloco $bloco) {
                    $this->descartarImagem($bloco->caminho);
                    $bloco->delete();
                });

            return $questao->refresh();
        });
    }

    /**
     * Guarda a imagem enviada e devolve o caminho relativo.
     *
     * Aceita apenas formatos que o navegador e o mPDF desenham; SVG
     * fica de fora porque carrega script.
     */
    public function guardarImagem(UploadedFile $arquivo, Questao $questao): string
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->extension());

        if (! in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            throw RegraDeNegocioException::porque(
                'Use uma imagem JPG, PNG, GIF ou WEBP.'
            );
        }

        if ($arquivo->getSize() > self::TAMANHO_MAXIMO_IMAGEM_KB * 1024) {
            throw RegraDeNegocioException::porque(
                'A imagem passa de '.(self::TAMANHO_MAXIMO_IMAGEM_KB / 1024).' MB.'
            );
        }

        return $arquivo->store("questoes/{$questao->getKey()}", 'public');
    }

    protected function descartarImagem(?string $caminho): void
    {
        if ($caminho !== null && Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->delete($caminho);
        }
    }
}
