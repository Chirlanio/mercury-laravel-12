# Guia do Usuário: Módulo de Ajuste de Estoque

**Data:** 06 de Novembro de 2025
**Versão:** 1.0

## 1. Introdução

O módulo de Ajuste de Estoque é a ferramenta utilizada para corrigir manualmente as quantidades de produtos no sistema. Ele permite registrar entradas (aumentos) e saídas (baixas) de estoque que não estão diretamente ligadas a uma venda ou transferência, como em casos de avarias, perdas, doações ou correções após contagem de inventário.

Este guia irá orientá-lo sobre como utilizar todas as funcionalidades deste módulo.

---

## 2. Tela Principal e Listagem

Ao acessar o módulo, você verá a tela principal com a lista de todos os ajustes de estoque já realizados. 

`[Imagem: Tela principal do módulo de Ajuste de Estoque, mostrando a lista e os filtros]`

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

`[Imagem: Janela modal de "Adicionar Ajuste" com o formulário em branco]`

### Passo 2: Preencher os Dados do Cabeçalho

Estes são os campos principais que identificam o ajuste:

*   **Loja:** Selecione a loja onde o ajuste está sendo realizado. **A seleção da loja é obrigatória** e irá carregar a lista de funcionários correspondentes.
*   **Responsável:** Selecione o nome do colaborador responsável pela realização do ajuste.
*   **Tipo de Movimentação:** Escolha se o ajuste é uma **Entrada** (para aumentar a quantidade de um produto no estoque) ou uma **Saída** (para diminuir a quantidade).
*   **Motivo:** Descreva brevemente o motivo do ajuste (ex: "Avaria no transporte", "Perda por vencimento", "Doação", "Acerto de inventário").
*   **Observação:** Um campo livre para adicionar qualquer detalhe ou informação extra que seja relevante para este ajuste.

### Passo 3: Adicionar Produtos ao Ajuste

Na seção "Itens do Ajuste", você irá adicionar os produtos que terão seu estoque corrigido.

`[Imagem: Seção de "Itens do Ajuste" com a busca de produtos e o campo de quantidade]`

1.  **Buscar Produto:** Comece a digitar o código ou o nome do produto no campo de busca. O sistema irá mostrar uma lista de produtos correspondentes. Selecione o produto desejado.
2.  **Informar a Quantidade:** No campo "Quantidade", digite o número de unidades que você está ajustando (aumentando ou diminuindo).
3.  **Adicionar à Lista:** Clique no botão **"Adicionar"** (geralmente um ícone de `+`). O produto será adicionado à lista de itens do ajuste.
4.  Repita o processo para todos os produtos que fazem parte deste ajuste.

### Passo 4: Salvar o Ajuste

Após preencher todos os dados e adicionar todos os produtos, revise as informações e clique no botão **"Salvar"** no final do formulário. O sistema processará o ajuste e, se tudo estiver correto, ele aparecerá na lista principal.

---

## 4. Como Editar um Ajuste

É possível editar um ajuste enquanto ele estiver com um status que permita a edição (geralmente "Pendente" ou "Em Aberto").

1.  Na lista principal, encontre o ajuste que deseja modificar.
2.  Clique no botão **Editar** (geralmente um ícone de lápis) na linha correspondente.
3.  A janela de edição se abrirá, com todos os dados do ajuste já preenchidos.
4.  Faça as alterações necessárias nos campos do cabeçalho ou na lista de produtos (você pode alterar quantidades ou remover itens).
5.  Clique em **"Salvar"** para confirmar as alterações.

`[Imagem: Janela modal de "Editar Ajuste" com os campos preenchidos]`

---

## 5. Dicionário de Campos

| Campo | Descrição Detalhada |
|---|---|
| **Loja** | A unidade de negócio (loja física ou centro de distribuição) cujo estoque será afetado. |
| **Responsável** | O colaborador que está executando ou que é o responsável pela veracidade do ajuste. |
| **Tipo de Movimentação** | Define a natureza do ajuste: **Entrada** soma a quantidade ao estoque atual; **Saída** subtrai a quantidade. |
| **Motivo** | Campo de texto curto para categorizar o ajuste. Essencial para relatórios futuros. |
| **Observação** | Campo de texto longo para fornecer contexto adicional, como números de lote, detalhes da avaria, etc. |
| **Produto (Item)** | O item específico que está sendo ajustado. |
| **Quantidade (Item)** | O número de unidades do produto a ser adicionado ou removido do estoque. |
| **Status** | Indica a etapa atual do ajuste no processo (veja a seção abaixo). |

---

## 6. Entendendo os Status do Ajuste

Um ajuste pode passar por diferentes status, dependendo do fluxo de aprovação da empresa.

*   **Pendente / Em Aberto:** O ajuste foi criado, mas ainda não foi processado ou aprovado. Geralmente, ainda pode ser editado.
*   **Aprovado / Concluído:** O ajuste foi validado e as alterações no estoque foram efetivadas no sistema.
*   **Rejeitado / Cancelado:** O ajuste foi analisado e não foi aprovado. Nenhuma alteração de estoque é realizada.
