<?php

namespace App\Actions\Prova;

use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Gabarito da prova no CSV que os leitores de folha de respostas
 * importam.
 *
 * O formato é o do arquivo de chaves de respostas:
 *
 *     Key Letter,Question Number,Response/Mapping,Point Value,Tags
 *
 * - **Key Letter**: a versão da chave. Em branco significa a chave
 *   primária, que é o caso aqui — o sistema monta uma versão por prova.
 * - **Question Number**: a numeração contínua da prova, a mesma que o
 *   aluno vê.
 * - **Response/Mapping**: a letra correta congelada no snapshot. Uma
 *   linha por resposta aceita, como o formato pede.
 * - **Point Value**: o peso que o professor definiu para a questão.
 * - **Tags**: opcional, e por isso vai vazia.
 *
 * Nada aqui é recalculado: tudo sai do snapshot da prova, então o
 * gabarito continua valendo mesmo que a questão original mude depois.
 */
class GerarGabaritoCsvAction
{
    public const CABECALHO = [
        'Key Letter',
        'Question Number',
        'Response/Mapping',
        'Point Value',
        'Tags',
    ];

    /** Quantas questões cabem na folha de respostas de referência. */
    public const QUESTOES_NA_FOLHA = 20;

    /** Letras que a folha de respostas de referência aceita. */
    public const LETRAS_DA_FOLHA = ['A', 'B', 'C', 'D'];

    /** Limite de caracteres de uma resposta, imposto pelo formato. */
    public const TAMANHO_MAXIMO_DA_RESPOSTA = 10;

    public function conteudo(Prova $prova, User $autor): string
    {
        Gate::forUser($autor)->authorize('verGabarito', $prova);

        $linhas = array_map(
            fn (array $campos) => implode(',', array_map($this->campo(...), $campos)),
            [self::CABECALHO, ...$this->linhas($prova)],
        );

        // CRLF é o fim de linha do RFC 4180.
        return implode("\r\n", $linhas)."\r\n";
    }

    /**
     * Aspas só quando o campo precisa delas.
     *
     * O `fputcsv` do PHP põe aspas em qualquer campo com espaço, e sairia
     * `"Key Letter"` num cabeçalho que o arquivo de referência traz
     * limpo. Dentro do campo, uma aspa se escreve duplicando-a.
     */
    protected function campo(string $valor): string
    {
        return preg_match('/[",\r\n]/', $valor) === 1
            ? '"'.str_replace('"', '""', $valor).'"'
            : $valor;
    }

    /**
     * Uma linha por resposta aceita. O formato pede um registro extra
     * para cada resposta alternativa da mesma questão, e cada trio
     * (versão, questão, resposta) aparece uma única vez.
     *
     * @return array<int, array<int, string>>
     */
    public function linhas(Prova $prova): array
    {
        $linhas = [];

        foreach ($this->questoes($prova) as $questao) {
            foreach ($this->respostasCorretas($questao) as $resposta) {
                $linhas[] = [
                    // Em branco: a chave primária, a única que a prova tem.
                    '',
                    (string) $questao->numero,
                    $resposta,
                    // Ponto como separador decimal, como o formato exige.
                    number_format((float) $questao->peso, 2, '.', ''),
                    // Etiquetas são opcionais.
                    '',
                ];
            }
        }

        return $linhas;
    }

    /**
     * As letras corretas da questão, sem repetição.
     *
     * Hoje cada questão tem exatamente uma correta, mas o formato prevê
     * respostas alternativas — e o snapshot já sabe dizer quais são.
     *
     * @return array<int, string>
     */
    protected function respostasCorretas(ProvaQuestao $questao): array
    {
        $letras = collect($questao->alternativas_snapshot ?? [])
            ->filter(fn (array $alternativa) => (bool) ($alternativa['correta'] ?? false))
            ->map(fn (array $alternativa) => mb_strtoupper(trim((string) $alternativa['letra'])))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Questão sem correta marcada não vira linha silenciosa: ela sai
        // com a resposta em branco, que o formato aceita, e o aviso da
        // tela explica o que falta.
        return $letras ?: [''];
    }

    /**
     * Razões para conferir antes de importar — o tamanho da folha de
     * respostas é de quem imprime, não do sistema, então isto avisa em
     * vez de bloquear.
     *
     * @return array<int, string>
     */
    public function avisos(Prova $prova): array
    {
        $avisos = [];
        $questoes = $this->questoes($prova);

        if ($questoes->count() > self::QUESTOES_NA_FOLHA) {
            $avisos[] = "A prova tem {$questoes->count()} questões e a folha de respostas de referência "
                .'tem '.self::QUESTOES_NA_FOLHA.'. Confirme que a folha escolhida comporta todas.';
        }

        foreach ($questoes as $questao) {
            foreach ($this->respostasCorretas($questao) as $resposta) {
                if ($resposta === '') {
                    $avisos[] = "A questão {$questao->numero} não tem alternativa correta marcada "
                        .'e sai com a resposta em branco.';

                    continue;
                }

                if (! in_array($resposta, self::LETRAS_DA_FOLHA, true)) {
                    $avisos[] = "A questão {$questao->numero} responde \"{$resposta}\", "
                        .'e a folha de respostas de referência aceita apenas '
                        .implode(', ', self::LETRAS_DA_FOLHA).'.';
                }

                if (mb_strlen($resposta) > self::TAMANHO_MAXIMO_DA_RESPOSTA) {
                    $avisos[] = "A resposta da questão {$questao->numero} passa de "
                        .self::TAMANHO_MAXIMO_DA_RESPOSTA.' caracteres.';
                }
            }
        }

        return array_values(array_unique($avisos));
    }

    public function nomeDoArquivo(Prova $prova): string
    {
        return app(GerarPdfDaProvaAction::class)->nomeDoArquivo($prova, comGabarito: true, extensao: 'csv');
    }

    /** @return Collection<int, ProvaQuestao> */
    protected function questoes(Prova $prova): Collection
    {
        return $prova->questoes()->orderBy('numero')->get();
    }
}
