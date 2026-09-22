# Convenções deste projeto

## Toda função nova nasce com teste

Sem exceção: action, componente Livewire, método de domínio, Policy, regra
de validação. Um recurso sem teste não está entregue.

### Telas: teste com dados, e com mais de uma linha

Um teste de renderização com cenário vazio não prova quase nada — `@foreach`
e `@forelse` populados nunca executam, e é justamente dentro deles que as
relações são acessadas.

Pior: o Laravel só marca os models com `preventsLazyLoading` quando a
consulta devolve **mais de um** registro (`Builder::hydrate`). Um teste com
uma linha só passa enquanto a tela de quem tem duas devolve 500.

Por isso:

- toda coleção exibida numa tela é testada com **no mínimo dois registros**;
- todo teste de Feature chama `proibirLazyLoading()` (aplicado
  automaticamente em `tests/Pest.php`), que marca **todos** os models
  hidratados, inclusive os que vêm sozinhos;
- ao corrigir um bug de renderização, o teste é escrito **antes** e
  verificado revertendo a correção — se ele passa com o bug de volta, ele
  não serve.

Os cenários completos ficam em `tests/Feature/Interface/RenderizacaoComDadosTest.php`.

### Dados de teste determinísticos

Um `assertDontSee` só vale se o texto procurado for exclusivo do registro
que deve estar ausente. Factories que sorteiam de uma lista fixa fazem dois
registros colidirem de vez em quando e o teste falha uma vez a cada tantas
execuções.

- nomes, códigos e e-mails usados em asserções são fixados no teste ou
  nascem únicos na factory;
- lembre que a busca costuma cobrir mais de uma coluna: um termo que casa
  com o e-mail derruba um teste que só pensava no nome;
- antes de fechar uma etapa, a suíte roda várias vezes seguidas.

### Escopo e autorização

Todo recurso novo escopado por Eixo precisa de teste para:

1. professor recebe 403;
2. PAEET de outro Eixo recebe 403 por id na URL (IDOR);
3. a listagem não vaza registros de fora do escopo.

## Autorização x regra de negócio

Policies respondem apenas **"este recurso é seu?"** → 403.

**"Dá para fazer isto agora?"** — turma no último ano, grade sem
disciplinas, identificação em conflito — é decidido nas Actions, que lançam
`RegraDeNegocioException` com mensagem explicativa. Misturar as duas coisas
transforma um "ainda não dá" em 403 mudo, e a interface some com o botão
sem dizer por quê.

Quando a ação não cabe no estado atual, a Policy segue liberando e a **view**
esconde o botão (`@can(...)` + `@if ($modelo->podeAlgo())`).

## Componentes Blade e diretivas

Nunca use `@disabled(...)`, `@checked(...)` ou `@readonly(...)` **dentro da
tag de um componente** (`<x-input @disabled(...)>`): o Blade não compila a
tag, ela fica literal no HTML e o campo desaparece da página sem erro
nenhum. O componente declara uma prop (`:desabilitado="$expr"`) e aplica a
diretiva no elemento HTML lá dentro.

Pelo mesmo motivo, `:atributo="$php"` só é interpretado em componentes. Num
`<button>` ou `<input>` puro ele vira uma diretiva do Alpine.

E `:disabled="$expr"` num componente **não resolve**: um valor falso ainda
é renderizado como atributo e desabilita o elemento para sempre. Use a prop
`desabilitado`, que `x-input`, `x-select`, `x-area-texto` e `x-botao`
aceitam.

O teste que pega isso conta os elementos de formulário no HTML renderizado
(`tests/Feature/Avaliacao/CamposDaQuestaoTest.php`). Um `assertSee` de
rótulo passa mesmo com a tag do componente literal.

Ao afirmar que um campo está desabilitado, procure o atributo booleano
(` disabled` seguido de espaço ou `>`), nunca a palavra solta: as classes
utilitárias `disabled:bg-...` estão presentes sempre.

## Eager loading

`Model::shouldBeStrict()` está ativo fora de produção. Regras:

- toda relação que a view acessa entra no `with()` do componente;
- selects parciais (`with('curso:id,nome')`) precisam incluir as chaves
  estrangeiras que as Policies percorrem — em produção a falta delas não
  quebra, vira N+1 silencioso;
- `AplicaEscopoDeEixo::eixoId()` lê a chave estrangeira do último trecho do
  caminho em vez de carregar o Eixo, então basta `eixo_id` no select.

## Quando a interface bloqueia algo, ela diz o quê

Um botão desabilitado sem explicação vira um chamado de suporte. Toda
restrição na tela vem acompanhada do motivo, no nível do item:

- não "faltam 3 questões", e sim "Questão 2: marque qual alternativa é a
  correta";
- o próprio botão bloqueado carrega a explicação no `title`;
- o estado de cada item aparece junto dele ("Pronta para enviar" /
  "2 pendência(s)").

As razões vêm do domínio (`Questao::pendencias()`), não de texto solto na
view, e por isso são testáveis.

Vale para os controles também: se a ação é escolher uma opção entre várias,
use um `radio` de verdade. Um círculo clicável que "parece" um enfeite faz
o usuário travar sem saber que precisa clicar.

## Avisos ao usuário

Ação que **fica na mesma tela** notifica por evento:
`$this->notificarSucesso(...)` (trait `App\Livewire\Concerns\Notifica`).
Um `session()->flash()` aqui só apareceria na próxima navegação — ou seja,
nunca, do ponto de vista de quem clicou.

Ação que **redireciona** usa `flashSucesso()`, que sobrevive à navegação.

Os dois caminhos desembocam no mesmo painel flutuante
(`x-notificacoes`, montado no layout). Testes usam
`assertDispatched('notificar', ...)`.

## Histórico nunca é reescrito

`turma_historicos`, `aluno_historicos` e `questao_feedbacks` são append-only:
sem `updated_at`, sem update, sem delete. Cada mudança grava o estado que
existia **antes** dela, dentro da mesma transação.

Grade em uso não se edita: cria-se a próxima versão.

## Nomes

Domínio em português (tabelas, colunas, models, métodos, rotas). Código do
framework em inglês, como o framework espera.
