# PAEET Avaliações

Plataforma de gestão acadêmica e do ciclo completo de avaliações: estrutura
acadêmica hierárquica, solicitação de questões aos professores, análise e
aprovação, montagem automática de provas, geração em PDF, importação de
resultados via planilha e cálculo de notas por disciplina com pesos.

## Stack

| Camada | Escolha |
|---|---|
| Runtime | PHP 8.5 · Laravel 13.17.0 |
| Banco | SQLite em desenvolvimento · MySQL 8 em produção (ver `.env.example`) |
| Front | Livewire 3 · Blade · Tailwind CSS 3 (tema por classe) · Alpine (embarcado no Livewire) |
| Auth | Laravel Fortify **sem registro público** |
| PDF | `mpdf/mpdf` |
| Planilhas | `maatwebsite/excel` |
| Auditoria | `spatie/laravel-activitylog` v5 |
| Testes | Pest 4 · PHPUnit 12 |
| Filas | driver `database` |

### Sobre as versões

`laravel/framework` está preso na **13.17.0 exata**, e não em `^13`: a
atualização foi pedida nessa versão. Subir dentro do 13.x é editar essa
linha do `composer.json` e rodar `composer update laravel/framework`.

O piso de PHP é `^8.5`. Quem o empurrava para cima antes era o
`spatie/laravel-activitylog` 5.x, que exige `^8.4` — o Laravel 13
sozinho aceita `^8.3`.

No PHP 8.5 o `phpoffice/phpword` 1.4 emite uma depreciação ao escrever
cada parágrafo (`Style::getStyle(null)` → *using null as an array
offset*). O `.docx` sai correto; o que vazava era ruído no log a cada
download. `GerarDocxDaProvaAction::semRuidoDoPhpWord()` cala **apenas**
`E_DEPRECATED` vindo de dentro do PhpWord — qualquer outro aviso,
inclusive uma depreciação nossa, continua passando, e há teste para as
duas coisas. A 1.4.0 é a última publicada; quando sair a correção, o
método some.

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

### No primeiro acesso, a senha passa a ser do dono

Quem cria a conta define uma senha provisória e a entrega por algum
canal — conversa, mensagem, papel. Ela serve para entrar **uma vez**:
antes de qualquer outra tela, o professor ou o PAEET escolhe a sua, e
daí em diante ninguém mais a conhece.

`usuarios.senha_definida_em` guarda quando o próprio dono escolheu.
Nulo significa que a senha em uso é de outra pessoa, e o middleware
`senha-propria` desvia para `/primeira-senha` **a cada request** — só
no login não bastaria: digitar outro endereço seguiria valendo.

Continuam abertas apenas a própria troca e a saída: quem não quiser
trocar agora pode sair, mas não usar o sistema com a senha alheia. A
tela recusa repetir a provisória, porque mantê-la deixaria a senha nas
mãos de quem a entregou.

Redefinir a senha de alguém pela coordenação zera o campo de novo:
**senha definida por outra pessoa é sempre provisória**. Trocar a
própria senha no perfil marca como definida.

Isto não é autocadastro: a conta já existe, criada pela coordenação. O
que muda é de quem é a senha.

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

## O que a demonstração traz

`php artisan migrate:fresh --seed` monta um ano letivo em andamento, não
uma tela vazia:

- **dois Eixos com curso de verdade** — Tecnologia (8 matérias) e Gestão
  (4), cada um com turma, alunos, professores e modelo de prova;
- **três bimestres fechados de ponta a ponta** na turma 1 A: solicitação,
  respostas dos professores, aprovação, prova montada e resultados
  importados. É o que faz o gráfico de evolução ter o que comparar;
- **duas solicitações em aberto** — uma no prazo, para o professor ter o
  que responder, e uma vencida, porque o selo "Atrasada" precisa de um
  caso.

Cada ciclo roda **na época dele** (`Carbon::setTestNow`). Sem isso, um
prazo no passado somado a um envio agora marcaria toda solicitação
antiga como "Entregue em atraso", e o selo vermelho apareceria em todas
em vez de apontar a única que interessa.

Os acertos são sorteados com semente fixa e a turma melhora a cada
bimestre: uma distribuição plana faria o gráfico de evolução ser uma
reta e não mostrar nada.

O seeder é idempotente — cada peça se guarda pelo próprio título.

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
| 5 | Montagem da prova, modelo, pré-visualização, PDF e Word | ✅ concluído |
| 6 | Importação XLS em dois passos e notas por disciplina | ✅ concluído |
| 7 | Cálculo de notas por disciplina | ✅ concluído (junto com o 6) |
| 8 | Dashboards, auditoria e refino de UI | parcial |

Todas as 24 tabelas de domínio existem com chaves estrangeiras, índices e
constraints, e **todos os módulos têm tela** — o
`ModuloEmConstrucaoController`, que segurava as rotas ainda sem interface,
deixou de existir.

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

### Bimestre

A solicitação declara de que **bimestre** ela é, e são quatro por ano
letivo (`App\Enums\Bimestre`). A prova **herda** o bimestre das
solicitações que produziram as suas questões — quem monta não precisa
lembrar. Quando as questões vêm de bimestres diferentes não há o que
herdar, e a tela deixa escolher. O bimestre sai no cabeçalho da folha,
no PDF e no Word.

Toda tela ligada a prova filtra por bimestre: solicitações, provas,
importações, resultados e a análise de desempenho. O filtro entra na URL
(`?bimestre=2`), então um recorte é um link.

### Habilidade avaliada

Cada questão declara **o que ela mede**, preenchido pelo professor. Sem
isso a questão não pode ser enviada — é uma pendência como as outras
(`Questao::pendencias()`), e não uma validação que impede salvar
rascunho.

A habilidade é **congelada na montagem** (`habilidade_snapshot`), como o
enunciado: a análise de uma prova antiga tem de continuar dizendo o que
aquela questão avaliava na época.

O campo é texto livre com um `datalist` das habilidades já escritas
**naquela disciplina**, para o professor reusar a mesma redação em vez
de criar uma variação a cada questão — e a análise agrupa ignorando
caixa e espaço sobrando. Não há catálogo fechado de habilidades; se a
escola quiser uma lista controlada, é o próximo passo natural.

### Negrito e itálico no enunciado

O professor formata o enunciado e os blocos de texto com marcas no
próprio texto: `**negrito**`, `*itálico*` e `***os dois***`. Os botões
**B** e *I* só envolvem a seleção; quem interpreta as marcas é
`App\Support\TextoDoEnunciado`, num lugar só, e os três destinos o
consultam:

| destino | o que recebe |
|---|---|
| tela e PDF | `paraHtml()` — tudo escapado, só as marcas viram `<strong>`/`<em>` |
| Word | `segmentos()` — trechos com `bold`/`italic`, porque o .docx é montado trecho a trecho e não lê HTML |
| onde não cabe formatar | `semMarcas()` |

Guardar HTML seria o caminho óbvio e o errado: o enunciado é entrada de
usuário e teria de ser higienizado na tela, o mPDF aceita só um
subconjunto e o Word não aceita HTML nenhum. Com marcas, o que está no
banco continua sendo texto, sem superfície de XSS.

As marcas exigem encostar no texto (`**assim**`, nunca `** assim **`).
É o que impede `3 * 4 * 5` de virar itálico — e numa prova de lógica
isso aparece.

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

## Montagem da prova

A prova é montada a partir das questões **já aprovadas** de uma turma. O
sistema não pergunta qual é a numeração: ele agrupa por disciplina, na
ordem em que as disciplinas foram pedidas na solicitação, e numera em
sequência contínua.

```
Lógica          5 questões  →  1 a 5
Processos       6 questões  →  6 a 11
Banco de Dados  4 questões  →  12 a 15
```

O aluno vê apenas o número. O sistema guarda, por dentro de cada número,
a disciplina, o professor, o peso e a letra correta — é isso que permite
depois calcular **uma nota por disciplina** em vez de uma nota só.

### O que fica congelado

`ProvaQuestao` não aponta apenas para a questão: ela **copia** enunciado,
blocos (texto, código e imagem), alternativas, letra correta, peso e o
número da versão da questão. Editar a questão original depois disso não
muda prova nenhuma já montada.

A moldura — instituição, logo, cabeçalho, rodapé — vem do **modelo de
prova** e é lida na hora de imprimir, de propósito: corrigir um erro no
papel timbrado vale também para a reimpressão de provas antigas.
Desativar um modelo o esconde da montagem sem tocar nas provas que já o
usaram.

As duas primeiras linhas da folha — o nome no topo e o nome da avaliação
— podem ser trocadas **por prova**, na montagem. Serve para uma
recuperação, ou para uma prova aplicada numa escola parceira, sem
obrigar a criar um modelo só para ela.

O campo aparece vazio, com o texto do modelo como marca-d'água, porque
em branco é o modelo que vale. Quem escreveu um texto próprio deixa de
ser alcançado por correções no modelo; quem não escreveu continua
sendo, que é o que se quer de um papel timbrado.

O **corpo do nome** também se escolhe, no modelo e na prova: "E. E.
Prof. Francisco Pereira de Souza Filho" quebra em duas linhas no
tamanho que servia a um nome curto. Ele fica fora do bloco que a ABNT
trava — a norma fala do corpo do texto, não do timbre — e é limitado
entre 8 e 28 pt, para o nome não sumir nem tomar a folha.

### Duas colunas, mesma folha para tela e papel

A pré-visualização dentro do sistema, o PDF e o Word saem da **mesma**
view (`resources/views/provas/folha/`). O que o PAEET confere na tela é
exatamente o que imprime.

Cada motor pede a sua sintaxe para a mesma coisa:

| | colunas | faixa da disciplina | linha de preencher |
|---|---|---|---|
| tela | `column-count` | `border-left/right` + fundo | `border-bottom` |
| PDF | tag `<columns>` do mPDF | idem | idem |
| Word | seção `continuous` com `colsNum` | tabela de uma célula | borda de célula |

No Word a seção das colunas vem depois do cabeçalho, que fica em uma
coluna só. E a faixa da disciplina precisa ser tabela porque o parágrafo
do Word aceita sombreado mas **não aceita borda** — sem ela, não haveria
as barras pretas dos lados.

O PDF é gerado pelo **mPDF**, e não pelo dompdf que o projeto usava
antes: o dompdf ignora `column-count` sem reclamar, e a folha saía
inteira em coluna única. A troca está coberta por teste
(`FormatacaoDaFolhaTest`), inclusive contra a regressão em que a regra
`@page` fazia o mPDF entrar em laço e devolver milhares de páginas.

Sobra uma limitação do modo de colunas do mPDF: uma questão comprida
pode ser partida entre o pé de uma coluna e o topo da outra. No
navegador o `break-inside: avoid` evita isso; no PDF, não.

A outra diferença entre tela e PDF é a origem das imagens: o navegador
usa URL, o mPDF lê o arquivo no disco.

O download é gerado **na hora**, a partir do snapshot, para nunca
entregar uma versão velha guardada em disco.

### Duas logos e as normas da ABNT

O cabeçalho leva **uma logo em cada extremo**, com a identificação ao
centro. O sistema já acompanha as duas — o brasão do estado à esquerda e
a da escola à direita, em `public/marca/` —, então um modelo novo sai
pronto sem enviar imagem nenhuma.

Cada lado escolhe entre três origens (`OrigemDaLogo`):

| | |
|---|---|
| **Padrão** | a que vem com o sistema; nada a enviar |
| **Enviada** | uma imagem própria daquele modelo, guardada no disco público |
| **Nenhuma** | aquele extremo fica sem logo |

Sair de "enviada" apaga o arquivo, que ninguém mais alcançaria pela
tela. E uma origem "enviada" cujo arquivo sumiu do disco cai de volta
para a padrão: moldura sem logo por causa de arquivo apagado à mão é
pior do que a logo do sistema.

As células dos extremos continuam existindo mesmo vazias — é o que
mantém o texto centrado quando um dos lados não tem logo.

Trocar as logos que acompanham o sistema é substituir os dois arquivos
em `public/marca/`; vale para todos os modelos que estejam no padrão. Os
formatos são PNG, JPG ou GIF: **o mPDF não desenha WEBP**.

Quem resolve de onde sai cada logo é `App\Support\LogoDaFolha`, e os três
destinos a consultam — a tela, o PDF e o **Word**. A diferença importa:
a logo padrão vem de `public/`, versionada com o código, e a enviada vem
do disco público, que é dado. Ler a coluna do caminho direto, como o
gerador do .docx fazia, deixava sem cabeçalho justamente quem não tinha
enviado imagem nenhuma.

A formatação padrão é a **ABNT (NBR 14724)**. A norma trata de trabalho
acadêmico, não de prova; o que se aproveita dela é a parte tipográfica,
que é justamente a que a escola costuma exigir:

| | |
|---|---|
| Papel | A4 |
| Margens | 3 cm em cima e à esquerda, 2 cm embaixo e à direita |
| Fonte | Arial ou Times New Roman |
| Corpo | 12 pt |
| Entrelinhas | 1,5 |
| Alinhamento | justificado |
| Trecho de código e legenda | 10 pt, espaçamento simples (o tratamento da citação longa) |
| Numeração | canto superior direito |

Enquanto a norma está marcada, o formulário do modelo **trava** corpo,
entrelinhas e margens e diz por quê — os valores são dela, não do
modelo. Quem precisar fugir da norma escolhe **Livre** e ajusta tudo.

O recuo de 4 cm da citação longa só é aplicado em **coluna única**: numa
coluna de ~7,5 cm ele não deixaria texto nenhum.

Os valores resolvidos ficam num lugar só, `App\Support\LayoutDaFolha`,
que o HTML e o Word consultam — é o que impede o .docx de divergir do
PDF.

### Gabarito

O gabarito completo é da coordenação (`ProvaPolicy::verGabarito`). O
professor abre a prova porque tem questões nela, mas não recebe as
respostas das outras disciplinas — nem pela tela, nem por `?gabarito=1`
na URL do download.

**Gerar gabarito** baixa o CSV de chaves de respostas que os leitores de
folha importam:

```
Key Letter,Question Number,Response/Mapping,Point Value,Tags
,1,A,1.50,
,2,C,1.00,
```

| coluna | o que vai |
|---|---|
| Key Letter | em branco — a chave primária, a única versão que a prova tem |
| Question Number | a numeração contínua da prova, a mesma que o aluno vê |
| Response/Mapping | a letra correta congelada no snapshot |
| Point Value | o peso que **o professor** definiu, com ponto decimal |
| Tags | vazia, porque é opcional |

Uma linha por resposta aceita — o formato prevê registros extras para
respostas alternativas da mesma questão —, e cada trio (versão, questão,
resposta) aparece uma única vez. Nada é recalculado: tudo sai do
snapshot, então o gabarito segue valendo mesmo que a questão original
mude depois.

As aspas entram só quando o campo precisa delas. O `fputcsv` do PHP põe
aspas em qualquer campo com espaço e sairia `"Key Letter"` num cabeçalho
que o arquivo de referência traz limpo, então a escrita é explícita.

O tamanho da folha de respostas é de quem imprime, não do sistema: se a
prova passar de 20 questões, se uma resposta sair do A–D ou se faltar
alternativa correta, a tela **avisa** em vez de bloquear
(`GerarGabaritoCsvAction::avisos()`).

### Arquivos públicos saem por `/storage`, sem host fixo

O disco público é configurado com uma URL **relativa à raiz**
(`config/filesystems.php`), e não montada a partir do `APP_URL`.

Com a URL absoluta, o `APP_URL=http://localhost` do ambiente de
desenvolvimento gerava `<img src="http://localhost/storage/...">`
enquanto o `artisan serve` atendia em `127.0.0.1:8000`: a imagem do
enunciado não carregava e nada na tela dizia por quê. Relativo, o
arquivo é sempre servido por quem serviu a página — qualquer host, porta
ou proxy.

Quem for servir os arquivos de outro domínio define
`FILESYSTEM_PUBLIC_URL`.

## Importação de resultados

A planilha é a que o leitor de folhas de resposta exporta:

```
Quiz Name, Class, ZipGrade Id, External Id, First Name, Last Name,
Num Questions, Num Correct, Percent Correct, Key Version, Q1, Q2, Q3…
```

Cada `Q` é uma questão: **1 acertou, 0 errou**. O leitor não diz qual
alternativa o aluno marcou, só se bateu com o gabarito — por isso
`respostas_alunos.alternativa_marcada` fica nula e o que se guarda é o
acerto.

As colunas são procuradas pelo nome, sem depender da ordem: uma coluna a
mais no meio não quebra a leitura.

### Dois passos, e o primeiro não grava nada

**Conferir** lê a planilha e monta um relatório linha a linha — de qual
aluno ela é, por qual critério foi reconhecida, e o motivo quando não
deu. **Confirmar** grava só o que a conferência aprovou, numa transação.
Entre os dois há uma tela para olhar: importar resultado no aluno errado
não tem desfazer fácil.

### O cadastro do aluno não é alterado

A planilha serve apenas para dizer de quem é cada linha. O nome no
sistema continua exatamente como está. `ConciliadorDeAlunos` tenta, da
mais firme para a mais frouxa:

| critério | quando |
|---|---|
| RA | `External Id` bate com o RA de um aluno da turma |
| nome idêntico | ignorando acento, caixa e espaço sobrando |
| primeiro e último nome | o leitor costuma encurtar "Ana Paula Souza" para "Ana Souza" |

Dois alunos com o mesmo nome, ou nenhum, **recusa a linha** e diz por
quê, com os candidatos. Reimportar a mesma prova substitui o resultado
anterior do aluno: vale a última leitura da folha, não a soma.

### O peso vale dentro da disciplina

Este é o ponto que mais se implementa errado. Uma prova com Lógica,
Redes e Banco de Dados produz **três notas independentes**, cada uma de
0 a 10:

```
nota = 10 × (soma dos pesos acertados ÷ soma dos pesos da disciplina)
```

Pesos 1; 1; 0,5; 0,5; 0,75 somam 3,75. Acertando 1 + 1 + 0,5 = 2,5, a
nota é **6,67** — é o critério de aceite 10, e tem teste com esse número.

Somar questões de disciplinas diferentes numa nota só apagaria
exatamente o que a escola quer ver, e por isso `notas_disciplina` guarda
também a soma dos pesos de cada lado: a conta fica conferível.

A tela mostra as notas e, abaixo, o acerto de cada questão (✓/✗) com o
número que o aluno viu na folha.

## Análise de desempenho

Onde a turma teve dificuldade, em duas leituras:

- **por habilidade** — "interpretar estruturas de repetição: 50%" diz o
  que ensinar de novo;
- **por questão** — o número, com a habilidade ao lado.

A leitura por número diz *onde* erraram; a por habilidade diz *o quê*, e
é essa que permite intervir. Duas questões da mesma habilidade somam
numa linha só, ainda que estejam em provas diferentes.

Abaixo de 60% de acerto (`AnalisarDesempenhoAction::LIMITE_DE_ATENCAO`)
a linha vai destacada, e a ordenação começa pela que mais precisa de
atenção.

O escopo é o de quem lê: o **professor** vê as habilidades das questões
dele, a **coordenação** vê as do seu Eixo. Os recortes são turma,
bimestre, prova e disciplina, todos na URL.

### Quem errou a habilidade

Cada linha abre aluno a aluno, ordenada de quem mais precisa de ajuda:
a lista por habilidade diz que a turma foi mal em "aplicar
condicionais"; esta diz **em quem**, com o acerto de cada questão.

### Evolução por bimestre

Uma linha por disciplina, com a nota média de cada bimestre. Bimestre
sem avaliação **não vira zero** — a linha se interrompe, porque zero
diria "foram mal" e o que houve foi "não houve prova".

O gráfico é SVG desenhado no servidor, e não uma biblioteca no
navegador: não entra dependência nova, o resultado é conferível pela
mesma suíte (a geometria mora em `GraficoDeEvolucao::coordenadas()`) e a
mesma marcação serve aos dois temas — cada série carrega o tom claro e o
escuro, e o CSS escolhe.

A paleta é categórica de ordem fixa, **nunca ciclada**: além de seis
séries a cauda não vira um tom inventado. Ela foi validada para
daltonismo e contraste contra as duas superfícies. Como o contraste no
tema claro fica abaixo de 3:1, a identidade nunca depende só da cor —
cada linha leva rótulo na ponta e há uma tabela com os mesmos números.

Linhas que terminam próximas teriam os rótulos sobrepostos. Em vez de
empurrá-los e soltá-los das suas linhas, `GraficoDeEvolucao::rotulos()`
os afasta o mínimo e a view traça um fio ligando cada nome à sua ponta.

### Boletim

**Uma página por aluno**, com a nota de cada disciplina e a soma dos
pesos que a produziu. Um documento único com a turma inteira seria mais
simples de gerar e impossível de entregar sem mostrar a nota de um aluno
para o outro.

## O nome da escola não é o nome do sistema

`app.name` nomeia o **software** — é o que aparece na aba do navegador e
no menu. `instituicao.nome` nomeia **a escola**, e é o que sai nos
documentos: folha de prova e boletim.

Os dois estavam misturados, e a prova saía assinada pelo programa. Agora
o padrão é a escola (`INSTITUICAO_NOME` no `.env`), o modelo de prova
pode dizer outro nome, e cada prova pode dizer outro ainda — do mais
geral para o mais específico.

## Claro, escuro ou como o aparelho

O tema é escolha de quem usa, e não a preferência do sistema imposta
pelo CSS: o Tailwind roda em `darkMode: 'selector'` e a classe `.dark`
no `<html>` é que manda. A escolha fica no navegador e vale também na
tela de entrada, onde o seletor também aparece.

Um script no `<head>` aplica a classe **antes da primeira pintura** —
sem ele, a página nasce clara e escurece quando o JavaScript roda, um
lampejo branco a cada recarga. Ele é síncrono de propósito: adiar seria
o mesmo que não fazer.

Em "como o aparelho", mudar a preferência do sistema troca o tema na
hora, sem recarregar.

A classe é reaplicada em `livewire:navigated`. Numa navegação do
Livewire o documento não recarrega: ele chama `replaceHtmlAttributes` e
troca os atributos do `<html>` pelos do documento novo, que vem do
servidor sem a classe. Sem reaplicar, o tema escolhido se perdia na
primeira troca de página.

O que o projeto escreve à mão — a barra de rolagem e o gráfico de
evolução — acompanha a mesma classe, e não uma `@media` própria: duas
fontes de verdade divergiriam no dia em que alguém escolhesse o claro
num aparelho escuro.

## A barra lateral se recolhe

O botão na barra de cima esconde o menu e devolve os 16 rem dele ao
conteúdo — a pré-visualização da prova, as tabelas largas de resultado e
a análise de desempenho são o motivo.

A escolha fica no `localStorage` (`$persist`), então vale para as
próximas visitas. E um véu no `<head>` lê a mesma chave **antes da
primeira pintura**: sem ele, quem deixou a barra escondida a veria
aparecer e sumir a cada recarga. O véu sai em `alpine:initialized`,
porque a partir dali quem posiciona a barra são as classes do Alpine e
as duas regras brigariam.

No celular a barra já é um painel sobreposto, que não ocupa espaço —
lá o botão não aparece.

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
