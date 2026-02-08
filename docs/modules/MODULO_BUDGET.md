# Módulo de Orçamentos (Budget) - Documentação Consolidada

**Sistema:** Mercury - Grupo Meia Sola
**Data de Consolidação:** 22 de Dezembro de 2025
**Versão do Documento:** 1.0
**Status do Módulo:** ⚠️ Em Desenvolvimento (70% Completo - 16/12/2025)

---

## 📋 Índice

1. [Visão Geral](#1-visão-geral)
2. [Arquitetura e Técnica](#2-arquitetura-e-técnica)
    - [Estrutura de Banco de Dados](#21-estrutura-de-banco-de-dados)
    - [Sistema de Versionamento](#22-sistema-de-versionamento)
    - [Otimização de Memória](#23-otimização-de-memória)
3. [Controle de Acesso e Permissões](#3-controle-de-acesso-e-permissões)
4. [Funcionalidades](#4-funcionalidades)
    - [Upload de Orçamento](#41-upload-de-orçamento)
    - [Visualização](#42-visualização)
    - [Download](#43-download)
5. [Troubleshooting e Diagnóstico](#5-troubleshooting-e-diagnóstico)

---

## 1. Visão Geral

O módulo de **Orçamentos (Budget)** permite o controle de orçamentos anuais através do upload de arquivos Excel, com sistema robusto de versionamento e controle de acesso por níveis de usuário.

### Estado Atual (Dezembro 2025)
*   ✅ **Upload:** Funcional com suporte a arquivos grandes e versionamento automático.
*   ✅ **Listagem:** Funcional com paginação.
*   ✅ **Visualização:** Detalhes e itens do orçamento disponíveis.
*   ✅ **Download:** Recuperação do arquivo original implementada.
*   ⚠️ **Permissões:** Implementadas no backend e frontend, mas requerem configuração fina.
*   ❌ **Pendências:** Edição/Exclusão, Gráficos avançados, Comparativo entre versões.

---

## 2. Arquitetura e Técnica

### 2.1 Estrutura de Banco de Dados

O módulo utiliza duas tabelas principais para armazenar versões e itens.

**`adms_budgets_uploads`** (Cabeçalho da Versão)
*   Armazena metadados do upload: versão, ano, arquivo físico, status (ativo/inativo) e auditoria.
*   Apenas uma versão por ano/área pode estar ativa (`is_active = 1`).

**`adms_budgets_items`** (Itens do Orçamento)
*   Armazena as linhas detalhadas de cada versão.
*   Colunas para valores mensais (`month_01_value`...`month_12_value`) e total anual calculado.
*   Mapeamento de `management_class` para `adms_store_id` (em desenvolvimento).

### 2.2 Sistema de Versionamento

O sistema utiliza uma lógica unificada para garantir consistência na criação de versões.

| Cenário | Ação do Sistema | Versão Resultante | Regra |
| :--- | :--- | :--- | :--- |
| **Primeiro Upload** | Força tipo "Novo" | `1.0` | Regra 1 |
| **Novo Ano** | Força tipo "Novo" | `1.0` | Regra 2 |
| **Mesmo Ano + Novo** | Incrementa Major | `X+1.0` (ex: 2.0) | Regra 3A |
| **Mesmo Ano + Ajuste** | Incrementa Minor | `X.Y+1` (ex: 1.01) | Regra 3B |

*Nota: Logs específicos (`BUDGET_VERSION_RULE_*`) registram qual regra foi aplicada.*

### 2.3 Otimização de Memória

Para suportar arquivos grandes (>10k linhas), foram implementadas estratégias de otimização na leitura do Excel:

1.  **Leitura Otimizada:** Uso de `setReadDataOnly(true)` e `setReadEmptyCells(false)` no PhpSpreadsheet.
2.  **Sem Cálculo de Fórmulas:** `disableCalculationCache()` evita o recálculo pesado, usando valores cacheados.
3.  **Processamento em Lotes (Chunks):** Inserção no banco feita em lotes de 100 linhas para manter uso de memória constante.
4.  **Garbage Collection:** Execução periódica de `gc_collect_cycles()` e liberação de objetos.

**Resultado:** Capacidade aumentada de ~5.000 para **50.000 linhas**, com redução de 94% no tempo de processamento.

---

## 3. Controle de Acesso e Permissões

O acesso é gerido pelo `BudgetService` e validado em três camadas: Backend (Controllers), Frontend (Views) e JavaScript.

### Níveis de Acesso
*   **Nível 1, 2 e 3 (Super Admin/Admin/Suporte):** Acesso total (Upload, View, Download, Edit, Delete).
*   **Nível 9 (Financeiro):** Leitura e Upload (Upload, View, Download).
*   **Nível 18 (Loja):** Leitura apenas (View, Download - configurável).
*   **Outros:** Acesso negado.

### Implementação Técnica
*   **Service:** `BudgetService::validateModuleAccess()`, `canUpload()`, `canView()`, etc.
*   **Logs:** Tentativas de acesso negado geram logs `WARNING` (`BUDGET_ACCESS_DENIED`).

---

## 4. Funcionalidades

### 4.1 Upload de Orçamento
Permite enviar arquivos `.xlsx` (máx 10MB) com estrutura padronizada.

**Fluxo:**
1.  Validação de arquivo (extensão, tamanho, MIME).
2.  Leitura da planilha e extração do Ano (última coluna do cabeçalho).
3.  Transação de Banco de Dados:
    *   Calcula nova versão.
    *   Desativa versão anterior.
    *   Insere novo upload e itens.
    *   **Rollback automático** em caso de falha em qualquer etapa.

**Mapeamento de Lojas:** O sistema tenta mapear a "Classe Gerencial" para o ID da loja (ex: "L01" -> Loja 01). Itens não mapeados ficam como corporativos/gerais.

### 4.2 Visualização
Modal detalhado (`_view_budget_modal.php`) carregado via AJAX.

*   Exibe metadados do upload (versão, usuário, data).
*   Mostra cards de estatísticas (Total de Itens, Valor Total Anual).
*   Renderiza tabela completa de itens.
*   Requer permissão `view_budget`.

### 4.3 Download
Permite baixar o arquivo Excel original enviado.

*   Botões disponíveis na listagem e no modal de visualização.
*   Acesso restrito via `BudgetService::canDownload()`.
*   Arquivo servido com nome original.

---

## 5. Troubleshooting e Diagnóstico

### Problemas Comuns

| Sintoma | Causa Provável | Solução |
| :--- | :--- | :--- |
| **Erro "Allowed memory size"** | Arquivo muito grande ou memória insuficiente | Verificar `php.ini` ou dividir arquivo. Otimizações já estão ativas. |
| **Upload falha com "Ano inválido"** | Última coluna do cabeçalho não é um ano | Ajustar planilha para ter o ano (ex: 2026) na última coluna (19ª). |
| **Botões de ação não aparecem** | Usuário sem permissão | Verificar `ordem_nivac` do usuário e configurações em `BudgetService`. |
| **Download retorna 404** | Arquivo físico deletado ou movido | Verificar existência do arquivo em `assets/files/budgets/`. |

### Logs Úteis
Verifique `assets/logs/app.log` buscando por tags:
*   `BUDGET_UPLOAD_FAILED`
*   `BUDGET_VERSION_RULE_*`
*   `BUDGET_ACCESS_DENIED`

---

*Para detalhes históricos e análises profundas, consulte os documentos originais na pasta `docs/` listados no índice mestre.*
