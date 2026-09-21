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
| 2 | Estrutura acadêmica, grade versionada, avanço de ano, históricos | schema pronto, UI pendente |
| 3 | Solicitações de questões, área do professor, prazos | schema pronto, UI pendente |
| 4 | Análise, feedbacks, reenvio e versionamento | schema pronto, UI pendente |
| 5 | Montagem da prova, modelo e PDF | schema pronto, UI pendente |
| 6 | Importação XLS em dois passos | schema pronto, UI pendente |
| 7 | Cálculo de notas por disciplina | schema pronto, UI pendente |
| 8 | Dashboards, auditoria e refino de UI | parcial |

Todas as 24 tabelas de domínio já existem com chaves estrangeiras, índices e
constraints. Os módulos ainda sem tela respondem por
`ModuloEmConstrucaoController`, que **já aplica a Policy correspondente** —
um professor recebe 403 em `/solicitacoes/criar`, `/provas/criar` e
`/importacoes/criar` desde hoje, não quando a tela ficar pronta.
