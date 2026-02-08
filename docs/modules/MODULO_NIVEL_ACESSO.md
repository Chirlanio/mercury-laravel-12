# Análise do Módulo de Nível de Acesso (Mercury)

**Data:** 02 de Dezembro de 2025
**Autor:** Gemini
**Versão:** 1.0

---

## 1. Visão Geral

O módulo de Nível de Acesso é uma parte crítica do sistema de segurança do Mercury, responsável por definir os diferentes níveis de permissão que os usuários podem ter.

A análise do controller `NivelAcesso.php` revela uma arquitetura legada, consistente com outros módulos mais antigos do sistema. Ele possui um único método `listar` para exibir os níveis de acesso, enquanto todas as outras ações de CRUD são delegadas a controllers separados.

### Status Atual

| Categoria | Status | Comentário |
|-----------|--------|------------|
| **Funcionalidade** | ✅ Funcional | Listagem de níveis de acesso. |
| **Padrão de Código** | 👎 Legado | Segue o padrão de um controller por ação. |
| **Manutenibilidade** | 👎 Baixa | A lógica é fragmentada em múltiplos arquivos, dificultando a manutenção. |

---

## 2. Arquitetura e Estrutura de Arquivos

-   **Controller (`app/adms/Controllers/NivelAcesso.php`):**
    -   `listar()`: Responsável por carregar o menu, os botões de ação e a lista de níveis de acesso.

-   **Controllers Legados:**
    -   `cadastrar-niv-ac`, `ver-niv-ac`, `editar-niv-ac`, `apagar-niv-ac`, `alt-ordem-niv-ac`, `permissoes`, `sincro-pg-niv-ac`: Controllers separados que lidam com as ações de CRUD e outras funcionalidades relacionadas a níveis de acesso.

-   **Model (`app/adms/Models/AdmsListarNivAc.php`):**
    -   Contém a lógica para buscar e paginar os níveis de acesso no banco de dados.

---

## 3. Pontos de Melhoria

A modernização do módulo de Nível de Acesso deve seguir as diretrizes do arquivo `MODERNIZATION_AND_PATTERNS.md`.

1.  **Unificar Controllers:**
    *   Consolidar todos los controllers de ação em um único `NivelAcessoController.php`.
    *   Criar métodos como `index`, `create`, `store`, `edit`, `update`, `destroy`, `editPermissions`, `syncPermissions`, etc., para abrigar a lógica dos controllers legados.

2.  **Implementar Camadas de Serviço e Repositório:**
    *   Criar um `NivelAcessoService` para a lógica de negócio (ex: validações de permissões).
    *   Criar um `NivelAcessoRepository` para centralizar todo o acesso ao banco de dados.

3.  **Adotar Injeção de Dependência:**
    *   Injetar as dependências (`NivelAcessoService`, `NivelAcessoRepository`) no `NivelAcessoController`.

4.  **Padronizar o Fluxo 100% AJAX:**
    *   Refatorar todas as ações para serem tratadas via AJAX com respostas JSON, eliminando os redirecionamentos de página inteira.

---

## 4. Conclusão

O módulo de Nível de Acesso é um exemplo claro da arquitetura legada do sistema. Sua refatoração, seguindo os padrões modernos do projeto, é fundamental para melhorar a segurança, a manutenibilidade e a coesão do código relacionado a permissões.
