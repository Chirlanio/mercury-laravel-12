# Changelog - Módulo de Ordem de Serviço

## [2.1.0] - 2026-01-05

### ✨ Adicionado

#### Modal de Visualização
- **Nova seção**: "Nota de Transferência e ZZnet"
  - Exibe informações adicionais sobre transferências e sistema ZZnet
  - Localização: Entre "Informações do Defeito" e "Observações"
  - A seção só é exibida se pelo menos um dos campos estiver preenchido

#### Novos Campos Exibidos
1. **Nº Nota Transferência** (`num_nota_transf`)
   - Número da nota fiscal de transferência
   - Formato: String

2. **Data Emissão Nota** (`data_emissao_nota_transf`)
   - Data de emissão da nota de transferência
   - Formato: DD/MM/YYYY

3. **Nº OS ZZnet** (`order_service_zznet`)
   - Número da ordem de serviço no sistema ZZnet
   - Formato: String

4. **Data OS ZZnet** (`date_order_service_zznet`)
   - Data da ordem de serviço no sistema ZZnet
   - Formato: DD/MM/YYYY

### 🔧 Melhorado

#### Função de Impressão
- Atualizada para incluir todos os novos campos
- Seção "Nota de Transferência e ZZnet" agora aparece na impressão
- Informações de auditoria corrigidas e sempre impressas:
  - Data de Criação
  - Criado Por
  - Última Atualização
  - Atualizado Por
  - Tempo em Aberto

### 🐛 Corrigido

#### Seletor de Cards na Impressão
- Problema: Card de auditoria não estava sendo encontrado devido a conflito com o novo card de transferência
- Solução: Implementado busca por texto do cabeçalho ao invés de classe CSS
- Arquivo: `assets/js/service-orders.js`

### 📁 Arquivos Modificados

1. **`app/adms/Views/serviceOrder/partials/_view_service_order_content.php`**
   - Adicionada nova seção HTML (linhas 132-178)
   - Exibição condicional baseada em campos preenchidos

2. **`assets/js/service-orders.js`**
   - Função `printServiceOrderDetails()` atualizada
   - Adicionada extração de dados da seção de transferência (linhas 1018-1061)
   - Corrigido seletor de card de auditoria (linhas 960-968)
   - Adicionada função `extractFromCard()` para extração de dados (linhas 1013-1029)
   - Seção de transferência incluída no HTML de impressão (linha 1257)

### 📋 Estrutura da Nova Seção

```html
<!-- Informações da Nota de Transferência e ZZnet -->
<div class="card border-secondary">
    <div class="card-header bg-secondary text-white">
        <i class="fas fa-file-invoice mr-2"></i>
        Nota de Transferência e ZZnet
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <!-- Nº Nota Transferência -->
                <!-- Data Emissão Nota -->
            </div>
            <div class="col-md-6">
                <!-- Nº OS ZZnet -->
                <!-- Data OS ZZnet -->
            </div>
        </div>
    </div>
</div>
```

### 🔍 Consultas SQL Necessárias

Nenhuma alteração no banco de dados foi necessária. Os campos já existem na tabela `adms_qualidade_ordem_servico`:
- `num_nota_transf`
- `data_emissao_nota_transf`
- `order_service_zznet`
- `date_order_service_zznet`

### ✅ Compatibilidade

- ✅ Retrocompatível com ordens existentes
- ✅ Campos vazios não quebram a interface
- ✅ Impressão funcional para todos os casos
- ✅ Responsivo (mobile e desktop)

### 📝 Notas Técnicas

#### Exibição Condicional
```php
$hasTransfData = !empty($order['num_nota_transf']) ||
                  !empty($order['data_emissao_nota_transf']) ||
                  !empty($order['order_service_zznet']) ||
                  !empty($order['date_order_service_zznet']);
```

#### Extração para Impressão (JavaScript)
```javascript
const transfSection = viewContent.querySelector('.card.border-secondary .fa-file-invoice');
if (transfSection) {
    const transfCardEl = transfSection.closest('.card');
    // Extrai todos os campos dt/dd do card
}
```

#### Busca de Card de Auditoria
```javascript
const allSecondaryCards = viewContent.querySelectorAll('.card.border-secondary');
allSecondaryCards.forEach(card => {
    const header = card.querySelector('.card-header');
    if (header && header.textContent.includes('Informações de Registro')) {
        auditCard = card;
    }
});
```

### 🎯 Casos de Uso

1. **Visualização no Modal**
   - Usuário abre modal de visualização da ordem
   - Se campos estiverem preenchidos, seção é exibida automaticamente
   - Campos vazios não geram erros

2. **Impressão**
   - Usuário clica em "Imprimir" no modal
   - Nova janela abre com todas as informações formatadas
   - Seção de transferência e ZZnet incluída automaticamente
   - Layout otimizado para papel A4

3. **Edição**
   - Campos podem ser editados através do formulário de edição existente
   - Não há alterações no fluxo de edição

### 📚 Referências

- Padrão de nomenclatura: `.claude/REGRAS_DESENVOLVIMENTO.md`
- Estrutura de views: `docs/PADRONIZACAO.md`
- Guia de implementação: `docs/GUIA_IMPLEMENTACAO_MODULOS.md`

---

**Desenvolvido por:** Equipe Mercury - Grupo Meia Sola
**Data da Implementação:** 05/01/2026
**Versão do Sistema:** 2.1.0
