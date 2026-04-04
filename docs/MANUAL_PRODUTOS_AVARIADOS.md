# Manual do Usuario - Produtos Avariados e Pes Trocados

**Sistema Mercury - Grupo Meia Sola**
**Versao:** 1.0 | **Data:** Abril/2026

---

## O que e o modulo de Produtos Avariados?

O modulo de **Produtos Avariados e Pes Trocados** permite registrar produtos com defeito ou que vieram com tamanhos trocados entre os pes. O sistema identifica automaticamente correspondencias entre lojas para que produtos com problemas complementares possam ser trocados, reduzindo perdas.

**Exemplo pratico:** A Loja A tem um par de sapatos onde o pe esquerdo veio tamanho 36 e o direito veio 37. A Loja B tem o mesmo sapato, mas ao contrario: esquerdo 37 e direito 36. O sistema encontra essa correspondencia e sugere a transferencia para montar pares corretos.

---

## Como Acessar

No menu lateral, clique em **Produtos Avariados**.

```
+--------------------------------------------------+
|  [Icone sapato] Produtos Avariados               |
+--------------------------------------------------+
```

A tela principal mostra:
- **Cards de estatisticas** - Total, Abertos, Matches, Resolvidos e Taxa de Resolucao
- **Filtros de busca** - Pesquisa por referencia, loja, status, tipo e problema
- **Listagem** - Tabela com todos os registros

---

## Cadastrar um Novo Registro

### Passo 1: Clique em "Novo Registro"

No topo da pagina, clique no botao verde **Novo Registro**.

```
[+ Novo Registro]   [Executar Matching]
```

> **Dica:** No celular, clique no botao "Acoes" para ver as opcoes.

---

### Passo 2: Selecione a Loja

Escolha a loja onde o produto com problema foi encontrado.

> **Nota:** Se voce e usuario de loja, sua loja ja vem selecionada automaticamente.

---

### Passo 3: Busque o Produto pela Referencia

No campo **Referencia do Produto**, comece a digitar a referencia ou o nome do produto. O sistema vai buscar automaticamente e mostrar uma lista de sugestoes.

```
+-------------------------------------------------------+
| Referencia do Produto                                  |
| [A10055...]                                            |
|  +---------------------------------------------------+ |
|  | A1005503800001 - SANDALIA SALTO BAIXO  [sapato] 8 tam.|
|  | A1005504200003 - BOTA CANO MEDIO       [sapato] 6 tam.|
|  +---------------------------------------------------+ |
+-------------------------------------------------------+
```

Clique no produto desejado. O sistema vai preencher automaticamente:
- Nome do produto
- Cor
- Categoria
- Se e calcado ou nao

---

### Passo 4: Selecione os Tamanhos (Calcados)

Para calcados, aparece uma **grade de tamanhos** com duas linhas:

```
+----------+-----+-----+-----+-----+-----+-----+-----+-----+
|    Pe    | 33  | 34  | 35  | 36  | 37  | 38  | 39  | 40  |
+----------+-----+-----+-----+-----+-----+-----+-----+-----+
| Esquerdo | [33]| [34]| [35]|*[36]*| [37]| [38]| [39]| [40]|
+----------+-----+-----+-----+-----+-----+-----+-----+-----+
| Direito  | [33]| [34]|*[35]*| [36]| [37]| [38]| [39]| [40]|
+----------+-----+-----+-----+-----+-----+-----+-----+-----+
```

1. Na linha **Esquerdo**, clique no tamanho que esta no pe esquerdo
2. Na linha **Direito**, clique no tamanho que esta no pe direito

Se os tamanhos forem diferentes, o sistema detecta automaticamente:

```
+--------------------------------------------------------------+
| Pe Esquerdo: 36 | Pe Direito: 35 - Pe trocado detectado!     |
+--------------------------------------------------------------+
```

> **Importante:** Para produtos que nao sao calcados, aparece apenas um campo simples de tamanho.

---

### Passo 5: Marque o Tipo de Problema

Selecione uma ou ambas as opcoes:

- **Pe Trocado** - Os tamanhos dos pes estao invertidos
- **Avariado** - O produto tem defeito fisico (rasgo, mancha, descostura, etc.)

Se marcar **Avariado**, preencha:
- **Tipo de Avaria** (obrigatorio) - Rasgo, Mancha, Descostura, etc.
- **Pe Avariado** (obrigatorio para calcados) - Esquerdo, Direito ou Ambos
- **Descricao da Avaria** (obrigatorio) - Descreva o defeito encontrado

---

### Passo 6: Adicione Fotos (Opcional)

Voce pode anexar ate **5 fotos** do produto com defeito (JPG, PNG ou WebP, maximo 5MB cada).

---

### Passo 7: Salve o Registro

Clique no botao **Salvar Registro**. O sistema vai:
1. Salvar o registro com status **Aberto**
2. Tentar encontrar automaticamente um match com produtos de outras lojas
3. Se encontrar, mudar o status para **Match Encontrado**

---

## Consultar Registros

### Filtros Disponiveis

Use os filtros no topo da listagem para encontrar registros:

| Filtro | Opcoes |
|--------|--------|
| Pesquisa | Referencia ou nome do produto |
| Loja | Todas ou uma loja especifica |
| Status | Aberto, Match Encontrado, Transferencia Solicitada, Resolvido, Cancelado |
| Tipo | Calcados, Bolsas, Carteiras, etc. |
| Problema | Pe Trocado ou Avariado |

Clique em **Buscar** para filtrar ou **Limpar** para remover os filtros.

---

### Acoes na Listagem

Para cada registro na tabela, voce pode:

| Botao | Acao |
|-------|------|
| [olho azul] | Visualizar detalhes do produto |
| [lapis amarelo] | Editar o registro |
| [lupa azul] | Ver matches encontrados (quando houver) |
| [lixeira vermelha] | Cancelar o registro |

> **No celular:** Clique no botao de 3 pontos para ver o menu de acoes.

---

## Entendendo os Status

| Status | Cor | Significado |
|--------|-----|-------------|
| **Aberto** | Azul | Registro cadastrado, aguardando match |
| **Match Encontrado** | Ciano | O sistema encontrou um produto complementar em outra loja |
| **Transferencia Solicitada** | Amarelo | A transferencia entre lojas foi aceita |
| **Resolvido** | Verde | A transferencia foi concluida com sucesso |
| **Cancelado** | Vermelho | Registro cancelado manualmente |

---

## Motor de Matching

### O que e o Matching?

O matching e o processo automatico que busca **correspondencias** entre produtos avariados/trocados de lojas diferentes. O sistema identifica dois tipos de match:

**1. Pes Trocados Complementares:**
A Loja A tem esquerdo 36 / direito 37 e a Loja B tem esquerdo 37 / direito 36. Transferindo os pes corretos, ambas as lojas ficam com pares perfeitos.

**2. Avariados Complementares:**
A Loja A tem o pe esquerdo danificado e a Loja B tem o pe direito danificado do mesmo produto. Combinando o pe bom de cada par, monta-se um par perfeito.

---

### Executar Matching

Clique no botao **Executar Matching** no topo da pagina.

```
[+ Novo Registro]   [Executar Matching]
```

> **Nota:** Este botao so aparece para administradores (niveis 1, 2 e 3).

O sistema vai varrer todos os registros abertos e encontrar correspondencias automaticamente. Ao finalizar, mostra quantos matches foram encontrados.

> **Importante:** O matching tambem e executado automaticamente ao cadastrar um novo produto. Se houver uma correspondencia, ela e detectada na hora.

---

### Regras do Matching

O matching respeita as seguintes regras:

1. **Mesma referencia** - Os produtos devem ser o mesmo modelo
2. **Lojas diferentes** - O match so ocorre entre lojas distintas
3. **Produto aberto** - So produtos com status "Aberto" participam
4. **Compatibilidade de marca/rede** - Lojas de franquia so recebem produtos da sua marca

**Regras de marca por rede:**

| Rede | Marcas Aceitas |
|------|---------------|
| Arezzo | Arezzo, Brizza |
| Anacapri | Anacapri |
| Schutz | Schutz |
| Brizza | Brizza |
| Meia Sola, MS Off, E-Commerce, CD | Qualquer marca |

> **Nota:** Estas regras sao configuradas pelo administrador e podem ser alteradas sem necessidade de atualizar o sistema.

---

## Aceitar ou Rejeitar um Match

### Visualizar Matches

Quando um produto tem matches, aparece um badge azul na coluna **Matches** da listagem. Clique no botao de lupa para abrir o modal de matches.

O modal mostra para cada match:
- **Produto parceiro** - Referencia e loja
- **Tabela comparativa** - Tamanhos de cada pe por loja
- **Sugestao de transferencia** - Loja de origem e destino

---

### Aceitar um Match

1. Clique no botao verde **Aceitar e Transferir**
2. O sistema exibe um **aviso importante**:

```
+---------------------------------------------------------------+
|  ATENCAO: Para prosseguir e necessario que a transferencia    |
|  ja tenha sido realizada no sistema (fisica e sistemica)       |
|  de Arezzo 408 para Arezzo Riomar.                            |
|  O numero da Nota Fiscal sera solicitado na proxima etapa.    |
|                                                                |
|           [Voltar]  [Transferencia ja realizada]               |
+---------------------------------------------------------------+
```

3. Apos realizar a transferencia no sistema, clique em **Transferencia ja realizada**
4. Informe o **numero da Nota Fiscal** no campo que aparece
5. Clique em **Confirmar**

O sistema vai:
- Criar uma transferencia automatica no modulo de Transferencias (tipo: Match)
- Mudar o status dos produtos para **Transferencia Solicitada**
- Quando a transferencia for confirmada no sistema, o status muda automaticamente para **Resolvido**

> **Nota para usuarios de loja:** Voce so pode aceitar matches onde sua loja e a origem ou o destino da transferencia.

---

### Rejeitar um Match

1. Clique no botao vermelho **Rejeitar**
2. Informe o **motivo da rejeicao** (obrigatorio)
3. Clique em **Confirmar Rejeicao**

Ao rejeitar:
- Os produtos voltam para o status **Aberto**
- Na proxima execucao do matching, o mesmo par pode ser sugerido novamente
- O motivo da rejeicao fica registrado no historico

> **Nota para usuarios de loja:** Voce so pode rejeitar matches onde sua loja participa.

---

## Editar um Registro

1. Na listagem, clique no botao amarelo de lapis
2. O modal de edicao permite alterar:
   - **Status** do registro
   - **Tipo de problema** (Pe Trocado / Avariado)
   - **Tamanhos** (grade de tamanhos para calcados)
   - **Detalhes da avaria** (tipo, pe, descricao)
   - **Fotos** (adicionar novas)
   - **Observacoes**

> **Nota:** O status tambem pode ser alterado manualmente. Ao marcar como **Resolvido**, o produto parceiro (se houver match aceito) tambem sera resolvido automaticamente.

---

## Cancelar um Registro

1. Na listagem, clique no botao vermelho de lixeira
2. Confirme a exclusao no modal

Ao cancelar:
- O registro muda para status **Cancelado**
- Todos os matches pendentes associados sao rejeitados
- O registro nao pode mais ser editado

> **Importante:** O cancelamento nao pode ser desfeito.

---

## Validacoes e Restricoes

| Regra | Descricao |
|-------|-----------|
| Duplicidade | Nao e possivel cadastrar o mesmo produto na mesma loja se ja existe um registro aberto |
| Pe avariado | Para calcados avariados, e obrigatorio informar qual pe esta danificado |
| Tipo de avaria | Para produtos avariados, o tipo de avaria e obrigatorio |
| Nota Fiscal | Para aceitar um match, o numero da NF e obrigatorio |
| Justificativa | Para rejeitar um match, o motivo e obrigatorio |
| Permissao de loja | Usuarios de loja so podem aceitar/rejeitar matches da propria loja |

---

## Cards de Estatisticas

No topo da pagina, 5 cards mostram os numeros do modulo:

| Card | Descricao |
|------|-----------|
| **Total** | Quantidade total de registros |
| **Abertos** | Registros aguardando match |
| **Matches** | Registros com correspondencia encontrada |
| **Resolvidos** | Registros concluidos com sucesso |
| **Resolucao** | Percentual de resolucao (resolvidos / total) |

> **Dica:** Os cards atualizam automaticamente ao filtrar a busca.

---

## Fluxo Completo Resumido

```
1. Cadastro do produto avariado/trocado
          |
2. Motor de matching busca correspondencias
          |
     [Match encontrado?]
      /           \
    SIM           NAO
     |              |
3. Avaliar       Aguardar
   sugestao      proximo matching
     |
  [Aceitar?]
   /       \
 SIM       NAO
  |          |
4. Informar  Justificar
   NF        rejeicao
   |          |
5. Transferencia    Volta para
   criada           "Aberto"
   |
6. Confirmar transferencia
   no modulo Transferencias
   |
7. Status muda para
   "Resolvido" automaticamente
```

---

## Duvidas Frequentes

**O matching pode ser executado varias vezes?**
Sim. A cada execucao, o sistema busca novas correspondencias entre os registros abertos.

**Se eu rejeitar um match, ele pode ser sugerido novamente?**
Sim. Na proxima execucao do matching, o mesmo par pode ser sugerido. Assim voce pode reavaliar a decisao.

**O que acontece se tres lojas tiverem o mesmo produto com pe trocado?**
O sistema faz matching 1:1. O primeiro par identificado recebe a sugestao. O terceiro produto fica aguardando uma nova correspondencia de outra loja.

**Posso cadastrar o mesmo produto novamente?**
So apos resolver ou cancelar o registro existente. O sistema bloqueia duplicidades para evitar confusao.

**Quem pode executar o matching?**
Apenas administradores (niveis de acesso 1, 2 e 3). Porem, o matching automatico e executado a cada novo cadastro.

---

**Sistema Mercury - Grupo Meia Sola**
**Modulo Produtos Avariados e Pes Trocados v1.0**
**Abril/2026**
