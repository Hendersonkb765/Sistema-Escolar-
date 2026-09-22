# PAEET Avaliações

Plataforma de gestão acadêmica e do ciclo completo de avaliações: estrutura
acadêmica hierárquica, solicitação de questões aos professores, análise e
aprovação, montagem automática de provas, geração em PDF, importação de
resultados via planilha e cálculo de notas por disciplina com pesos.

## Stack

| Camada | Escolha |
|---|---|
| Runtime | PHP 8.4 · Laravel 12 |
| Banco | SQLite em desenvolvimento · MySQL 8 em produção (ver `.env.example`) |
| Front | Livewire 3 · Blade · Tailwind CSS 3 · Alpine (embarcado no Livewire) |
| Auth | Laravel Fortify **sem registro público** |
| PDF | `barryvdh/laravel-dompdf` |
| Planilhas | `maatwebsite/excel` |
| Auditoria | `spatie/laravel-activitylog` v5 |
| Testes | Pest 3 |
| Filas | driver `database` |

## Regra de ouro

**Não existe cadastro público.** Não há rota, tela, link ou controller de
registro; `Features::registration()` está desligada e a action
`CreateNewUser` foi removida. Uma conta só nasce em `/usuarios/criar`, criada
por um PAEET Admin ou por um PAEET, e só entra se existir e estiver com
`ativo = true`.

O `ativo` é verificado em três camadas independentes:

1. `Fortify::authenticateUsing()` — barra o login;
2. `GarantirUsuarioAtivo` — middleware `web`, derruba a sessão em curso;
3. `Gate::before()` — nega qualquer autorização, inclusive fora do HTTP.

## Como rodar

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # DemonstracaoSeeder: recusa rodar em produção
npm run build                       # ou: npm run dev
php artisan serve
```

Contas de demonstração (senha `senha-forte-123`):

| E-mail | Perfil | Escopo |
|---|---|---|
| `admin@paeet.local` | PAEET Admin | Tecnologia + Gestão |
| `paeet.tec@paeet.local` | PAEET (também leciona) | Tecnologia |
| `paeet.adm@paeet.local` | PAEET | Gestão |
| `professor@paeet.local` | Professor | Tecnologia |

Em produção não há seeder de acesso. A primeira conta nasce pelo terminal:

```bash
php artisan usuario:criar-admin
```

## Escopo por Eixo

O isolamento multi-tenant é lógico e se apoia no pivot `eixo_usuario`. Cada
model declara em `caminhoDoEixo()` o caminho relacional até o Eixo, e o trait
`AplicaEscopoDeEixo` usa isso para dois fins:

- `Model::query()->visivelPara($usuario)` — filtra listagens, selects e
  validações de formulário;
- `$model->dentroDoEscopoDe($usuario)` — decisão individual das Policies.

**Não há Global Scope, de propósito.** Um global scope faria o Route Model
Binding devolver 404 para um recurso de outro Eixo, enquanto o critério de
aceite exige 403. O binding resolve sem filtro e a Policy responde 403.

Professores não são escopados por Eixo, e sim pelo próprio trabalho: veem as
solicitações endereçadas a eles, as próprias questões e os resultados das
disciplinas que lecionam (`professor_disciplina`).

## Vínculo docente

Um PAEET que também dá aula **não recebe uma segunda conta**. O vínculo vive
em `professor_disciplina` e independe do perfil.

## Testes

```bash
./vendor/bin/pest
./vendor/bin/pint            # estilo de código
```

## Estado dos milestones

| # | Milestone | Situação |
|---|---|---|
| 1 | Setup, auth sem registro público, perfis, escopo, policies, testes | ✅ concluído |
| 2 | Estrutura acadêmica, grade versionada, avanço de ano, históricos | ✅ concluído |
| 3 | Solicitações de questões, área do professor, prazos | ✅ concluído |
| 4 | Análise, feedbacks, reenvio e versionamento | schema pronto, UI pendente |
| 5 | Montagem da prova, modelo e PDF | schema pronto, UI pendente |
| 6 | Importação XLS em dois passos | schema pronto, UI pendente |
| 7 | Cálculo de notas por disciplina | schema pronto, UI pendente |
| 8 | Dashboards, auditoria e refino de UI | parcial |

Todas as 24 tabelas de domínio já existem com chaves estrangeiras, índices e
constraints. Os módulos ainda sem tela respondem por
`ModuloEmConstrucaoController`, que **já aplica a Policy correspondente** —
um professor recebe 403 em `/provas/criar` e `/importacoes/criar` desde
hoje, não quando a tela ficar pronta.

## Solicitação de questões

O PAEET abre uma solicitação escolhendo turma, disciplina e professor. As
disciplinas oferecidas são só as que **aquela turma cursa no período dela**,
segundo a foto de grade que congelou — não dá para pedir Back-end a uma
turma do 1º período.

Cada questão pedida vira um item com seu peso, e uma questão em rascunho já
ligada a ele. O professor abre a solicitação e encontra os campos prontos;
o peso aparece, mas só para leitura. `peso` está fora do `$fillable` de
`Questao` justamente para isso.

Designar alguém para uma disciplina cria o vínculo docente se ele ainda não
existir — é o que dará a esse professor acesso aos resultados dela.

### Prazo não bloqueia

Esta é a regra que mais costuma ser implementada errado:

- prazo vencido deixa a solicitação **"Atrasada"**, e ela continua
  aceitando questões;
- o envio depois do prazo é aceito e fica **"Enviada em atraso"**, com o
  prazo original e a data real preservados;
- o que fecha o envio é o **encerramento ou cancelamento manual** pelo
  PAEET — reversível por `reabrir`.

## Estrutura acadêmica

```
Eixo → Curso → Disciplina (com período)
              → Turma (de um período) → Aluno
```

A **disciplina pertence a um curso** e carrega o período em que é cursada.
Um curso de dois anos tem, por exemplo, Lógica e Redes no 1º período e
Back-end e Front-end no 2º. A **turma cursa um período** e tem nome livre —
"2 A", "3B", "Noturno A".

Isso é o cadastro: o estado atual, editável a qualquer momento.

## Grade versionada: a foto do curso

A grade **não é um formulário**, é o registro de como o curso estava num
momento. `PublicarVersaoDeGradeAction` tira uma foto das disciplinas ativas
do curso e a congela como nova versão vigente, arquivando a anterior sem
apagá-la. Cada turma aponta para a foto com que começou.

É isso que permite mover Back-end do 2º para o 1º período sem reescrever o
percurso de quem já cursou: a turma antiga segue na v1, a nova nasce na v2.
`CompararGradeComCursoAction` alimenta o aviso de "mudanças ainda não
publicadas" na tela do curso.

A primeira turma de um curso publica a v1 automaticamente, para não travar o
fluxo numa etapa extra.

## Avanço de ano

`AvancarTurmaAction` roda em transação e grava o estado anterior em
`turma_historicos` **antes** de qualquer escrita. Tem duas modalidades:

- **promoção** — a própria turma avança: "2 A" passa a "3 A", os alunos
  seguem juntos e as disciplinas do novo período saem da foto congelada;
- **remanejamento** — os alunos vão para uma turma já aberta do período
  seguinte, cada movimentação com registro em `aluno_historicos`, e a turma
  de origem passa a "Concluída".

Quando a escola mantém "1 A", "2 A" e "3 A" no mesmo período letivo,
promover "2 A" esbarraria na turma "3 A" existente. Nesse caso a action
recusa com a razão e as saídas possíveis, em vez de estourar a unicidade no
banco.

### Autorização x regra de negócio

As Policies respondem apenas "este recurso é seu?" (403). "Dá para fazer
isto agora?" — turma no último ano, grade sem disciplinas, identificação em
conflito — é decidido nas actions, que lançam `RegraDeNegocioException` com
uma mensagem explicativa. Misturar as duas coisas transformaria um "ainda
não dá" em um 403 mudo.
