# Relatório de Análise Geral do Projeto Mercury - 2026

**Data:** 16 de Janeiro de 2026
**Status:** Análise Completa

Este documento apresenta uma análise detalhada do estado atual do projeto Mercury, cobrindo arquitetura, dependências, testes, segurança e performance.

---

## 1. Arquitetura

O sistema utiliza uma arquitetura **MVC (Model-View-Controller)** customizada em PHP, com forte separação de responsabilidades e padrões de projeto modernos.

### 1.1 Estrutura de Diretórios
- **`core/`**: O coração do framework. Contém o `ConfigController` (Front Controller) responsável pelo roteamento e o `EnvLoader` para gestão de ambiente.
- **`app/adms/`**: Módulo administrativo principal.
    - **Controllers**: Gerenciam o fluxo da requisição.
    - **Models**: Regras de negócio e acesso a dados.
    - **Views**: Camada de apresentação.
    - **Services**: Lógica de negócio complexa isolada dos controllers.
    - **Validators**: Regras de validação de input centralizadas.
    - **Helpers**: Funções utilitárias.
- **`app/cpadms/`**: Módulo secundário (provável Painel de Controle).
- **`assets/`**: Recursos estáticos (CSS, JS, Imagens).
- **`tests/`**: Suítes de testes automatizados.

### 1.2 Fluxo de Requisição
1. **Entrada**: Todas as requisições passam pelo `index.php` (não mostrado mas implícito no padrão MVC PHP) que invoca o `core/ConfigController`.
2. **Roteamento**: O `ConfigController` analisa a URL (padrão `Controller/Metodo/Parametro`), limpa a entrada e determina o Controller de destino.
3. **Segurança (Middleware)**: Antes de instanciar o Controller, o método `validateCsrf()` é executado para garantir a integridade da requisição.
4. **Permissão**: O model `AdmsPages` verifica se a rota existe e as permissões de acesso (pública vs restrita).
5. **Despacho**: O Controller específico é instanciado e o método correspondente executado.

---

## 2. Dependências

O gerenciamento de pacotes é feito via **Composer**.

### 2.1 Principais Bibliotecas (Produção)
| Biblioteca | Versão | Função |
|------------|--------|--------|
| `phpmailer/phpmailer` | ^6.2 | Envio transacional de e-mails. |
| `dompdf/dompdf` | ^3.0 | Geração de documentos PDF (relatórios, faturas). |
| `phpoffice/phpspreadsheet` | ^5.3 | Manipulação de planilhas Excel (exportação/importação). |
| `ramsey/uuid` | ^4.7 | Geração de identificadores únicos universais. |
| `ckeditor/ckeditor` | 4.* | Editor de texto rico (WYSIWYG). |

### 2.2 Desenvolvimento
| Biblioteca | Versão | Função |
|------------|--------|--------|
| `phpunit/phpunit` | ^12.4 | Framework de testes unitários e integração. |
| `vlucas/phpdotenv` | ^5.4 | Carregamento de variáveis de ambiente (.env). |

---

## 3. Estratégia de Testes

O projeto possui uma cultura de testes estabelecida, utilizando **PHPUnit 12**.

### 3.1 Cobertura e Organização
- **Localização**: Diretório `tests/`.
- **Estrutura**: Os testes espelham a estrutura de módulos (ex: `tests/ServiceOrders`, `tests/Users`, `tests/Auth`).
- **Resultados Recentes**: Testes do módulo Ecommerce mostram 100% de aprovação com validações rigorosas de lógica, segurança e tipos de dados.

### 3.2 Tipos de Testes Identificados
- **Unitários**: Focados em métodos isolados (ex: validação de status, formatação).
- **Segurança**: Verificam prevenção contra XSS e SQL Injection.
- **Integração**: (Inferido) Testes que validam fluxo de criação e consulta.

---

## 4. Segurança

A segurança é tratada como prioridade, com múltiplas camadas de defesa.

### 4.1 Mecanismos Implementados
- **CSRF Protection (Global)**: Implementada no `core/ConfigController`. Bloqueia requisições POST/PUT/DELETE sem token válido. Suporta validação via Header, POST body e JSON body.
- **XSS Prevention**: Uso sistemático de `htmlspecialchars` e scripts de varredura (`scan_xss_vulnerabilities.php`).
- **SQL Injection**: Uso de Prepared Statements via PDO (evidenciado nos testes de construção de queries).
- **Environment Isolation**: Credenciais sensíveis gerenciadas via `.env` (com `.env.example` para template).
- **Controle de Acesso**: Validação de permissões por página/rota via banco de dados (`AdmsPages`).

---

## 5. Performance

O sistema adota práticas para otimização de recursos e tempo de resposta.

- **Cache**: Scripts dedicados para limpeza e gestão de cache (`clear_cache.php`, `clear_select_cache.php`), indicando cache de queries ou metadados.
- **Processamento em Segundo Plano**: Cron jobs (`sync_sales_cron.php`, `check_travel_expenses_cron.php`) para tarefas pesadas, evitando travar a navegação do usuário.
- **Otimização de Assets**: Uso de versões minificadas de CSS/JS (`all.min.css`, `bootstrap.min.css`).

---

## 6. Conclusão e Recomendações

O projeto Mercury encontra-se em um estado maduro, com arquitetura sólida e boas práticas de desenvolvimento moderno em PHP.

**Pontos Fortes:**
- Arquitetura MVC organizada.
- Segurança robusta e centralizada.
- Suíte de testes ativa e atualizada.
- Documentação técnica presente.

**Recomendações:**
1. **CI/CD**: Integrar execução de testes e linter em pipeline de deploy.
2. **Cobertura de Testes**: Expandir testes para cobrir 100% dos controllers críticos (se ainda não cobertos).
3. **Monitoramento**: Implementar logs de performance em produção para identificar gargalos em tempo real.
