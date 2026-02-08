# Guia de Implementação do Modal de Confirmação de Exclusão

## Visão Geral

Sistema padronizado para confirmação de exclusão de registros usando modais Bootstrap personalizados.

## Arquitetura

### 1. **Componente Base**
- **Arquivo**: `app/adms/Views/include/_delete_confirmation_modal.php`
- **Descrição**: Modal genérico reutilizável que pode ser personalizado via variáveis PHP

### 2. **Biblioteca JavaScript**
- **Arquivo**: `assets/js/delete-confirmation.js`
- **Classe**: `DeleteConfirmationModal`
- **Função Helper**: `showDeleteConfirmation()`

## Como Implementar em um Novo Módulo

### Passo 1: Criar o Modal Específico do Módulo

Crie um arquivo em `app/adms/Views/[modulo]/partials/_delete_[nome]_modal.php`:

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

$modalId = 'deleteExampleModal';  // ID único do modal
$modalTitle = 'Confirmar Exclusão de Exemplo';  // Título do modal
$warningMessage = 'Atenção: Esta ação não pode ser desfeita.';  // Mensagem de aviso

include __DIR__ . '/../../include/_delete_confirmation_modal.php';
?>
```

### Passo 2: Incluir o Modal e o JavaScript na View Principal

No arquivo principal do módulo (ex: `loadExamples.php`):

```php
<?php
// ... outros includes ...
include_once 'partials/_delete_example_modal.php';
?>

<!-- Carregar biblioteca JavaScript -->
<script src="<?php echo URLADM . 'assets/js/delete-confirmation.js?v=' . time(); ?>"></script>
<script src="<?php echo URLADM . 'assets/js/examples.js?v=' . time(); ?>"></script>
```

### Passo 3: Implementar a Função JavaScript

No arquivo JavaScript do módulo (ex: `assets/js/examples.js`):

```javascript
/**
 * Abre o modal de confirmação de exclusão
 * @param {number} itemId - ID do item
 * @param {string} itemName - Nome do item
 * @param {string} additionalInfo - Informações adicionais (opcional)
 */
function deleteExample(itemId, itemName, additionalInfo = '') {
    if (!itemId) return;

    // Cria instância do modal
    const deleteModal = new DeleteConfirmationModal('deleteExampleModal');

    // Prepara os dados para exibir
    const data = {
        'ID': itemId,
        'Nome': itemName || 'N/A'
    };

    if (additionalInfo) {
        data['Info Adicional'] = additionalInfo;
    }

    // Define o callback de exclusão
    const onConfirm = async (id) => {
        const URL_BASE = '<?php echo URLADM; ?>';  // ou obtenha dinamicamente

        try {
            const response = await fetch(`${URL_BASE}delete-example/delete/${id}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Erro ao deletar');
            }

            const result = await response.json();

            // Fecha o modal
            deleteModal.close();

            // Mostra notificação
            if (result.success) {
                showNotification('success', result.message || 'Item excluído com sucesso!');
                // Recarrega a listagem
                listExamples(1);
            } else {
                showNotification('error', result.message || 'Erro ao excluir item.');
            }

        } catch (error) {
            console.error('Erro ao deletar:', error);
            deleteModal.showError('Erro ao processar exclusão. Tente novamente.');
        }
    };

    // Abre o modal
    deleteModal.show(itemId, data, onConfirm, {
        description: 'Você está prestes a excluir o seguinte item:',
        extraWarning: 'Todos os dados serão removidos permanentemente.'
    });
}
```

### Passo 4: Atualizar o HTML da Listagem

Certifique-se de chamar a função com os parâmetros corretos:

```php
<button type="button" class="btn btn-danger btn-sm"
        onclick="deleteExample(<?= $id ?>, '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($additionalInfo, ENT_QUOTES) ?>')">
    <i class="fas fa-trash"></i> Excluir
</button>
```

## Módulos já Implementados

✅ **Movimentações de Pessoal** - `personnelMoviments`
- Modal: `_delete_moviment_modal.php`
- Função: `confirmDeleteMoviment()`
- Reativa colaborador automaticamente ao excluir

✅ **Funcionários** - `employee`
- Modal: `_delete_employee_modal.php`
- Função: `deleteEmployee()`

✅ **Ajustes de Estoque** - `adjustments`
- Modal: `_delete_adjustment_modal.php`

✅ **Transferências** - `transfers`
- Modal: `_delete_transfer_modal.php`

✅ **Remanejos** - `relocation`
- Modal: `_delete_relocation_modal.php`

✅ **Cupons** - `coupon`
- Modal: `_delete_coupon_modal.php`

✅ **Páginas** - `pagina`
- Modal: `_delete_page_modal.php`

## API da Classe DeleteConfirmationModal

### Constructor
```javascript
new DeleteConfirmationModal(modalId)
```
- **modalId**: ID do modal HTML (deve corresponder ao ID definido no PHP)

### Métodos

#### show(itemId, data, deleteCallback, options)
Abre o modal com os dados do item.

**Parâmetros:**
- `itemId` (number|string): ID do item a ser excluído
- `data` (Object): Dados do item para exibir no modal (pares chave-valor)
- `deleteCallback` (Function): Função assíncrona chamada ao confirmar
- `options` (Object): Opções adicionais
  - `description` (string): Descrição personalizada
  - `extraWarning` (string): Aviso extra personalizado

**Exemplo:**
```javascript
deleteModal.show(123, {
    'ID': 123,
    'Nome': 'João Silva',
    'CPF': '123.456.789-00'
}, async (id) => {
    await deleteItem(id);
}, {
    description: 'Confirme a exclusão do seguinte funcionário:',
    extraWarning: 'O funcionário será reativado automaticamente se houver movimentações.'
});
```

#### showError(message)
Exibe uma mensagem de erro dentro do modal.

#### clearMessages()
Limpa todas as mensagens do modal.

#### close()
Fecha o modal.

## Personalização

### Alterar Cores e Estilos

O modal usa as classes do Bootstrap. Para personalizar:

1. **Header** - Use `bg-danger`, `bg-warning`, etc.
2. **Botões** - Use `btn-danger`, `btn-primary`, etc.
3. **Ícones** - Font Awesome (`fas fa-trash`, `fas fa-exclamation-triangle`)

### Mensagens Personalizadas

Você pode personalizar as mensagens via parâmetros PHP ou JavaScript:

**PHP:**
```php
$modalTitle = 'ATENÇÃO - Ação Perigosa';
$warningMessage = 'Esta ação afetará outros sistemas!';
```

**JavaScript:**
```javascript
deleteModal.show(id, data, callback, {
    description: 'Esta exclusão é irreversível!',
    extraWarning: '<strong>ATENÇÃO:</strong> Esta ação afetará o estoque.'
});
```

## Boas Práticas

1. **IDs Únicos**: Sempre use um ID único para cada modal de módulo
2. **Sanitização**: Use `htmlspecialchars(..., ENT_QUOTES)` ao passar strings do PHP para JavaScript
3. **Feedback Visual**: Mostre spinner no botão durante a exclusão
4. **Tratamento de Erros**: Sempre capture exceções e mostre mensagens claras
5. **Recarregamento**: Recarregue a lista após exclusão bem-sucedida
6. **AJAX**: Use `X-Requested-With: XMLHttpRequest` para controllers detectarem requisições AJAX

## Exemplo Completo

Ver implementação em:
- **Backend**: `app/adms/Models/AdmsDeletePersonnelMoviments.php`
- **Controller**: `app/adms/Controllers/DeletePersonnelMoviments.php`
- **Frontend**: `assets/js/personnelMoviments.js` (linhas 1261-1353)
- **View**: `app/adms/Views/personnelMoviments/partials/_delete_moviment_modal.php`

## Suporte

Para dúvidas ou problemas, consulte:
- `DELETE_MODAL_IMPLEMENTATION_GUIDE.md` (este arquivo)
- `delete-confirmation.js` (código fonte com comentários)
- Implementação de referência no módulo `personnelMoviments`
