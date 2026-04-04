# Guia de Contribuição — Projeto Mercury

**Versão:** 1.0
**Última Atualização:** 22 de Março de 2026

---

## Fluxo de Trabalho

### Branches

Toda contribuição deve partir da branch `develop`. Utilize a seguinte nomenclatura:

- `feature/*` — Novas funcionalidades (ex: `feature/vacation-approval`)
- `fix/*` — Correções de bugs (ex: `fix/stock-audit-calculation`)
- `refactor/*` — Refatorações sem mudança de comportamento (ex: `refactor/legacy-controllers`)
- `docs/*` — Alterações exclusivas em documentação (ex: `docs/update-deployment-guide`)

### Mensagens de Commit

Siga o padrão **Conventional Commits**:

```
feat: adicionar fluxo de aprovação de férias
fix: corrigir cálculo de divergência na auditoria de estoque
refactor: migrar controlador de transferências para match expression
docs: atualizar guia de implantação
test: adicionar testes para módulo de vendas
chore: atualizar dependências do Composer
```

- Mensagens em **português** ou **inglês** (manter consistência dentro do PR)
- Primeira linha com no máximo 72 caracteres
- Corpo opcional para detalhes adicionais

---

## Padrões de Código

Consulte obrigatoriamente o documento **[REGRAS_DESENVOLVIMENTO.md](../.claude/REGRAS_DESENVOLVIMENTO.md)** antes de implementar qualquer funcionalidade.

### Resumo dos padrões principais

| Item | Padrão |
|------|--------|
| Controllers | PascalCase (`StoreGoals.php`) |
| Models | Prefixo `Adms` (`AdmsListSales.php`) |
| Views | camelCase dirs e arquivos (`sales/loadSales.php`) |
| JavaScript | kebab-case (`store-goals.js`) |
| Partials | `_snake_case_modal.php` |
| PHP | Type hints em todos os parâmetros e retornos |
| SQL | Prepared statements obrigatórios |
| Output | `htmlspecialchars()` em todo dado exibido |

---

## Processo de Pull Request

### 1. Criar o PR

- Alvo: branch **develop** (nunca diretamente para `main`)
- Título curto e descritivo (máximo 70 caracteres)
- Descrição com resumo das alterações e plano de teste

### 2. Checklist obrigatório antes do PR

Antes de abrir o Pull Request, verifique:

- [ ] **Type hints** em todos os métodos (parâmetros e retorno)
- [ ] **PHPDoc** em métodos públicos
- [ ] **Prepared statements** em todas as queries (prevenção de SQL Injection)
- [ ] **htmlspecialchars()** em toda exibição de dados do usuário (prevenção de XSS)
- [ ] **LoggerService** para operações CRUD e erros
- [ ] **Testes unitários** — mínimo 5 por módulo (instanciação + 4 casos)
- [ ] **Todos os testes passando** (`php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/`)
- [ ] **Validação explícita** de campos obrigatórios (não depender de `AdmsCampoVazio`)
- [ ] **Permissões** via `adms_nivacs_pgs` (sem verificações hardcoded)
- [ ] **Responsividade** testada em mobile e desktop
- [ ] **Nomenclatura** seguindo os padrões do projeto

### 3. Revisão de Código

Toda PR requer pelo menos **uma aprovação** antes do merge. O revisor deve verificar:

- Aderência aos padrões documentados em `REGRAS_DESENVOLVIMENTO.md`
- Segurança (SQL Injection, XSS, validação de input)
- Cobertura de testes adequada
- Logging de operações relevantes
- Impacto em módulos existentes
- Migrations necessárias documentadas

### 4. Merge

- Após aprovação, o merge é feito para `develop`
- Merges para `main` ocorrem apenas em releases planejados
- Nunca faça force push em `main` ou `develop`

---

## Diretrizes de Revisão

### O que procurar

1. **Segurança** — Inputs validados? Outputs escapados? Queries parametrizadas?
2. **Consistência** — Segue os padrões existentes no projeto?
3. **Clareza** — O código é legível sem comentários excessivos?
4. **Testes** — Cenários de sucesso, erro e borda cobertos?
5. **Performance** — Queries otimizadas? Paginação utilizada?
6. **Logging** — Operações importantes registradas via `LoggerService`?

### Tom da revisão

- Seja construtivo e específico nas sugestões
- Diferencie entre bloqueadores (must fix) e sugestões (nice to have)
- Referencie documentação quando aplicável

---

## Módulo de Referência

O módulo **Sales** é a implementação de referência. Consulte-o para exemplos de:

- Controller com match expression
- Model de estatísticas
- JavaScript async/await
- Cobertura de testes completa

```
app/adms/Controllers/Sales.php
app/adms/Models/AdmsStatisticsSales.php
assets/js/sales.js
tests/Sales/
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
