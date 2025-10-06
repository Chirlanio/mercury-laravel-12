# Sistema de Confirmações Personalizadas

## Visão Geral

O sistema Mercury agora possui diálogos de confirmação personalizados que substituem o `window.confirm()` nativo do navegador, oferecendo uma experiência mais profissional e consistente.

## Componentes Criados

### 1. ConfirmDialog Component
**Arquivo**: `resources/js/Components/ConfirmDialog.jsx`

Componente visual do diálogo de confirmação com suporte a diferentes tipos e estilos.

#### Props:
- `show` (boolean): Controla visibilidade do modal
- `onClose` (function): Callback ao fechar
- `onConfirm` (function): Callback ao confirmar
- `title` (string): Título do diálogo (padrão: "Confirmação")
- `message` (string): Mensagem principal
- `confirmText` (string): Texto do botão de confirmação (padrão: "Confirmar")
- `cancelText` (string): Texto do botão de cancelar (padrão: "Cancelar")
- `type` (string): Tipo do alerta - "warning", "danger", "info", "success"
- `confirmButtonClass` (string): Classes CSS customizadas para o botão de confirmação

#### Tipos Disponíveis:
- **warning** (amarelo): Avisos gerais
- **danger** (vermelho): Ações destrutivas/perigosas
- **info** (azul): Informações importantes
- **success** (verde): Confirmações positivas

### 2. useConfirm Hook
**Arquivo**: `resources/js/Hooks/useConfirm.jsx`

Hook customizado que facilita o uso do ConfirmDialog em qualquer componente.

#### Uso Básico:

```jsx
import { useConfirm } from '@/Hooks/useConfirm';

export default function MyComponent() {
    const { confirm, ConfirmDialogComponent } = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Confirmar Exclusão',
            message: 'Tem certeza que deseja excluir este item?',
            confirmText: 'Sim, Excluir',
            cancelText: 'Cancelar',
            type: 'danger',
        });

        if (confirmed) {
            // Executar ação de exclusão
        }
    };

    return (
        <>
            <button onClick={handleDelete}>Excluir</button>

            {/* Adicionar no final do componente */}
            <ConfirmDialogComponent />
        </>
    );
}
```

## Exemplo Implementado

### Página de Permissões
**Arquivo**: `resources/js/Pages/AccessLevels/Permissions.jsx`

Implementação de confirmação antes de salvar permissões:

```jsx
const handleSaveAll = async () => {
    const activePermissions = countActivePermissions();
    const totalPages = Object.keys(permissions).length;

    const confirmed = await confirm({
        title: 'Salvar Permissões',
        message: `Você está prestes a salvar ${activePermissions} de ${totalPages} permissões para o perfil "${accessLevel.name}". Esta ação afetará o acesso dos usuários com este nível. Deseja continuar?`,
        confirmText: 'Sim, Salvar',
        cancelText: 'Cancelar',
        type: 'info',
    });

    if (!confirmed) return;

    // Prosseguir com salvamento...
};
```

## Modal de Sessão Expirada

### Erro 419 (CSRF Token)
**Arquivo**: `resources/js/app.jsx`

Modal customizado para erro de sessão expirada (419):

```javascript
router.on('error', (event) => {
    if (detail.page && detail.page.status === 419) {
        // Exibe modal HTML personalizado
        // Com botões "Cancelar" e "Recarregar Página"
    }
});
```

**Características:**
- Design consistente com o sistema
- Mensagem clara sobre o motivo da sessão expirada
- Botões de ação bem definidos
- Ícone de aviso visual
- Responsivo e acessível

## Vantagens

1. **Consistência Visual**: Todos os diálogos seguem o mesmo design system
2. **Melhor UX**: Mensagens mais claras e contextuais
3. **Acessibilidade**: Suporte completo a navegação por teclado
4. **Responsivo**: Funciona bem em mobile e desktop
5. **Reutilizável**: Fácil de implementar em qualquer página
6. **Assíncrono**: Usa Promises para fluxo mais limpo
7. **Personalizável**: Múltiplos tipos e opções de customização

## Como Migrar Códigos Existentes

### Antes (usando window.confirm):
```jsx
const handleDelete = () => {
    if (confirm('Tem certeza que deseja excluir?')) {
        // Executar ação
    }
};
```

### Depois (usando useConfirm):
```jsx
import { useConfirm } from '@/Hooks/useConfirm';

const MyComponent = () => {
    const { confirm, ConfirmDialogComponent } = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Confirmar Exclusão',
            message: 'Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.',
            confirmText: 'Sim, Excluir',
            cancelText: 'Cancelar',
            type: 'danger',
        });

        if (confirmed) {
            // Executar ação
        }
    };

    return (
        <>
            {/* Seu componente */}
            <ConfirmDialogComponent />
        </>
    );
};
```

## Localizações com window.confirm() para Migrar

Os seguintes arquivos ainda usam `window.confirm()` e devem ser migrados:

1. `resources/js/Pages/UserManagement/Index.jsx` (2 ocorrências)
2. `resources/js/Pages/UserManagement/Edit.jsx` (1 ocorrência)
3. `resources/js/Pages/Pages/Index.jsx` (2 ocorrências)
4. `resources/js/Pages/Menu/Index.jsx` (1 ocorrência)
5. `resources/js/Components/EmployeeEventsModal.jsx` (1 ocorrência)
6. `resources/js/Pages/Menu/Show.jsx` (1 ocorrência)
7. `resources/js/Components/EmployeeHistoryModal.jsx` (1 ocorrência)

## Boas Práticas

1. **Seja descritivo**: Explique claramente o que acontecerá ao confirmar
2. **Use o tipo correto**:
   - `danger` para exclusões e ações destrutivas
   - `warning` para mudanças importantes
   - `info` para confirmações informativas
   - `success` para confirmações positivas
3. **Botões claros**: Use textos que descrevam a ação (ex: "Sim, Excluir" ao invés de "OK")
4. **Contexto**: Inclua informações relevantes na mensagem (nomes, quantidades, etc)

## Estilização

O componente usa Tailwind CSS e Headless UI, seguindo o design system do Mercury:

- Cores padronizadas para cada tipo
- Animações suaves de entrada/saída
- Overlay semitransparente
- Bordas arredondadas
- Sombras adequadas
- Responsividade mobile-first

## Suporte

Para dúvidas ou sugestões sobre o sistema de confirmações, consulte a documentação técnica ou entre em contato com a equipe de desenvolvimento.
