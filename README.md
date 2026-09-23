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
| 4 | Análise, feedbacks, reenvio e versionamento | ✅ concluído |
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

Uma solicitação é o pedido das questões de **uma prova**, e a prova reúne
várias disciplinas:

```
SolicitacaoProva (a prova, com turma e prazo)
  └── SolicitacaoParte (disciplina + professor + cota de questões)
        └── Questao (ordem dentro da parte)
```

O PAEET escolhe a turma e monta a lista de pares **disciplina + professor**,
cada um com quantas questões deve entregar. As disciplinas oferecidas são só
as que **aquela turma cursa no período dela**, segundo a foto de grade que
congelou — não dá para pedir Back-end a uma turma do 1º período. A mesma
disciplina não se repete na mesma prova, e o mesmo professor pode ficar com
duas delas.

Cada questão pedida nasce em rascunho, ligada à sua parte. O professor abre
a solicitação e encontra os campos prontos — só os da parte dele.

### A entrega é por disciplina

O prazo é da prova inteira, mas o envio é de cada parte: o professor de
Lógica entrega quando termina, sem esperar pelo de Redes. A prova só fica
"enviada" quando todas as partes chegam, e o atraso de uma não contamina a
outra.

**O peso é do professor.** É ele quem sabe quanto cada questão vale dentro
da disciplina, então o peso é definido na tela de resposta, questão a
questão, e não na abertura da solicitação. Peso 1 é apenas o ponto de
partida.

### Enunciado com imagem e código

O enunciado tem um comando em texto e, depois dele, blocos ordenados:

- **código**, com a linguagem declarada — Python, JavaScript, TypeScript,
  React (JSX), HTML, CSS, Kotlin, Swift, Java, C#, C, C++, PHP, SQL, Shell
  e JSON;
- **imagem** (JPG, PNG, GIF ou WEBP até 4 MB), com legenda;
- **texto**, para intercalar explicações entre trechos de código.

Os blocos vivem em `questao_blocos` e são reordenáveis. O realce de sintaxe
usa highlight.js, e a classe `language-…` gravada no bloco é a mesma que o
PDF vai usar no milestone 5.

Designar alguém para uma disciplina cria o vínculo docente se ele ainda não
existir — é o que dará a esse professor acesso aos resultados dela.

### Enviar para análise

O professor salva rascunhos quantas vezes quiser — cada gravação confirma
com um aviso flutuante, sem recarregar a página. O botão **Enviar para
análise** só libera quando todas as questões estão completas: enunciado,
peso maior que zero, todas as alternativas preenchidas e uma marcada como
correta. Enquanto faltar alguma, a tela diz quais.

Ao confirmar, um resumo mostra disciplina, turma, quantidade e soma dos
pesos, e avisa que as questões ficarão bloqueadas para edição até a
análise. O que estiver digitado na tela é gravado antes do envio, mesmo
sem ter salvo o rascunho.

## Análise das questões

A coordenação abre a solicitação e decide questão a questão: **Aprovar**
(com comentário opcional) ou **Devolver para correção** — aqui o motivo é
obrigatório, porque é o único texto que o professor vai ler. Há também um
"Aprovar todas as pendentes" para o caso comum.

Cada decisão vira um registro em `questao_feedbacks`, que é **append-only**
e guarda a versão da questão a que se referia. Nada é sobrescrito.

### A devolução é impossível de não notar

Uma questão que volta precisa alcançar o professor sem que ele tenha de
abrir solicitação por solicitação. Ela aparece em quatro lugares:

1. **selo vermelho no menu lateral**, em "Minhas questões", com a
   contagem;
2. **indicador no painel**, em primeiro lugar entre os cartões, porque é o
   que trava o resto do fluxo;
3. **listagem de solicitações**: badge "N devolvida(s)" e a ação muda de
   "Responder" para **"Corrigir N"**, com filtro próprio;
4. **tela de resposta**: aviso vermelho no topo listando cada questão
   devolvida com o motivo, âncora para o bloco correspondente, e a questão
   em si com moldura e o título marcado.

Clicar em **Corrigir**, seja na lista de questões ou no aviso do topo, abre
**apenas aquela questão** — não a disciplina inteira. Um botão devolve a
visão completa quando ele quiser.

A questão devolvida volta a ser editável mesmo com a parte já entregue: é
exatamente o que ele precisa mexer. As demais seguem bloqueadas enquanto
aguardam análise.

O professor corrige e clica em **Reenviar corrigida**: a questão vai como
**versão nova** e volta para a fila. O histórico mostra as duas passagens:

```
v1  Rejeitada  Coordenação PAEET: A alternativa C está ambígua; reescreva.
v2  Aprovada   Coordenação PAEET: (sem comentário)
```

**Só questão aprovada entra em prova** (`Questao::aprovadas()`). Pendentes
e devolvidas ficam de fora.

Quem escreveu a questão não a analisa — vale inclusive para o PAEET que
também leciona, e a tela explica por que os botões não estão ali.

`/questoes` é a fila: a coordenação abre já filtrada pelas enviadas, o
professor vê as próprias com atalho para corrigir as devolvidas.

### Concluída não é o mesmo que encerrada

`encerrada_em` é reservado ao **fechamento manual**, que bloqueia o envio.
Uma solicitação marcada como *concluída* porque todas as questões foram
aprovadas continua aceitando correção se a coordenação devolver alguma
depois. Confundir as duas coisas travaria o reenvio.

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
