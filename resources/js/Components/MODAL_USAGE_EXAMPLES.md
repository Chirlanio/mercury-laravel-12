# Guia de Uso dos Componentes de Modal Genéricos

Este projeto possui dois componentes de modal altamente reutilizáveis:

1. **GenericFormModal** - Para formulários de criação e edição
2. **GenericDetailModal** - Para visualização de detalhes

---

## 1. GenericFormModal

Modal genérico para criar e editar registros.

### Propriedades

| Prop | Tipo | Padrão | Descrição |
|------|------|--------|-----------|
| `show` | `boolean` | - | Controla visibilidade do modal |
| `onClose` | `function` | - | Callback ao fechar |
| `onSuccess` | `function` | - | Callback após sucesso |
| `title` | `string` | - | Título do modal |
| `mode` | `'create' \| 'edit'` | `'create'` | Modo do formulário |
| `initialData` | `object \| null` | `null` | Dados para edição |
| `sections` | `array` | `[]` | Seções do formulário |
| `submitUrl` | `string` | - | URL de submissão |
| `submitMethod` | `string` | `'post'` | Método HTTP |
| `submitButtonText` | `string` | `'Salvar'` | Texto do botão |
| `maxWidth` | `string` | `'85vw'` | Largura máxima |
| `transformData` | `function` | `null` | Transforma dados antes de enviar |
| `preserveState` | `boolean` | `false` | Preserva estado após submissão |
| `preserveScroll` | `boolean` | `false` | Preserva scroll após submissão |

### Exemplo de Uso Básico

```jsx
import GenericFormModal from '@/Components/GenericFormModal';
import { useState } from 'react';

export default function MyPage() {
    const [showModal, setShowModal] = useState(false);
    const [editData, setEditData] = useState(null);

    const sections = [
        {
            title: 'Informações Básicas',
            columns: 'md:grid-cols-2',
            fields: [
                {
                    name: 'name',
                    label: 'Nome',
                    type: 'text',
                    required: true,
                    placeholder: 'Digite o nome',
                },
                {
                    name: 'email',
                    label: 'E-mail',
                    type: 'email',
                    required: true,
                },
                {
                    name: 'status',
                    label: 'Status',
                    type: 'select',
                    required: true,
                    placeholder: 'Selecione o status',
                    options: [
                        { value: 'active', label: 'Ativo' },
                        { value: 'inactive', label: 'Inativo' },
                    ],
                },
            ],
        },
    ];

    return (
        <>
            <button onClick={() => setShowModal(true)}>
                Novo Registro
            </button>

            <GenericFormModal
                show={showModal}
                onClose={() => setShowModal(false)}
                onSuccess={() => {
                    setShowModal(false);
                    // Recarregar dados, etc.
                }}
                title="Criar Novo Registro"
                mode="create"
                sections={sections}
                submitUrl="/api/records"
                submitMethod="post"
                submitButtonText="Criar Registro"
            />
        </>
    );
}
```

### Tipos de Campos Suportados

#### Text, Email, Number, Date, Time
```javascript
{
    name: 'field_name',
    label: 'Campo',
    type: 'text', // ou 'email', 'number', 'date', 'time'
    required: true,
    placeholder: 'Digite aqui',
    helperText: 'Texto de ajuda',
    defaultValue: '',
}
```

#### Textarea
```javascript
{
    name: 'description',
    label: 'Descrição',
    type: 'textarea',
    rows: 4,
    placeholder: 'Digite a descrição',
}
```

#### Select
```javascript
{
    name: 'category',
    label: 'Categoria',
    type: 'select',
    required: true,
    placeholder: 'Selecione uma categoria',
    options: [
        { value: '1', label: 'Categoria 1' },
        { value: '2', label: 'Categoria 2' },
    ],
}
```

#### Checkbox
```javascript
{
    name: 'is_active',
    label: 'Ativo',
    type: 'checkbox',
    defaultValue: false,
}
```

#### File (com validação e preview)
```javascript
{
    name: 'image',
    label: 'Imagem',
    type: 'file',
    accept: 'image/*',
    helperText: 'Tamanho máximo: 2MB',
    validate: (file) => {
        if (file.size > 2 * 1024 * 1024) {
            return 'Arquivo muito grande (max 2MB)';
        }
        return null;
    },
    preview: (file, initialData) => {
        if (file instanceof File) {
            return (
                <img
                    src={URL.createObjectURL(file)}
                    alt="Preview"
                    className="w-20 h-20 object-cover rounded"
                />
            );
        }
        return null;
    },
}
```

#### Campo Customizado
```javascript
{
    name: 'custom_field',
    type: 'custom',
    render: (data, setData, errors, field) => (
        <div>
            {/* Seu componente customizado aqui */}
        </div>
    ),
}
```

### Transformação de Dados

```javascript
<GenericFormModal
    // ... outras props
    transformData={(data) => ({
        ...data,
        cpf: data.cpf.replace(/\D/g, ''), // Remover máscara
        price: parseFloat(data.price),
    })}
/>
```

### Modo de Edição

```javascript
<GenericFormModal
    show={showEditModal}
    onClose={() => setShowEditModal(false)}
    onSuccess={() => setShowEditModal(false)}
    title="Editar Registro"
    mode="edit"
    initialData={selectedItem}
    sections={sections}
    submitUrl={`/api/records/${selectedItem.id}`}
    submitMethod="put"
    submitButtonText="Salvar Alterações"
/>
```

---

## 2. GenericDetailModal

Modal genérico para visualizar detalhes de um registro.

### Propriedades

| Prop | Tipo | Padrão | Descrição |
|------|------|--------|-----------|
| `show` | `boolean` | - | Controla visibilidade |
| `onClose` | `function` | - | Callback ao fechar |
| `title` | `string` | - | Título do modal |
| `resourceId` | `string \| number \| null` | `null` | ID do recurso |
| `fetchUrl` | `string \| null` | `null` | URL para buscar dados |
| `data` | `object \| null` | `null` | Dados já carregados |
| `sections` | `array` | `[]` | Seções de informações |
| `actions` | `array` | `[]` | Ações disponíveis |
| `maxWidth` | `string` | `'85vw'` | Largura máxima |
| `header` | `object \| null` | `null` | Configuração do cabeçalho |
| `renderHeader` | `function \| null` | `null` | Render customizado do cabeçalho |
| `emptyMessage` | `string` | `'Nenhum dado disponível'` | Mensagem quando vazio |

### Exemplo de Uso Básico

```jsx
import GenericDetailModal from '@/Components/GenericDetailModal';
import { useState } from 'react';

export default function MyPage() {
    const [showModal, setShowModal] = useState(false);
    const [selectedId, setSelectedId] = useState(null);

    const sections = [
        {
            title: 'Informações Pessoais',
            fields: [
                { name: 'name', label: 'Nome' },
                { name: 'email', label: 'E-mail' },
                {
                    name: 'birth_date',
                    label: 'Data de Nascimento',
                    type: 'date',
                },
            ],
        },
        {
            title: 'Informações Profissionais',
            fields: [
                { name: 'position', label: 'Cargo' },
                {
                    name: 'status',
                    label: 'Status',
                    type: 'badge',
                    getBadgeConfig: (value) => ({
                        label: value === 'active' ? 'Ativo' : 'Inativo',
                        className: value === 'active'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800',
                    }),
                },
            ],
        },
    ];

    const actions = [
        {
            label: 'Editar',
            variant: 'warning',
            icon: ({ className }) => (
                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            ),
            onClick: (data) => {
                // Abrir modal de edição
            },
        },
    ];

    return (
        <>
            <button onClick={() => {
                setSelectedId(123);
                setShowModal(true);
            }}>
                Ver Detalhes
            </button>

            <GenericDetailModal
                show={showModal}
                onClose={() => setShowModal(false)}
                title="Detalhes do Registro"
                resourceId={selectedId}
                fetchUrl="/api/records"
                sections={sections}
                actions={actions}
            />
        </>
    );
}
```

### Tipos de Campos Suportados

#### Campo Simples
```javascript
{
    name: 'field_name',
    label: 'Campo',
}
```

#### Campo com Path Aninhado
```javascript
{
    path: 'user.address.city',
    label: 'Cidade',
}
```

#### Badge
```javascript
{
    name: 'status',
    label: 'Status',
    type: 'badge',
    getBadgeConfig: (value, data) => ({
        label: value,
        className: 'bg-blue-100 text-blue-800',
    }),
}
```

#### Boolean
```javascript
{
    name: 'is_active',
    label: 'Ativo',
    type: 'boolean',
    trueText: 'Sim',
    falseText: 'Não',
}
```

#### Data/Hora
```javascript
{
    name: 'created_at',
    label: 'Criado em',
    type: 'datetime', // ou 'date'
}
```

#### Moeda
```javascript
{
    name: 'price',
    label: 'Preço',
    type: 'currency',
}
```

#### Lista
```javascript
{
    name: 'tags',
    label: 'Tags',
    type: 'list',
}
```

#### Render Customizado
```javascript
{
    name: 'complex_data',
    label: 'Dados',
    render: (value, data) => (
        <div>
            {/* Seu componente customizado */}
        </div>
    ),
}
```

### Cabeçalho Customizado

```javascript
<GenericDetailModal
    // ... outras props
    header={{
        avatar: (data) => (
            <img
                src={data.avatar_url}
                alt={data.name}
                className="w-16 h-16 rounded-full"
            />
        ),
        title: (data) => data.name,
        subtitle: (data) => data.email,
        badges: (data) => (
            <>
                {data.is_active && (
                    <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        Ativo
                    </span>
                )}
            </>
        ),
    }}
/>
```

### Seção com Render Customizado

```javascript
{
    title: 'Estatísticas',
    fullWidth: true,
    render: (data) => (
        <div className="grid grid-cols-3 gap-4">
            <div className="text-center">
                <div className="text-2xl font-bold">{data.total_sales}</div>
                <div className="text-sm text-gray-600">Vendas</div>
            </div>
            {/* ... */}
        </div>
    ),
}
```

---

## Modais Específicos Existentes

Os seguintes modais já possuem implementações específicas e complexas:

- **EmployeeCreateModal** / **EmployeeEditModal** / **EmployeeModal** (Funcionários)
- **Pages/CreateModal** / **Pages/EditModal** / **Pages/ViewModal** (Páginas)

Estes modais podem continuar sendo usados devido à suas funcionalidades específicas (formatação de CPF, upload de imagens com preview, seletor de ícones FontAwesome, etc.).

---

## Quando Usar Cada Componente?

### Use GenericFormModal quando:
- ✅ Formulário simples a moderado
- ✅ Campos padrão (text, select, checkbox, etc.)
- ✅ Validação básica
- ✅ Criar e editar com mesma estrutura

### Use GenericDetailModal quando:
- ✅ Visualização somente leitura
- ✅ Exibição de dados formatados
- ✅ Ações contextuais
- ✅ Fetch automático de dados

### Crie modal específico quando:
- ❌ Lógica de negócio muito complexa
- ❌ Validações customizadas elaboradas
- ❌ Componentes muito específicos do domínio
- ❌ Interações complexas entre campos
