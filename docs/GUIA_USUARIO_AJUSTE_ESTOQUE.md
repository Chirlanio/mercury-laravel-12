# Guia do Usuário: Módulo de Ajuste de Estoque

**Data:** 06 de Novembro de 2025
**Última Atualização:** 11 de Fevereiro de 2026
**Versão:** 2.1

## 1. Introdução

O módulo de Ajuste de Estoque é a ferramenta utilizada para corrigir manualmente as quantidades de produtos no sistema. Ele permite registrar entradas (aumentos) e saídas (baixas) de estoque que não estão diretamente ligadas a uma venda ou transferência, como em casos de avarias, perdas, doações ou correções após contagem de inventário.

Este guia irá orientá-lo sobre como utilizar todas as funcionalidades deste módulo.

---

## 2. Tela Principal e Listagem

Ao acessar o módulo, você verá a tela principal com a lista de todos os ajustes de estoque já realizados.

Nesta tela, você pode:

*   **Visualizar** os ajustes existentes.
*   **Buscar e Filtrar** por situação, loja ou data.
*   **Iniciar o cadastro** de um novo ajuste clicando no botão **"Adicionar Ajuste"**.
*   **Editar ou Excluir** um ajuste existente (dependendo das suas permissões).

---

## 3. Como Criar um Novo Ajuste de Estoque

Siga os passos abaixo para registrar uma nova correção de estoque.

### Passo 1: Abrir o Formulário de Cadastro

Na tela principal, clique no botão verde **"Adicionar Ajuste"**. Uma janela (modal) se abrirá com o formulário para o novo ajuste.

### Passo 2: Preencher os Dados do Cabeçalho

Estes são os campos principais que identificam o ajuste:

*   **Loja:** Selecione a loja onde o ajuste está sendo realizado. **A seleção da loja é obrigatória** e irá carregar a lista de consultores correspondentes.
*   **Consultor(a):** Selecione o nome do colaborador responsável pela solicitação.
*   **Cliente:** Nome completo do cliente relacionado ao ajuste.
*   **Observação:** Campo opcional para adicionar detalhes ou informações extras relevantes sobre o ajuste.

### Passo 3: Adicionar Produtos ao Ajuste

Na seção **"Produtos para Ajuste"**, você irá adicionar os produtos que terão seu estoque corrigido.

#### Busca com Autocomplete

1.  **Digitar no campo de busca:** Comece a digitar a **referência**, o **código de barras** ou a **descrição** do produto no campo de busca. Ao digitar pelo menos 2 caracteres, o sistema exibirá automaticamente uma lista suspensa com sugestões de produtos encontrados no cadastro.

2.  **Selecionar da lista:** Clique no produto desejado na lista de sugestões. O sistema carregará automaticamente as informações do produto, incluindo:
    *   **Foto** do produto
    *   **Descrição** completa
    *   **Tamanhos disponíveis** (grade completa)

3.  **Busca direta:** Alternativamente, digite a referência completa e clique no botão **"Buscar Produto"** ou pressione **Enter** para uma busca direta.

#### Campos de busca suportados

| Campo | Tipo de busca | Exemplo |
|-------|---------------|---------|
| **Referência** | Aproximada (LIKE) | `A139` encontra `A1398800070003` |
| **Código de barras** | Exata | `7891234567890` |
| **Referência auxiliar** | Exata | Código auxiliar do fornecedor |

#### Preenchendo as Quantidades

Após o produto ser carregado:

1.  **Marque os tamanhos** que precisam de ajuste usando os checkboxes.
2.  **Informe a quantidade** desejada para cada tamanho marcado.
3.  O sistema exibirá o **estoque real** da loja selecionada (consultado no CIGAM) ao lado de cada tamanho, no formato `Est: X`.

> **Nota:** O estoque exibido é o estoque real da loja selecionada no momento da busca. Se o sistema CIGAM estiver temporariamente indisponível, o estoque será exibido como `Est: 0` sem impedir o cadastro.

4.  Repita o processo para todos os produtos que fazem parte deste ajuste.

### Passo 4: Salvar o Ajuste

Após preencher todos os dados e adicionar todos os produtos, revise as informações e clique no botão **"Salvar Solicitação"** no final do formulário. O sistema validará os produtos no cadastro e, se tudo estiver correto, o ajuste aparecerá na lista principal.

> **Importante:** Somente produtos cadastrados no sistema são aceitos. Se um produto não for encontrado, será exibida uma mensagem de erro indicando a referência inválida.

---

## 4. Como Editar um Ajuste

É possível editar um ajuste enquanto ele estiver com um status que permita a edição (geralmente "Pendente" ou "Em Aberto").

1.  Na lista principal, encontre o ajuste que deseja modificar.
2.  Clique no botão **Editar** (ícone de lápis) na linha correspondente.
3.  A janela de edição se abrirá, com todos os dados do ajuste já preenchidos.
4.  Faça as alterações necessárias nos campos do cabeçalho ou na lista de produtos (você pode alterar quantidades, marcar/desmarcar tamanhos ou remover produtos).
5.  Clique em **"Atualizar Solicitação"** para confirmar as alterações.

### Restrição para Adicionar Novos Produtos

> **Importante:** O botão **"Adicionar Produto"** só é exibido quando o ajuste está com o status **"Pendente"**. Se o status for alterado para "Aprovado", "Rejeitado" ou qualquer outro, o botão será automaticamente ocultado e a seção de busca de produtos será fechada. Isso garante que novos itens só possam ser adicionados a ajustes que ainda não foram processados.

### Informações do Produto na Edição

Na tela de edição, cada produto exibe:

*   **Descrição do produto** (carregada do cadastro local)
*   **Referência**
*   **Coleção** (badge azul) e **Estação** (badge cinza), quando disponíveis
*   **Tamanhos** com checkboxes, quantidades e estoque

---

## 5. Como Visualizar um Ajuste

Para ver os detalhes completos de um ajuste:

1.  Na lista principal, clique no botão **Visualizar** (ícone de olho).
2.  O modal de visualização exibirá:
    *   **Informações do ajuste** (loja, consultor, cliente, status, datas)
    *   **Resumo de ajustes** (total de itens, marcados, com diferença)
    *   **Lista de produtos** com:
        *   Foto, descrição e referência
        *   **Coleção** e **Estação** (badges informativos)
        *   Tamanhos com quantidades, estoque e diferenças
    *   **Observações** (quando preenchidas)

---

## 6. Dicionário de Campos

| Campo | Descrição Detalhada |
|---|---|
| **Loja** | A unidade de negócio (loja física) cujo estoque será afetado. |
| **Consultor(a)** | O colaborador responsável pela solicitação do ajuste. |
| **Cliente** | Nome completo do cliente relacionado ao ajuste. |
| **Observação** | Campo opcional para fornecer contexto adicional. |
| **Produto (Referência)** | Identificador do produto no sistema. Pode ser buscado por referência, código de barras ou referência auxiliar. |
| **Descrição** | Nome/descrição completa do produto, carregada automaticamente do cadastro. |
| **Coleção** | Coleção à qual o produto pertence (ex: "Verão 2026"). |
| **Estação** | Subcoleção/estação do produto (ex: "Alto Verão"). |
| **Tamanho** | Grade de tamanhos do produto (38, 39, 40... ou UN para tamanho único). |
| **Quantidade** | Quantidade desejada após o ajuste. |
| **Estoque** | Quantidade real em estoque na loja selecionada, consultado no sistema CIGAM (informativo). |
| **Diferença** | Cálculo automático: Quantidade - Estoque. Positivo (verde) ou negativo (vermelho). |
| **Status** | Indica a etapa atual do ajuste no processo (veja a seção abaixo). |

---

## 7. Entendendo os Status do Ajuste

Um ajuste pode passar por diferentes status, dependendo do fluxo de aprovação da empresa.

*   **Pendente / Em Aberto:** O ajuste foi criado, mas ainda não foi processado ou aprovado. Ainda pode ser editado e **novos produtos podem ser adicionados**.
*   **Aprovado / Concluído:** O ajuste foi validado e as alterações no estoque foram efetivadas no sistema. Não é possível adicionar novos produtos.
*   **Rejeitado / Cancelado:** O ajuste foi analisado e não foi aprovado. Nenhuma alteração de estoque é realizada. Não é possível adicionar novos produtos.
