# Relatório de Análise de Módulos - Mercury 2026

**Data:** 16 de Janeiro de 2026
**Escopo:** Análise estrutural e qualitativa dos principais módulos do sistema (ServiceOrder e Users).

---

## 1. Critérios de Avaliação

A análise foi conduzida com base nos seguintes critérios:
- **Complexidade Ciclomática**: Nível de aninhamento e caminhos de decisão nos Controllers e Models.
- **Acoplamento**: Dependência entre classes e camadas.
- **Padronização**: Adesão ao padrão MVC e convenções de nomenclatura do projeto.
- **Segurança**: Validação de inputs e verificação de permissões.

---

## 2. Módulos Analisados

### 2.1 Módulo ServiceOrder (Ordens de Serviço)
- **Controller**: `app/adms/Controllers/ServiceOrder.php`
- **Model Principal**: `app/adms/Models/AdmsListServiceOrders.php`

**Análise:**
- **Estrutura**: O Controller é exemplar, atuando apenas como orquestrador. Utiliza expressões `match` (PHP 8) para roteamento interno limpo.
- **Responsabilidade**: Delega listagem, estatísticas e busca para classes especializadas (`AdmsListServiceOrders`, `AdmsStatisticsServiceOrders`, `CpAdmsSearchServiceOrders`).
- **Segurança**: Implementa `filter_input` para todos os parâmetros GET/POST. O Model aplica cláusulas `WHERE` restritivas baseadas na loja do usuário (`$_SESSION['usuario_loja']`), garantindo isolamento de dados (Multi-tenancy lógico).
- **Performance**: O Model utiliza `LEFT JOIN` para trazer dados relacionados (marcas, defeitos) em uma única query, evitando o problema N+1.

### 2.2 Módulo User (Gestão de Usuários)
- **Controller**: `app/adms/Controllers/User.php`
- **Model Principal**: `app/adms/Models/AdmsListUsers.php`

**Análise:**
- **Consistência**: Segue estritamente o mesmo padrão do `ServiceOrder`, evidenciando uma arquitetura coerente e previsível.
- **Controle de Acesso**: O método `getUserAccessLevels` filtra níveis de acesso baseados na hierarquia (`ordem >= :ordem`), impedindo escalação de privilégios.
- **Interface**: Carrega selects e filtros de forma modular via métodos privados.

---

## 3. Métricas Gerais

| Métrica | Avaliação | Detalhes |
|---------|-----------|----------|
| **Tamanho dos Controllers** | Baixo (< 200 linhas) | Excelente. Controllers focados apenas no fluxo HTTP. |
| **Tamanho dos Models** | Médio (200-400 linhas) | Adequado. Concentram lógica SQL e regras de negócio. |
| **Acoplamento** | Alto (Concreto) | Uso direto de `new ClassName()`. Dificulta testes unitários isolados dos controllers, mas simplifica o desenvolvimento rápido. |
| **Coesão** | Alta | Cada classe tem responsabilidade única bem definida (Listar, Cadastrar, Estatísticas). |

---

## 4. Pontos de Atenção e Otimizações

### 4.1 Vulnerabilidades Potenciais
- **Instanciação Direta**: A criação de objetos com `new` dentro dos métodos dificulta a injeção de dependências e mocks para testes.
- **Hardcoded IDs**: Alguns arrays de status e tipos (ex: `getServiceOrderStatuses`) possuem IDs fixos no código. Recomendado mover para banco de dados ou constantes globais.

### 4.2 Sugestões de Melhoria
1.  **Injeção de Dependência**: Refatorar Controllers para receber Models via construtor ou Service Container.
2.  **Fábrica de Queries**: Abstrair a construção de queries complexas (com muitos JOINs) para classes Repository dedicadas, limpando os Models de visualização.
3.  **Constantes**: Substituir números mágicos (ex: `status_id = 1`) por Constantes de Classe (ex: `Status::AGUARDANDO`).

---

## 5. Conclusão

O sistema apresenta uma arquitetura modular madura e muito bem padronizada. A consistência entre módulos facilita a manutenção e a curva de aprendizado para novos desenvolvedores. A segurança é tratada de forma nativa na estrutura das queries e validações.

---
**Próxima Varredura Recomendada:** Junho de 2026
