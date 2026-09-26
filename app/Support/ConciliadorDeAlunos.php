<?php

namespace App\Support;

use App\Models\Aluno;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Descobre de qual aluno da turma é cada linha da planilha.
 *
 * O cadastro **não é alterado**: o nome do sistema continua como está, e
 * aqui só se diz a qual aluno a linha pertence. Quando não dá para
 * afirmar, a linha é recusada com o motivo — importar no aluno errado é
 * pior do que não importar.
 *
 * A ordem das tentativas vai da mais firme para a mais frouxa, e cada
 * resultado diz por qual delas passou, para quem confere poder julgar.
 */
final class ConciliadorDeAlunos
{
    public const POR_MATRICULA = 'matricula';

    public const POR_NOME = 'nome';

    public const POR_PRIMEIRO_E_ULTIMO = 'primeiro-e-ultimo';

    public const NAO_ENCONTRADO = 'nao-encontrado';

    public const AMBIGUO = 'ambiguo';

    /** @var Collection<int, Aluno> */
    protected Collection $alunos;

    /** @param  Collection<int, Aluno>  $alunos */
    public function __construct(Collection $alunos)
    {
        $this->alunos = $alunos;
    }

    /**
     * @return array{aluno: ?Aluno, criterio: string, candidatos: array<int, string>}
     */
    public function conciliar(LinhaDeResultado $linha): array
    {
        // A matrícula, quando vem preenchida, é mais firme que o nome.
        if ($linha->identificacao !== null) {
            $porMatricula = $this->alunos->firstWhere(
                fn (Aluno $aluno) => self::comparavel($aluno->matricula) === self::comparavel($linha->identificacao)
            );

            if ($porMatricula !== null) {
                return $this->achado($porMatricula, self::POR_MATRICULA);
            }
        }

        $procurado = self::comparavel($linha->nomeCompleto());

        $exatos = $this->alunos->filter(
            fn (Aluno $aluno) => self::comparavel($aluno->nome) === $procurado
        );

        if ($exatos->count() === 1) {
            return $this->achado($exatos->first(), self::POR_NOME);
        }

        if ($exatos->count() > 1) {
            return $this->duvida($exatos);
        }

        // "Ana Paula Souza" no sistema e "Ana Souza" na planilha: o nome
        // do meio costuma faltar no leitor.
        $pelasPontas = $this->alunos->filter(
            fn (Aluno $aluno) => self::pontas($aluno->nome) === self::pontas($linha->nomeCompleto())
        );

        return match (true) {
            $pelasPontas->count() === 1 => $this->achado($pelasPontas->first(), self::POR_PRIMEIRO_E_ULTIMO),
            $pelasPontas->count() > 1 => $this->duvida($pelasPontas),
            default => ['aluno' => null, 'criterio' => self::NAO_ENCONTRADO, 'candidatos' => []],
        };
    }

    /** @return array{aluno: Aluno, criterio: string, candidatos: array<int, string>} */
    protected function achado(Aluno $aluno, string $criterio): array
    {
        return ['aluno' => $aluno, 'criterio' => $criterio, 'candidatos' => []];
    }

    /**
     * @param  Collection<int, Aluno>  $candidatos
     * @return array{aluno: null, criterio: string, candidatos: array<int, string>}
     */
    protected function duvida(Collection $candidatos): array
    {
        return [
            'aluno' => null,
            'criterio' => self::AMBIGUO,
            'candidatos' => $candidatos
                ->map(fn (Aluno $aluno) => $aluno->nome.' ('.$aluno->matricula.')')
                ->values()
                ->all(),
        ];
    }

    /** Sem acento, sem maiúscula e sem espaço sobrando. */
    public static function comparavel(?string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $texto))) ?? '');
    }

    /** O primeiro e o último nome, que é o que o leitor costuma trazer. */
    public static function pontas(string $nome): string
    {
        $partes = array_values(array_filter(explode(' ', self::comparavel($nome))));

        return $partes === [] ? '' : $partes[0].' '.end($partes);
    }
}
