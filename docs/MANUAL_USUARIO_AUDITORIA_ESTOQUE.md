# Manual do Usuario - Auditoria de Estoque

**Modulo:** Stock Audit | **Sistema:** Mercury
**Versao:** 1.0 | **Data:** 2026-03-15

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Tipos de Auditoria](#2-tipos-de-auditoria)
3. [Fluxo de Trabalho Geral](#3-fluxo-de-trabalho-geral)
4. [Passo a Passo: Criando uma Auditoria](#4-passo-a-passo-criando-uma-auditoria)
5. [Passo a Passo: Autorizacao](#5-passo-a-passo-autorizacao)
6. [Passo a Passo: Contagem](#6-passo-a-passo-contagem)
7. [Passo a Passo: Importacao de Contagem](#7-passo-a-passo-importacao-de-contagem)
8. [Passo a Passo: Conciliacao](#8-passo-a-passo-conciliacao)
9. [Passo a Passo: Justificativas da Loja](#9-passo-a-passo-justificativas-da-loja)
10. [Passo a Passo: Assinatura Digital e Finalizacao](#10-passo-a-passo-assinatura-digital-e-finalizacao)
11. [Relatorios](#11-relatorios)
12. [Dashboard e Mapa de Calor](#12-dashboard-e-mapa-de-calor)
13. [Perguntas Frequentes](#13-perguntas-frequentes)

---

## 1. Visao Geral

O modulo de Auditoria de Estoque permite a contagem fisica dos produtos da loja, comparando os valores contados com o saldo registrado no sistema (ERP Cigam). O objetivo e identificar divergencias (faltas e sobras), justifica-las e gerar relatorios para tomada de decisao.

### Perfis de Acesso

| Perfil | O que pode fazer |
|--------|-----------------|
| **Super Admin (Nivel 1)** | Tudo: criar, autorizar, contar, conciliar, finalizar, cancelar |
| **Admin Regional (Nivel 2)** | Criar, autorizar, conciliar, revisar justificativas, finalizar |
| **Gerente de Loja (Nivel 3)** | Criar, contar, conciliar, enviar justificativas |
| **Operador (Nivel 5)** | Visualizar, contar (se designado como contador) |

---

## 2. Tipos de Auditoria

O sistema suporta 4 tipos de auditoria, cada um com finalidade e comportamento distintos:

### 2.1 Auditoria Total

**Quando usar:** Contagem completa de todo o estoque da loja.

- Todos os produtos com saldo no sistema sao carregados automaticamente
- A 2a contagem e **obrigatoria** (ativada automaticamente)
- A 3a contagem e opcional
- Indicada para auditorias periodicas (trimestral, semestral)
- **Exemplo:** Inventario geral de fim de ano

### 2.2 Auditoria Parcial

**Quando usar:** Contagem de parte do estoque (departamento, categoria, corredor).

- Todos os produtos sao carregados, mas o foco e em areas especificas
- A 2a e 3a contagens sao **opcionais**
- Use areas de contagem para segmentar o trabalho
- **Exemplo:** Contar apenas calcados de uma loja

### 2.3 Auditoria Especifica

**Quando usar:** Contagem de itens pontuais, geralmente apos identificar divergencias ou reclamacoes.

- Focada em SKUs especificos
- A 2a e 3a contagens sao **opcionais**
- Ideal para verificacao rapida de produtos com suspeita de desvio
- **Exemplo:** Verificar 30 produtos que tiveram ajustes no ultimo mes

### 2.4 Auditoria Aleatoria

**Quando usar:** Amostragem inteligente baseada em volume de movimentacao.

- Ao criar, informe a **quantidade de itens** a serem auditados (minimo 10)
- O sistema seleciona automaticamente os N produtos com **maior movimentacao** nos ultimos 90 dias
- Produtos com mais vendas, transferencias e ajustes tem prioridade
- A 2a e 3a contagens sao **opcionais**
- **Exemplo:** Auditar os 200 produtos mais movimentados da loja

---

## 3. Fluxo de Trabalho Geral

O fluxo de uma auditoria segue 6 etapas sequenciais:

```
  ETAPA 1           ETAPA 2              ETAPA 3           ETAPA 4
+-----------+    +--------------+    +--------------+    +---------------+
| Rascunho  | -> | Aguardando   | -> | Em Contagem  | -> | Conciliacao   |
| (Criacao) |    | Autorizacao  |    | (1a, 2a, 3a) |    | (Fases A/B/C) |
+-----------+    +--------------+    +--------------+    +---------------+
                                                                |
                                          ETAPA 5               v            ETAPA 6
                                     +-----------+    +------------------+
                                     | Cancelada |    | Finalizada       |
                                     | (Qualquer |    | (Assinatura +    |
                                     |  momento) |    |  Relatorios)     |
                                     +-----------+    +------------------+
```

| Etapa | Status | Cor | Descricao |
|-------|--------|-----|-----------|
| 1 | Rascunho | Cinza | Auditoria criada, aguardando envio para autorizacao |
| 2 | Aguardando Autorizacao | Amarelo | Gerente ou admin precisa autorizar |
| 3 | Em Contagem | Azul | Contadores realizam as contagens fisicas |
| 4 | Conciliacao | Azul escuro | Comparacao contagem vs sistema + justificativas |
| 5 | Finalizada | Verde | Auditoria concluida, relatorios disponiveis |
| 6 | Cancelada | Vermelho | Auditoria cancelada (somente niveis 1 e 2) |

---

## 4. Passo a Passo: Criando uma Auditoria

### Onde acessar

Menu principal > **Auditoria de Estoque** > Botao **+ Nova Auditoria**

### Preenchendo o formulario

**Campos obrigatorios:**

| Campo | Descricao | Dica |
|-------|-----------|------|
| **Loja** | Selecione a loja a ser auditada | Usuarios de loja so veem a propria loja |
| **Tipo de Auditoria** | Total, Parcial, Especifica ou Aleatoria | Veja secao 2 para escolher |
| **Empresa Auditora** | Empresa terceirizada responsavel | Cadastrada previamente em Fornecedores de Auditoria |

**Campos opcionais:**

| Campo | Descricao |
|-------|-----------|
| **Ciclo de Auditoria** | Cronograma ao qual pertence (Mensal, Trimestral...) |
| **Gerente Responsavel** | Gerente da loja que acompanhara |
| **Estoquista** | Funcionario responsavel pelo estoque |
| **2a Contagem obrigatoria** | Marcado automaticamente para auditorias Totais |
| **3a Contagem obrigatoria** | Marque se deseja uma terceira rodada de contagem |
| **Notas** | Observacoes gerais sobre a auditoria |

**Campo condicional (somente para Aleatoria):**

| Campo | Descricao |
|-------|-----------|
| **Quantidade de Itens** | Numero de produtos a serem selecionados (minimo 10) |

### Salvando

1. Clique em **Criar Auditoria**
2. A auditoria sera criada com status **Rascunho**
3. Voce sera redirecionado para a listagem

---

## 5. Passo a Passo: Autorizacao

### Enviando para autorizacao

1. Na listagem, localize a auditoria com status **Rascunho**
2. Clique no icone de **visualizar** (olho)
3. No modal de detalhes, clique em **Enviar para Autorizacao**
4. O status muda para **Aguardando Autorizacao**

### Autorizando (Admin/Gerente)

1. Localize a auditoria com status **Aguardando Autorizacao**
2. Clique no icone de **visualizar**
3. Revise os dados (loja, tipo, equipe)
4. Clique em **Autorizar Auditoria**
5. O sistema carrega automaticamente o saldo do estoque do ERP Cigam
6. Para auditorias **Aleatorias**: o sistema seleciona os itens com maior movimentacao
7. O status muda para **Em Contagem**

> **Importante:** A partir deste momento, os itens do estoque estao "congelados" como foto do sistema. Qualquer movimentacao posterior nao afeta os valores da auditoria.

---

## 6. Passo a Passo: Contagem

### Acessando a tela de contagem

1. Na listagem, localize a auditoria com status **Em Contagem**
2. Clique no botao **Contar** (icone de clipboard)
3. A tela de contagem sera aberta

### Conhecendo a tela de contagem

A tela possui as seguintes secoes:

```
+----------------------------------------------------------+
| [<- Voltar]   Contagem #123   Loja Z424   [Opcoes v]     |  <- Cabecalho
+----------------------------------------------------------+
| [Itens: 450]  [Unidades: 3.280]  [Referencias: 312]      |  <- Cards resumo
+----------------------------------------------------------+
| [1a Contagem] [2a Contagem] [3a Contagem]  [Finalizar]    |  <- Seletor de rodadas
+----------------------------------------------------------+
| > Areas de Contagem (clique para expandir)                 |  <- Painel de areas
+----------------------------------------------------------+
| Codigo de Barras: [________________]  Qtd: [1]             |  <- Entrada de dados
+----------------------------------------------------------+
| Ultimo item bipado: Tenis Runner Pro - 1 un.               |  <- Feedback visual
+----------------------------------------------------------+
| Filtrar: [________________]                                |  <- Busca
+----------------------------------------------------------+
| SKU | Produto | Barcode | 1a | 2a | 3a | Acoes            |  <- Tabela de itens
+----------------------------------------------------------+
```

### Selecionando a rodada de contagem

- Clique no botao **1a Contagem**, **2a Contagem** ou **3a Contagem**
- A rodada ativa fica destacada em **azul**
- Rodadas finalizadas ficam em **verde** com icone de check
- Rodadas bloqueadas ficam com icone de cadeado (desbloqueiam apos finalizar a rodada anterior)

### Usando areas de contagem

As areas permitem dividir a loja em zonas de contagem (ex: Vitrine, Estoque, Prateleira A).

**Criando areas:**

1. Abra o menu **Opcoes** > **Areas**
2. No modal, informe os nomes das areas (uma por linha)
3. Clique em **Criar Areas**
4. O sistema gera etiquetas com codigo EAN-13 automaticamente

**Designando contadores:**

1. Expanda o painel **Areas de Contagem** (clique na seta)
2. Para cada area, voce vera uma grade de contadores x rodadas
3. Clique no **+** na interseccao desejada (ex: "Joao" na "1a Contagem" da "Area Vitrine")
4. O badge muda para azul com o nome do contador
5. Para remover, clique no **x** do badge

**Imprimindo etiquetas:**

1. Abra o menu **Opcoes** > **Etiquetas**
2. O sistema gera um PDF com etiquetas de codigo de barras para cada area
3. Cole as etiquetas de INICIO e FIM nos limites fisicos de cada area
4. Os contadores bipam a etiqueta para ativar/desativar a area no coletor

### Contagem por leitor de codigo de barras (scanner)

Este e o metodo mais rapido e recomendado:

1. Selecione a **rodada** desejada (1a, 2a ou 3a)
2. Se usar areas, selecione a **area** clicando no card correspondente
3. Posicione o cursor no campo **Codigo de Barras**
4. **Bipe o produto** com o leitor
5. O sistema automaticamente:
   - Identifica o produto pelo codigo de barras
   - Incrementa a quantidade em 1 unidade
   - Exibe o nome do produto na barra de feedback verde
   - Atualiza a tabela de itens
6. Continue bipando os proximos produtos
7. Repita ate completar toda a area/loja

**Ajuste manual de quantidade:**

- Se um produto tem muitas unidades (ex: 50 pares iguais):
  1. Altere o campo **Qtd** para o valor desejado (ex: 50)
  2. Bipe o codigo de barras
  3. O sistema registra a quantidade informada de uma vez
  4. O campo Qtd volta para 1 automaticamente

**Corrigindo uma contagem:**

- Na tabela de itens, clique no icone de **editar** do produto
- Altere a quantidade manualmente
- Confirme a alteracao

### Contagem manual (sem scanner)

Se nao houver leitor de codigo de barras:

1. Digite o codigo de barras no campo de texto
2. Pressione **Enter**
3. O sistema identifica e registra da mesma forma

### Finalizando uma rodada de contagem

Apos contar todos os itens de uma rodada:

1. Clique no botao **Finalizar Rodada** (icone de bandeira)
2. Confirme no modal de confirmacao
3. A rodada sera marcada como **finalizada** (verde com check)
4. Os itens desta rodada nao podem mais ser alterados
5. Se houver 2a contagem, o botao da proxima rodada sera desbloqueado

> **Atencao:** A finalizacao e irreversivel. Certifique-se de que todos os itens foram contados antes de finalizar.

### Limpando uma contagem

Se for necessario refazer uma rodada (antes de finalizar):

1. Abra o menu **Opcoes** > **Limpar**
2. Selecione a rodada e a area (ou todas)
3. Confirme a acao
4. Todos os valores daquela rodada/area serao zerados

---

## 7. Passo a Passo: Importacao de Contagem

A importacao permite carregar contagens a partir de arquivos, util para coletores de dados portateis e planilhas.

### Formatos aceitos

| Formato | Extensoes | Descricao |
|---------|-----------|-----------|
| **Coletor de Dados** | CSV, TXT | Arquivo com uma coluna de codigos de barras bipados sequencialmente |
| **Planilha** | CSV, TXT, XLSX, XLS | Colunas: codigo de barras, quantidade, area (opcional), observacao (opcional) |

Tamanho maximo: **10 MB**

### Formato 1: Coletor de Dados

Arquivo gerado pelo coletor portatil com um codigo de barras por linha:

```
7891234567890
7891234567890
7891234567890
7894561237890
7894561237890
```

Neste exemplo:
- Produto `7891234567890`: 3 unidades (aparece 3 vezes)
- Produto `7894561237890`: 2 unidades (aparece 2 vezes)

**Usando etiquetas de area no coletor:**

```
9999000100100    <- Inicio da Area 1 (etiqueta de inicio)
7891234567890
7891234567890
9999000100109    <- Fim da Area 1 (etiqueta de fim)
9999000200100    <- Inicio da Area 2
7894561237890
9999000200109    <- Fim da Area 2
```

Os codigos que comecam com `9999` sao as etiquetas de area geradas pelo sistema. Ao bipar a etiqueta de inicio, os produtos seguintes sao vinculados aquela area automaticamente.

### Formato 2: Planilha

Arquivo com colunas separadas por ponto e virgula (`;`):

```
codigo_barras;quantidade;area;observacao
7891234567890;3;Vitrine;Prateleira superior
7894561237890;2;Estoque;
7897891234560;1;;Produto danificado
```

**Nomes de colunas aceitos (flexivel):**

| Coluna | Nomes aceitos |
|--------|--------------|
| Codigo de barras | `codigo_barras`, `cod_barras`, `barcode`, `ean`, `sku`, `codigo` e outros |
| Quantidade | `quantidade`, `qtd`, `qty`, `quant`, `count`, `estoque` e outros |
| Area | `area`, `local`, `setor`, `zona`, `regiao` (opcional) |
| Observacao | `observacao`, `obs`, `nota`, `notes` (opcional) |

### Realizando a importacao

1. Abra o menu **Opcoes** > **Importar**
2. No modal de importacao:
   - Selecione a **rodada** (1a, 2a ou 3a)
   - Selecione a **area** (opcional, se aplicavel)
   - Escolha o **modo**:
     - **Somar** (padrao): adiciona as quantidades ao que ja existe
     - **Substituir**: sobrescreve a contagem existente
   - Clique em **Escolher arquivo** e selecione o arquivo
3. Clique em **Importar**
4. Aguarde o processamento (barra de progresso exibida)
5. Ao concluir, o sistema exibe:
   - Total de linhas processadas
   - Linhas importadas com sucesso
   - Linhas com erro (se houver)

### Arquivo de rejeitados

Se houver linhas que nao puderam ser importadas:

- O sistema gera automaticamente um arquivo CSV com os erros
- Um link para download aparece na mensagem de resultado
- O arquivo lista: numero da linha, codigo de barras, motivo do erro, quantidade
- Motivos comuns: codigo de barras nao encontrado, formato invalido, area inexistente

### Baixando templates

No modal de importacao, voce pode baixar modelos de arquivo:

- **Template Planilha**: arquivo CSV com cabecalhos corretos
- **Template Coletor**: arquivo TXT de exemplo com codigos de barras

---

## 8. Passo a Passo: Conciliacao

A conciliacao compara os valores contados com o estoque do sistema e e dividida em 3 fases:

### Iniciando a conciliacao

1. Apos finalizar todas as rodadas de contagem obrigatorias:
   - Abra o menu **Opcoes** > **Conciliar**
   - Confirme no dialogo
2. O sistema transiciona a auditoria para o status **Conciliacao**
3. Voce sera redirecionado para a tela de conciliacao

### Fase A: Conciliacao de Contagens

**Objetivo:** Resolver diferencas entre as rodadas de contagem (1a vs 2a vs 3a).

```
+----------------------------------------------------------+
| Conciliacao #123 - Fase A: Contagens                      |
+----------------------------------------------------------+
| [Itens: 450] [Divergentes: 32] [Resolvidos: 0] [7%]      |  <- Cards
+----------------------------------------------------------+
| [Area 1 (5)] [Area 2 (12)] [Area 3 (15)]                  |  <- Badges por area
+----------------------------------------------------------+
| SKU | Produto | 1a | 2a | 3a | Aceita | Acoes             |  <- Tabela
+----------------------------------------------------------+
```

**Passo a passo:**

1. Revise os itens com divergencia (destacados em vermelho ou azul)
2. Para cada item divergente, voce pode:
   - **Aceitar contagem automatica**: o sistema escolhe o valor mais frequente
   - **Aceitar manualmente**: clique no item, escolha qual contagem aceitar
   - **Informar valor diferente**: insira um valor corrigido
3. Clique em **Auto-aceitar** para resolver automaticamente itens com contagens iguais
4. Acompanhe o progresso pelos cards de resumo
5. Apos resolver todos os itens, clique em **Concluir Conciliacao de Contagens**

**Badges de area:**

- Verde: area sem divergencias
- Vermelho: area com divergencias pendentes
- Amarelo: area com divergencias resolvidas
- Azul: area pendente sem divergencias

Clique em um badge para filtrar os itens daquela area.

### Fase B: Conciliacao de Estoque

**Objetivo:** Comparar a contagem aceita com o saldo do sistema (Cigam).

```
+----------------------------------------------------------+
| Conciliacao #123 - Fase B: Estoque                        |
+----------------------------------------------------------+
| [Acuracidade: 94.2%] [Faltas: R$ 3.450] [Sobras: R$ 890] |
+----------------------------------------------------------+
| SKU | Produto | Sistema | Contagem | Diferenca | Valor    |
+----------------------------------------------------------+
```

**Passo a passo:**

1. Revise os itens com divergencia entre contagem aceita e saldo do sistema
2. Para cada item, voce vera:
   - **Quantidade do sistema** (saldo no Cigam)
   - **Quantidade contada** (aceita na Fase A)
   - **Divergencia** (diferenca em unidades)
   - **Valor** da divergencia (em R$, preco de venda e custo)
3. **Justificar divergencias (auditor):**
   - Clique no item e adicione uma justificativa
   - Marque como **Justificado** se a divergencia tem explicacao valida
   - Exemplos: produto em transito, erro de cadastro, amostra danificada
4. Acompanhe a **acuracidade** (% de itens sem divergencia) nos cards
5. Clique em **Concluir Conciliacao** para encerrar a Fase B

**Filtros disponiveis:**

- **Todos**: lista completa
- **Divergentes**: apenas itens com diferenca
- **Faltas**: itens com contagem menor que o sistema (divergencia negativa)
- **Sobras**: itens com contagem maior que o sistema (divergencia positiva)
- **Justificados**: itens ja justificados pelo auditor
- **Pendentes**: itens sem justificativa

> **Dica:** Use o botao **Ver Movimentacao** em cada item para consultar o historico de vendas, transferencias e ajustes dos ultimos 90 dias. Isso ajuda a entender a causa da divergencia.

---

## 9. Passo a Passo: Justificativas da Loja

Apos a conciliacao (Fase B), a loja pode justificar divergencias que restaram.

### Fase C1: Envio de Justificativas (Loja)

**Quem faz:** Gerente da loja ou equipe responsavel

1. Acesse a auditoria > clique em **Justificativas da Loja**
2. A tela mostra todos os itens com divergencia ainda nao resolvida
3. Para cada item:
   - Clique no botao **Justificar**
   - Escreva a justificativa (texto obrigatorio)
   - Se encontrou parte dos produtos, informe a **quantidade encontrada**
   - Anexe fotos como evidencia (opcional)
   - Clique em **Enviar**
4. Apos justificar todos os itens desejados, clique em **Finalizar Envio**
5. As justificativas sao enviadas para revisao do backoffice

### Fase C2: Revisao de Justificativas (Backoffice)

**Quem faz:** Admin Regional ou Super Admin (niveis 1 e 2)

1. Acesse a auditoria > clique em **Justificativas da Loja**
2. A tela mostra os itens com justificativas enviadas pela loja
3. Para cada justificativa:
   - Leia o texto da justificativa
   - Verifique as fotos anexadas (se houver)
   - Decida: **Aceitar** ou **Rejeitar**
   - Adicione uma nota de revisao (opcional)
4. **Aceitar Divergencias**: botao para aceitar todas as justificativas pendentes de uma vez
5. Apos revisar todos os itens, clique em **Finalizar Auditoria**

### Como funciona a logica de faltas e sobras

A logica de justificativas e **assimetrica** — faltas e sobras funcionam de forma diferente:

**Para FALTAS (contagem < sistema):**

| Situacao | Efeito no Resultado |
|----------|-------------------|
| Justificada pelo auditor (Fase B) | Deduzida do resultado (perda explicada) |
| Aceita pela loja (Fase C) | Deduzida do resultado (perda confirmada) |
| Rejeitada (Fase C) | Permanece como perda no resultado |

Formula: `Resultado Faltas = Bruto - Fase B - Fase C Aceitas`

**Para SOBRAS (contagem > sistema):**

| Situacao | Efeito no Resultado |
|----------|-------------------|
| Justificada pelo auditor (Fase B) | Confirmada (estoque real, precisa ajuste no sistema) |
| Aceita pela loja (Fase C) | Confirmada (estoque real, precisa ajuste no sistema) |
| Rejeitada (Fase C) | Deduzida do resultado (erro de contagem confirmado) |

Formula: `Resultado Sobras = Bruto - Fase C Rejeitadas`

**Resumo:**
- Falta aceita = "ok, realmente sumiu, mas temos explicacao"
- Falta rejeitada = "nao aceito, permanece como perda"
- Sobra aceita = "o produto realmente esta aqui, ajustar sistema"
- Sobra rejeitada = "erro de contagem, nao mexer no sistema"

---

## 10. Passo a Passo: Assinatura Digital e Finalizacao

### Assinando a auditoria

Ao clicar em **Finalizar Auditoria** na Fase C, um modal de assinatura e exibido:

```
+--------------------------------------------+
|   Assinatura Digital - Finalizar Auditoria  |
+--------------------------------------------+
|   Auditoria: #123 | Loja: Z424             |
|                                             |
|   Gerente: [Nome do Gerente]                |
|   +--------------------------------------+  |
|   |                                      |  |
|   |    (Assine aqui com o mouse ou dedo) |  |
|   |                                      |  |
|   +--------------------------------------+  |
|   [Limpar]                                  |
|                                             |
|   Auditor: [Nome do Auditor]                |
|   +--------------------------------------+  |
|   |                                      |  |
|   |    (Assine aqui com o mouse ou dedo) |  |
|   |                                      |  |
|   +--------------------------------------+  |
|   [Limpar]                                  |
+--------------------------------------------+
|   [Cancelar]        [Assinar e Finalizar]   |
+--------------------------------------------+
```

1. O **gerente** assina no primeiro canvas (usando mouse ou toque na tela)
2. O **auditor** assina no segundo canvas
3. Se errar, clique em **Limpar** para refazer
4. Ambas as assinaturas sao **obrigatorias**
5. Clique em **Assinar e Finalizar**

### O que acontece apos finalizar

1. As assinaturas sao armazenadas com registro de IP e navegador
2. O status muda para **Finalizada** (verde)
3. A acuracidade e registrada no historico
4. Um e-mail automatico e enviado para o e-mail da loja com o relatorio PDF anexado
5. A auditoria fica bloqueada para alteracoes

---

## 11. Relatorios

### Tipos de relatorio

| Tipo | Conteudo | Quando usar |
|------|----------|-------------|
| **Completo** | Tudo: resumo, top 10, lista completa, financeiro, equipe, timeline, assinaturas | Relatorio oficial para arquivo |
| **Divergencias** | Apenas itens com diferenca entre contagem e sistema | Analise rapida de problemas |
| **Faltas** | Apenas itens com contagem menor que o sistema | Investigacao de perdas |
| **Sobras** | Apenas itens com contagem maior que o sistema | Investigacao de excessos |

### Gerando um relatorio

1. Na listagem, clique no icone de **visualizar** da auditoria finalizada
2. No modal de detalhes, clique em **Relatorio**
3. Selecione o tipo desejado
4. Clique em **Download PDF** ou **Enviar por E-mail**

### Conteudo do relatorio PDF

O relatorio completo inclui:

- **Cabecalho**: loja, tipo, periodo, equipe
- **Resumo executivo**: acuracidade, total de itens, divergencias
- **Top 10 divergencias**: maiores faltas e sobras em valor
- **Tabela de breakdown financeiro**:

```
| Descricao              | Faltas (R$)    | Sobras (R$)    |
|------------------------|----------------|----------------|
| Divergencias Brutas    | R$ 5.000,00    | R$ 1.200,00    |
| (-) Just. Fase B       | R$ 1.500,00    | Confirmadas    |
| (-) Just. Fase C Aceitas| R$ 800,00     | Confirmadas    |
| (-) Rejeitadas Fase C  | Permanecem     | R$ 300,00      |
| RESULTADO              | R$ 2.700,00    | R$ 900,00      |
| PERDA DE ESTOQUE       | R$ 1.800,00 (Faltas - Sobras)  |
```

- **Lista completa de itens**: com todas as contagens e divergencias
- **Assinaturas digitais**: imagens das assinaturas do gerente e auditor
- **Rodape**: data de geracao, numero de paginas

### Enviando por e-mail

1. No modal de relatorio, clique em **Enviar por E-mail**
2. Informe os enderecos de e-mail (separados por virgula)
3. Clique em **Enviar**
4. O PDF e gerado e enviado como anexo

---

## 12. Dashboard e Mapa de Calor

### Dashboard

**Onde acessar:** Listagem de auditorias > botao **Dashboard** (na toolbar)

O dashboard apresenta uma visao gerencial com:

**6 Cards de indicadores:**

| Card | Descricao |
|------|-----------|
| Total | Numero total de auditorias |
| Ativas | Auditorias em andamento (contagem + conciliacao) |
| Finalizadas | Auditorias concluidas |
| Acuracidade | Media de acuracidade (%) |
| Faltas | Total de perdas em R$ |
| Sobras | Total de sobras em R$ |

**4 Graficos:**

| Grafico | Tipo | Descricao |
|---------|------|-----------|
| Evolucao de Acuracidade | Linha | Historico mensal (selecione uma loja) |
| Ranking de Lojas | Barras horizontal | Acuracidade por loja (verde >=95%, amarelo >=85%, vermelho <85%) |
| Distribuicao por Status | Rosca | Proporacao de auditorias por status |
| Impacto Financeiro | Barras | Faltas vs sobras por loja |

**Filtros:**
- Loja (dropdown)
- Periodo (data inicio e fim)

### Mapa de Calor

**Onde acessar:** Listagem de auditorias > botao **Mapa de Calor** (ou Dashboard > botao **Mapa de Calor**)

O mapa de calor identifica padroes de divergencia com 3 abas:

**Aba 1: Areas**
- Cards coloridos por acuracidade de cada area de contagem
- Verde (>=95%), Ciano (85-94%), Amarelo (70-84%), Vermelho (<70%)
- Mostra: total de itens, divergentes, faltas e sobras (valor + unidades)

**Aba 2: Lojas**
- Mesma visualizacao, agrupada por loja
- Disponivel apenas para usuarios com nivel acima da loja

**Aba 3: Produtos Recorrentes**
- Tabela com os 20 produtos que mais aparecem com divergencia em multiplas auditorias
- Ajuda a identificar problemas sistematicos (furto recorrente, erro de cadastro, etc.)

---

## 13. Perguntas Frequentes

### Criacao e Configuracao

**P: Qual tipo de auditoria devo escolher?**
- **Total**: para inventario completo periodico (recomendado trimestral ou semestral)
- **Parcial**: para contar uma secao da loja (ex: so calcados)
- **Especifica**: para verificar itens pontuais com suspeita de problema
- **Aleatoria**: para amostragem rapida baseada nos produtos mais movimentados

**P: Preciso sempre fazer 2 contagens?**
Para auditorias **Totais**, sim — a 2a contagem e obrigatoria automaticamente. Para os demais tipos, a 2a e 3a contagens sao opcionais, mas recomendadas para maior confiabilidade.

**P: Posso editar uma auditoria depois de criada?**
Sim, enquanto estiver no status **Rascunho**. Apos a autorizacao, os dados do cabecalho sao bloqueados.

### Contagem

**P: O que acontece se eu bipar um codigo que nao existe no sistema?**
O sistema exibe uma mensagem de erro informando que o produto nao foi encontrado. Verifique se o codigo esta correto ou se o produto esta cadastrado.

**P: Posso contar o mesmo produto mais de uma vez?**
Sim. Cada bipagem adiciona 1 unidade ao total. Se bipar o mesmo produto 5 vezes, ele tera quantidade = 5.

**P: Posso usar o celular para contar?**
Sim, a tela de contagem e responsiva e funciona em tablets e smartphones. Voce pode digitar o codigo de barras manualmente ou usar um leitor bluetooth.

**P: Posso importar e bipar na mesma rodada?**
Sim. Use o modo **Somar** na importacao para adicionar as quantidades importadas ao que ja foi bipado.

**P: O que acontece se eu finalizar a rodada e esqueci de contar um produto?**
A finalizacao e **irreversivel**. O produto ficara com quantidade 0 (ou o que foi registrado) naquela rodada. Na conciliacao, a divergencia sera identificada e podera ser justificada.

### Conciliacao

**P: Qual a diferenca entre Fase A e Fase B?**
- **Fase A** compara as rodadas de contagem entre si (1a vs 2a vs 3a)
- **Fase B** compara a contagem aceita com o saldo do sistema (Cigam)

**P: O que e acuracidade?**
E o percentual de itens cuja contagem coincide com o sistema. Acuracidade de 95% significa que 95% dos itens estao com saldo correto.

**P: Posso recalcular os resultados de uma auditoria ja finalizada?**
Sim. No modal de visualizacao, existe o botao **Recalcular Resultados** disponivel para admins. Use caso algum bug tenha afetado os calculos.

### Justificativas

**P: Quem pode enviar justificativas da loja?**
Qualquer usuario com acesso a auditoria (niveis 1 a 5).

**P: Quem pode aceitar ou rejeitar justificativas?**
Apenas usuarios com nivel 1 (Super Admin), 2 (Admin Regional) ou 3 (Gerente).

**P: O que acontece se a loja nao justificar?**
As divergencias permanecem no relatorio como perdas ou sobras nao justificadas. O backoffice pode aceitar as divergencias mesmo sem justificativa usando o botao **Aceitar Divergencias**.

### Relatorios

**P: Quando o e-mail automatico e enviado?**
Ao finalizar a auditoria (com assinatura), o sistema envia automaticamente o relatorio completo em PDF para o e-mail cadastrado da loja.

**P: Posso gerar o relatorio antes de finalizar?**
Nao. Os relatorios so ficam disponiveis apos a finalizacao da auditoria.

---

## Glossario

| Termo | Descricao |
|-------|-----------|
| **Acuracidade** | Percentual de itens sem divergencia entre contagem e sistema |
| **Area de contagem** | Zona fisica da loja dividida para organizar a contagem |
| **Bipagem** | Leitura de codigo de barras com scanner |
| **Cigam** | Sistema ERP que fornece o saldo oficial do estoque |
| **Conciliacao** | Processo de comparar contagens e resolver divergencias |
| **Divergencia** | Diferenca entre quantidade contada e quantidade do sistema |
| **EAN-13** | Codigo de barras padrao de 13 digitos |
| **Falta** | Produto com contagem menor que o sistema (perda) |
| **Justificativa** | Explicacao textual para uma divergencia |
| **Rodada** | Cada ciclo de contagem (1a, 2a ou 3a) |
| **SKU** | Codigo unico do produto no sistema |
| **Sobra** | Produto com contagem maior que o sistema |
| **Snapshot** | Foto do saldo do sistema no momento da autorizacao |

---

**Fim do Manual**

Duvidas ou sugestoes? Entre em contato com a equipe de TI.
