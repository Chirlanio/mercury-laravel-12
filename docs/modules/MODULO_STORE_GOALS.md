# Análise Aprofundada e Plano de Refatoração - Módulo de Metas (StoreGoals)

**Data:** 06 de Novembro de 2025
**Autor:** Gemini
**Versão:** 3.0 (Refatoração Orientada a Serviços e SOLID)

## 1. Visão Geral

Esta análise revisita o módulo `StoreGoals`, cujo estado atual viola severamente os padrões de desenvolvimento, arquitetura e SOLID estabelecidos para o projeto Mercury. A implementação é fragmentada em múltiplos controllers, mistura padrões de interação (AJAX com recargas de página) e ignora o uso de serviços essenciais como `LoggerService` e `NotificationService`.

O plano de refatoração proposto visa modernizar o módulo, alinhando-o completamente aos guias do projeto, resultando em um código coeso, manutenível, testável e seguro.

| Categoria | Status Atual | Objetivo da Refatoração |
|---|---|---|
| **Arquitetura** | Múltiplos Controllers, violação de SRP e DIP | Controller Único, Arquitetura de Serviço-Repositório |
| **Padrão de Código** | Inconsistente, acoplado, sem logs/notificações | Padrão unificado (AJAX), Injeção de Dependência |
| **UX (Experiência)** | Desconexa, lenta, com recargas constantes | Rápida, fluida e moderna (Single Page Application feel) |
| **Manutenibilidade** | Muito Baixa (código espalhado e duplicado) | Alta (código centralizado, desacoplado e previsível) |
| **Testabilidade** | Nula (impossível testar unitariamente) | Alta (com DI, Serviços e Repositórios) |
| **Serviços** | Não utiliza `LoggerService` nem `NotificationService` | Uso obrigatório dos serviços padronizados |

---

## 2. Análise Crítica do `StoreGoals.php` Atual

O controller `StoreGoals.php` exemplifica os problemas do módulo:

1.  **Violação do Princípio da Responsabilidade Única (SRP):**
    *   O método `list()` contém lógica para decidir qual ação tomar com base em `$_GET['typegoals']`. Um método de controller deve ter uma única responsabilidade (ex: listar, buscar), não atuar como um roteador interno.
    *   Ele instancia diretamente múltiplos Models (`AdmsBotao`, `AdmsMenu`, `AdmsAddStoreGoals`, etc.), acoplando o Controller a implementações concretas de acesso a dados.

2.  **Violação do Princípio da Inversão de Dependência (DIP):**
    *   As dependências (Models) são instanciadas com `new`. O Controller controla suas dependências, em vez de recebê-las via injeção, o que impede a testabilidade e a flexibilidade.

3.  **Duplicação de Código:**
    *   Os métodos `listStoreGoalsPriv()` e `searchStoreGoalPriv()` contêm código quase idêntico para carregar a view (`new \Core\ConfigView(...)` e `$loadView->renderList()`).

4.  **Falta de Serviços Essenciais:**
    *   Não há uso do `LoggerService` para registrar quem está listando ou buscando metas.
    *   Não há uso do `NotificationService` para exibir mensagens.

5.  **Inconsistência de Padrão:**
    *   O método `list()` às vezes carrega uma view inicial (`loadStoreGoals`) e outras vezes renderiza uma lista parcial (`listStoreGoals`), contribuindo para a complexa e ineficiente mistura de recargas de página e AJAX (em outros controllers).

---

## 3. Plano de Refatoração Estratégico

A refatoração será baseada em 3 pilares: **Unificação**, **Desacoplamento** e **Padronização**.

### Passo 1: Unificar Controllers em `StoreGoalsController`

Todos os controllers (`StoreGoals`, `AddStoreGoals`, `ViewStoreGoals`, `EditStoreGoal`, `DeleteStoreGoal`) serão unificados em um único `StoreGoalsController.php`. Este controller seguirá um padrão de recurso (resource controller) e será totalmente orientado a AJAX.

**Estrutura do Novo `StoreGoalsController.php`:**

```php
<?php

namespace App\adms\Controllers;

use App\adms\Services\StoreGoalsService;
use App\adms\Services\LoggerService;
// Outros \'use\' necessários

class StoreGoalsController
{
    private StoreGoalsService $storeGoalsService;
    private LoggerService $logger;

    public function __construct(StoreGoalsService $storeGoalsService, LoggerService $logger)
    {
        $this->storeGoalsService = $storeGoalsService;
        $this->logger = $logger;
    }

    // Carrega a view principal (casca) e o formulário de busca
    public function index(): void
    {
        // ... Lógica para carregar a view inicial com filtros ...
    }

    // Responde a requisições AJAX para listar/buscar metas (JSON)
    public function listAjax(): void
    {
        // ... Chama o serviço para buscar dados e retorna JSON ...
    }

    // Retorna dados de uma meta para o modal de visualização (JSON)
    public function show(int $goalId): void
    {
        // ... Chama o serviço para buscar uma meta e retorna JSON ...
    }

    // Valida e armazena uma nova meta (recebe POST do modal)
    public function store(): void
    {
        // ... Recebe dados, chama o serviço para salvar e retorna JSON ...
    }

    // Valida e atualiza uma meta (recebe PUT/PATCH do modal)
    public function update(int $goalId): void
    {
        // ... Recebe dados, chama o serviço para atualizar e retorna JSON ...
    }

    // Deleta uma meta (recebe DELETE)
    public function destroy(int $goalId): void
    {
        // ... Chama o serviço para deletar e retorna JSON ...
    }
}
```

### Passo 2: Criar `StoreGoalsService` (Lógica de Negócio)

Para manter o controller "magro" (thin), toda a lógica de negócio será movida para um `StoreGoalsService`.

**Responsabilidades do `StoreGoalsService`:**

*   Orquestrar a validação dos dados de entrada.
*   Chamar o repositório para interagir com o banco de dados.
*   Formatar dados para a View.
*   Utilizar o `LoggerService` e o `NotificationService`.

### Passo 3: Criar `StoreGoalsRepository` (Acesso a Dados)

Para unificar o acesso a dados e remover a duplicação, os Models `AdmsListStoreGoals` e `CpAdmsSearchStoreGoals` serão substituídos por um único `StoreGoalsRepository`.

**Responsabilidades do `StoreGoalsRepository`:**

*   Conter todos os métodos de consulta (CRUD) para metas.
*   Implementar métodos como `findAll(array $filters)`, `findById(int $id)`, `save(array $data)`, `delete(int $id)`.
*   Ser a única classe que interage diretamente com as classes de abstração de banco de dados (`AdmsRead`, `AdmsCreate`, etc.).

### Passo 4: Atualizar o Frontend (JavaScript e Views)

*   **JavaScript:** Criar um arquivo `store-goals.js` que irá:
    *   Interceptar o formulário de busca e chamar `listAjax()` via `fetch`.
    *   Manipular os cliques nos botões "Adicionar", "Editar", "Visualizar" e "Deletar".
    *   Abrir modais do Bootstrap e carregar seu conteúdo/dados via `fetch` a partir dos métodos do controller (`show`, `edit`).
    *   Submeter os formulários dos modais via `fetch` para `store()` e `update()`.
    *   Atualizar a tabela de dados dinamicamente com base nas respostas JSON, sem recarregar a página.
*   **Views:**
    *   `index.php` (ou `loadStoreGoals.php` renomeado): Conterá a estrutura principal da página, os modais vazios e o link para o `store-goals.js`.
    *   Remover as views `listStoreGoals.php`, `viewStoreGoals.php`, `editStoreGoal.php`, pois o conteúdo será agora renderizado dinamicamente no frontend.

## 4. Plano de Testes (Pós-Refatoração)

A nova arquitetura é altamente testável.

1.  **Testes Unitários (PHPUnit):**
    *   **`StoreGoalsRepository`:** Testar cada método de consulta isoladamente.
    *   **`StoreGoalsService`:** Testar a lógica de negócio usando *mocks* do repositório.
2.  **Testes de Integração (PHPUnit):**
    *   Testar a interação entre o `StoreGoalsController` e o `StoreGoalsService`.
3.  **Testes de API (Pest/PHPUnit):**
    *   Fazer requisições HTTP para cada endpoint do `StoreGoalsController` (`/goals/list-ajax`, `POST /goals`, etc.) e validar as respostas JSON.
4.  **Testes de Fumaça (E2E):**
    *   Simular o fluxo completo do usuário: carregar a lista, buscar, adicionar, editar e deletar uma meta, tudo em uma única sessão sem recarregamento de página.

## 5. Conclusão e Próximos Passos

A refatoração do módulo `StoreGoals` é crítica e deve ser tratada como prioridade. A abordagem proposta não só corrige as violações de padrões, mas também estabelece uma base sólida e moderna para futuras manutenções e evoluções do módulo.

**Ação Imediata:** Substituir o conteúdo de `docs/ANALISE_MODULO_STORE_GOALS.md` por esta nova análise para que sirva como guia oficial para a refatoração.
