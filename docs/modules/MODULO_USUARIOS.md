# Análise do Módulo de Usuários (Mercury)

**Data:** 02 de Dezembro de 2025
**Autor:** Gemini
**Versão:** 1.0

---

## 1. Visão Geral

O módulo de gerenciamento de usuários é um componente fundamental do sistema Mercury, responsável por controlar o acesso e o cadastro de todos os usuários da plataforma.

A análise revela uma arquitetura consistente com outros módulos legados do sistema. A camada de **Views** é parcialmente moderna, utilizando um sistema de carregamento de página inicial e listagem via AJAX. No entanto, a camada de **Controllers** segue um modelo fragmentado, com um controller principal para listagem e controllers separados para cada ação de CRUD.

### Status Atual

| Categoria | Status | Comentário |
|-----------|--------|------------|
| **Funcionalidade** | ✅ Funcional | CRUD completo, busca, e estatísticas de usuários. |
| **Padrão de Código** | 👍 Híbrido | Listagem e Views modernizadas; actions em controllers separados. |
| **Performance** | ✅ Boa | Listagem com AJAX e paginação. |
| **UX** | 👍 Média | A experiência é baseada em AJAX para listagem, mas as ações de CRUD podem levar a um fluxo de navegação inconsistente. |
| **Segurança** | ✅ Boa | Acesso a níveis de acesso restrito ao nível do usuário logado. |
| **Manutenibilidade** | 👍 Média | A estrutura de Views é clara, mas a fragmentação dos controllers de ação aumenta a complexidade. |

---

## 2. Arquitetura e Estrutura de Arquivos

O módulo está localizado principalmente em `app/adms/` e segue um padrão MVC.

-   **Controllers (`app/adms/Controllers/`):**
    -   `Users.php`: Controller principal que gerencia a listagem e busca de usuários. Utiliza AJAX para carregamento dinâmico.
    -   `AddUser.php`, `EditUser.php`, `DeleteUser.php`, `ViewUser.php`: **Controllers legados** que respondem pelas ações de CRUD.

-   **Models (`app/adms/Models/`):**
    -   `AdmsListUsers.php`, `AdmsAddUser.php`, `AdmsEditUser.php`, etc.: Modelos que contêm a lógica de negócio e o acesso ao banco de dados.
    -   `AdmsUserStatistics.php`: Modelo para buscar estatísticas de usuários.

-   **Views (`app/adms/Views/usuario/`):**
    -   `loadUsers.php` e `listUsers.php`: Arquivos base para carregar a estrutura e a lista de usuários.

---

## 3. Pontos de Melhoria (Próximos Passos)

A modernização do módulo de usuários deve seguir as diretrizes do arquivo `MODERNIZATION_AND_PATTERNS.md`. Os passos principais são:

1.  **Unificar Controllers de CRUD no `Users.php`:**
    *   Eliminar os controllers de ação legados (`AddUser.php`, `EditUser.php`, etc.).
    *   Mover a lógica para métodos dentro do controller `Users.php` (ex: `create()`, `store()`, `update()`, `destroy()`).

2.  **Refatorar para Camadas de Serviço e Repositório:**
    *   Criar um `UserService` para conter a lógica de negócio.
    *   Criar um `UserRepository` para centralizar todo o acesso ao banco de dados, incluindo as consultas que hoje estão no `Users.php` (ex: `getAreas`, `getStores`).

3.  **Adotar Injeção de Dependência:**
    *   Injetar o `UserService` e o `UserRepository` no `Users.php` através do construtor.

4.  **Padronizar o Fluxo 100% AJAX:**
    *   Garantir que todas as operações de CRUD sejam tratadas via AJAX com respostas JSON, proporcionando uma experiência de usuário fluida e sem recarregamentos de página.

---

## 4. Conclusão

O módulo de usuários está funcional, mas sua arquitetura legada o torna um candidato ideal para a refatoração. A aplicação dos padrões definidos no `MODERNIZATION_AND_PATTERNS.md` irá alinhar este módulo com as práticas modernas de desenvolvimento, melhorando significativamente sua manutenibilidade e escalabilidade.
