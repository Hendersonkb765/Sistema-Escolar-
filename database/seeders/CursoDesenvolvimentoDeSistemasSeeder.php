<?php

namespace Database\Seeders;

use App\Actions\Academico\CompararGradeComCursoAction;
use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Enums\PerfilUsuario;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * O curso de Desenvolvimento de Sistemas como ele é na escola.
 *
 * Diferente do {@see DemonstracaoSeeder}, isto não é cenário de teste: é
 * catálogo. Roda em produção, não cria conta de acesso nenhuma e não
 * apaga nada — nem as disciplinas que encontrar fora da lista.
 *
 * Rode explicitamente, uma vez:
 *
 *     php artisan db:seed --class=CursoDesenvolvimentoDeSistemasSeeder
 *
 * Fora do `DatabaseSeeder` de propósito: ele chama a demonstração, e
 * misturar catálogo com dado de exemplo no mesmo comando é como as duas
 * coisas acabam juntas no banco de alguém.
 *
 * Reexecutar é seguro. O que já existe não é sobrescrito: divergência
 * entre o cadastro e esta lista vira aviso no terminal, porque mudar o
 * período de uma disciplina já cursada não é correção de seed — é
 * decisão acadêmica, e tem tela para isso.
 */
class CursoDesenvolvimentoDeSistemasSeeder extends Seeder
{
    private const EIXO = ['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação'];

    /**
     * Dois períodos, e por isso `duracao_anos` 2 — que é também o mínimo
     * que a restrição da tabela aceita (2 ou 3).
     */
    private const CURSO = ['codigo' => 'DS', 'nome' => 'Desenvolvimento de Sistemas', 'duracao_anos' => 2];

    /**
     * Provisória: a carga não veio com a lista das disciplinas, e 80 é o
     * padrão da coluna. Ajustar pela tela de disciplinas depois não exige
     * republicar grade nenhuma — a comparação com a foto vigente avisa.
     */
    private const CARGA_HORARIA = 80;

    /** @var array<int, array{int, string, string}> período, código, nome */
    private const DISCIPLINAS = [
        [1, 'CCMT', 'Carreira e Competências para o Mercado de Trabalho'],
        [1, 'LLP', 'Lógica e Linguagem de Programação'],
        [1, 'PDSMA', 'Processos de Desenvolvimento de Software e Metodologias Ágeis'],
        [1, 'RCSIN', 'Redes de Computadores e Segurança da Informação na Nuvem'],

        [2, 'IA', 'Inteligência Artificial'],
        [2, 'MDBD', 'Modelagem e Desenvolvimento de Banco de Dados'],
        [2, 'PBE', 'Programação Back-End'],
        [2, 'PFE', 'Programação Front-End'],
        [2, 'PMOB', 'Programação Mobile'],
        [2, 'PMDS', 'Projeto Multidisciplinar em Desenvolvimento de Sistemas'],
        [2, 'VCSM', 'Versionamento de Código e Sistemas de Mensageria'],
    ];

    public function run(): void
    {
        $eixo = Eixo::query()->firstOrCreate(
            ['codigo' => self::EIXO['codigo']],
            ['nome' => self::EIXO['nome']],
        );

        $curso = Curso::query()->firstOrCreate(
            ['eixo_id' => $eixo->getKey(), 'codigo' => self::CURSO['codigo']],
            ['nome' => self::CURSO['nome'], 'duracao_anos' => self::CURSO['duracao_anos']],
        );

        $this->command?->info("Eixo {$eixo->codigo} · curso {$curso->codigo} — {$curso->nome}");

        $criadas = $this->cadastrarDisciplinas($curso);

        $this->relatarExtras($curso);

        $this->publicarGrade($curso, $eixo, $criadas);
    }

    /**
     * Cadastra o que falta e **confere** o que já está lá.
     *
     * `firstOrCreate` sozinho mentiria por omissão: uma disciplina com o
     * mesmo código e período diferente passaria batida, e a grade sairia
     * com o percurso trocado sem ninguém ver.
     */
    private function cadastrarDisciplinas(Curso $curso): int
    {
        $criadas = 0;

        foreach (self::DISCIPLINAS as [$periodo, $codigo, $nome]) {
            /*
             * `withTrashed`: o índice único de (curso, código) continua
             * valendo para a linha removida. Sem isto, recadastrar uma
             * disciplina excluída estoura na chave, e não no seeder.
             */
            $existente = Disciplina::withTrashed()
                ->where('curso_id', $curso->getKey())
                ->where('codigo', $codigo)
                ->first();

            if ($existente === null) {
                Disciplina::query()->create([
                    'curso_id' => $curso->getKey(),
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'periodo' => $periodo,
                    'carga_horaria' => self::CARGA_HORARIA,
                ]);

                $criadas++;

                continue;
            }

            if ($existente->trashed()) {
                $existente->restore();

                $this->command?->warn("  {$codigo} estava excluída e foi restaurada.");
            }

            if ($existente->nome !== $nome) {
                $this->command?->warn(
                    "  {$codigo} está cadastrada como “{$existente->nome}”, e não “{$nome}”. Mantida."
                );
            }

            if ((int) $existente->periodo !== $periodo) {
                $this->command?->warn(
                    "  {$codigo} está no {$existente->periodo}º período, e esta lista diz {$periodo}º. "
                    .'Mantida: mudar período de disciplina já cursada é decisão acadêmica, não de seed.'
                );
            }
        }

        $total = count(self::DISCIPLINAS);

        $this->command?->info("{$criadas} disciplina(s) cadastrada(s); {$total} na lista oficial.");

        return $criadas;
    }

    /**
     * Disciplinas do curso que não estão nesta lista.
     *
     * Só avisa. Apagar seria resolver no escuro: pode ser disciplina de
     * uma versão anterior da grade, com turma cursando agora.
     */
    private function relatarExtras(Curso $curso): void
    {
        $oficiais = array_column(self::DISCIPLINAS, 1);

        $extras = $curso->disciplinas()->ativas()
            ->whereNotIn('codigo', $oficiais)
            ->orderBy('periodo')->orderBy('nome')
            ->get();

        if ($extras->isEmpty()) {
            return;
        }

        $this->command?->warn(
            "{$extras->count()} disciplina(s) do curso estão fora desta lista e foram mantidas:"
        );

        foreach ($extras as $extra) {
            $this->command?->warn("  {$extra->codigo} — {$extra->nome} ({$extra->periodo}º período)");
        }
    }

    /**
     * Publica a grade quando há o que publicar.
     *
     * Precisa de um usuário de gestão do Eixo: publicar é ato de alguém,
     * fica no log de atividade e a Policy exige escopo. Sem ninguém
     * cadastrado ainda, o seeder para aqui e diz o que falta — criar
     * usuário em seed é exatamente o que este projeto não faz.
     */
    private function publicarGrade(Curso $curso, Eixo $eixo, int $criadas): void
    {
        $autor = $this->gestorDo($eixo);

        if ($autor === null) {
            $this->command?->warn(
                'Nenhum PAEET vinculado ao Eixo '.$eixo->codigo.': a grade não foi publicada. '
                .'Cadastre a coordenação e publique a primeira versão pela tela de grades.'
            );

            return;
        }

        $vigente = $curso->gradeVigente();
        $comparacao = app(CompararGradeComCursoAction::class)->executar($curso, $vigente);

        if ($vigente !== null && ! $comparacao['divergente']) {
            $this->command?->info("Grade v{$vigente->versao} já reflete o cadastro. Nada a publicar.");

            return;
        }

        /*
         * Nova versão, e não edição da vigente: turma aberta continua
         * apontando para a foto com que começou. É a regra do projeto, e
         * é ela que impede reescrever o percurso de quem já cursou.
         */
        $grade = app(PublicarVersaoDeGradeAction::class)->executar(
            curso: $curso,
            autor: $autor,
            observacoes: $criadas > 0 && $vigente === null
                ? 'Primeira versão, publicada com o catálogo do curso.'
                : 'Publicada após atualização do catálogo de disciplinas.',
        );

        $this->command?->info(
            "Grade v{$grade->versao} publicada por {$autor->nome} "
            ."com {$grade->disciplinas()->count()} disciplina(s)."
        );
    }

    /** O PAEET Admin do Eixo, ou qualquer PAEET dele. */
    private function gestorDo(Eixo $eixo): ?User
    {
        return User::query()
            ->where('ativo', true)
            ->whereIn('perfil', [PerfilUsuario::PaeetAdmin->value, PerfilUsuario::Paeet->value])
            ->whereHas('eixos', fn ($consulta) => $consulta->whereKey($eixo->getKey()))
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (User $usuario) => $usuario->perfil === PerfilUsuario::PaeetAdmin ? 1 : 0)
            ->first();
    }
}
