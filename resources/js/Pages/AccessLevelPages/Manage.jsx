import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';

export default function Manage({ accessLevel, menu, pages = [] }) {
    const { flash } = usePage().props;
    const [pagesData, setPagesData] = useState(
        (pages || []).map((page, index) => ({
            page_id: page.id,
            page_name: page.page_name,
            menu_controller: page.menu_controller,
            menu_method: page.menu_method,
            icon: page.icon,
            permission: page.has_permission,
            order: page.order,
            dropdown: page.dropdown,
            lib_menu: page.lib_menu,
        }))
    );

    const { post, processing } = useForm();

    const handleChange = (index, field, value) => {
        const newData = [...pagesData];
        newData[index] = {
            ...newData[index],
            [field]: value,
        };
        setPagesData(newData);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        post(
            route('access-levels.menus.pages.update', {
                accessLevel: accessLevel.id,
                menu: menu.id,
            }),
            {
                pages: pagesData,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Opcional: adicionar feedback de sucesso
                },
            }
        );
    };

    const handleSelectAll = () => {
        setPagesData(
            pagesData.map((page) => ({
                ...page,
                permission: true,
            }))
        );
    };

    const handleDeselectAll = () => {
        setPagesData(
            pagesData.map((page) => ({
                ...page,
                permission: false,
            }))
        );
    };

    if (!accessLevel || !menu) {
        return (
            <AuthenticatedLayout>
                <Head title="Erro" />
                <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <p>Erro ao carregar dados. Por favor, tente novamente.</p>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout>
            <Head title={`Gerenciar Páginas - ${menu.name} - ${accessLevel.name}`} />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Mensagens de Sucesso/Erro */}
                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-100 p-4 text-green-700 dark:bg-green-900 dark:text-green-100">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="mb-4 rounded-lg bg-red-100 p-4 text-red-700 dark:bg-red-900 dark:text-red-100">
                        {flash.error}
                    </div>
                )}

                {/* Header */}
                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div className="p-6 text-gray-900 dark:text-gray-100">
                        <div className="mb-4">
                            <h1 className="text-2xl font-semibold">
                                Gerenciar Páginas do Menu
                            </h1>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Nível de Acesso:{' '}
                                <span className="font-semibold">
                                    {accessLevel.name}
                                </span>
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Menu:{' '}
                                <span className="font-semibold">
                                    {menu.icon && (
                                        <i className={`${menu.icon} mr-1`}></i>
                                    )}
                                    {menu.name}
                                </span>
                            </p>
                        </div>

                        <div className="mb-4 flex gap-2">
                            <Button
                                onClick={handleSelectAll}
                                variant="secondary"
                                size="sm"
                            >
                                Selecionar Todas
                            </Button>
                            <Button
                                onClick={handleDeselectAll}
                                variant="secondary"
                                size="sm"
                            >
                                Desmarcar Todas
                            </Button>
                        </div>

                        {/* Formulário */}
                        <form onSubmit={handleSubmit}>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Página
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Rota
                                            </th>
                                            <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Permissão
                                            </th>
                                            <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Ordem
                                            </th>
                                            <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Dropdown
                                            </th>
                                            <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Exibir no Menu
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        {pagesData.map((page, index) => (
                                            <tr
                                                key={page.page_id}
                                                className={
                                                    page.permission
                                                        ? 'bg-blue-50 dark:bg-blue-900/20'
                                                        : ''
                                                }
                                            >
                                                <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {page.icon && (
                                                        <i
                                                            className={`${page.icon} mr-2`}
                                                        ></i>
                                                    )}
                                                    {page.page_name}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                                    {page.menu_controller}/
                                                    {page.menu_method}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            page.permission
                                                        }
                                                        onChange={(e) =>
                                                            handleChange(
                                                                index,
                                                                'permission',
                                                                e.target.checked
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                    />
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <input
                                                        type="number"
                                                        value={page.order}
                                                        onChange={(e) =>
                                                            handleChange(
                                                                index,
                                                                'order',
                                                                parseInt(
                                                                    e.target
                                                                        .value
                                                                ) || 1
                                                            )
                                                        }
                                                        disabled={
                                                            !page.permission
                                                        }
                                                        min="1"
                                                        className="w-20 rounded-md border-gray-300 text-center shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                    />
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={page.dropdown}
                                                        onChange={(e) =>
                                                            handleChange(
                                                                index,
                                                                'dropdown',
                                                                e.target.checked
                                                            )
                                                        }
                                                        disabled={
                                                            !page.permission
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                                    />
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={page.lib_menu}
                                                        onChange={(e) =>
                                                            handleChange(
                                                                index,
                                                                'lib_menu',
                                                                e.target.checked
                                                            )
                                                        }
                                                        disabled={
                                                            !page.permission
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-6 flex items-center justify-between">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => window.history.back()}
                                >
                                    Voltar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    loading={processing}
                                >
                                    Salvar Permissões
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Legenda */}
                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div className="p-6 text-gray-900 dark:text-gray-100">
                        <h3 className="mb-3 text-lg font-semibold">
                            Legenda dos Campos
                        </h3>
                        <dl className="space-y-2 text-sm">
                            <div>
                                <dt className="font-semibold">Permissão:</dt>
                                <dd className="text-gray-600 dark:text-gray-400">
                                    Define se o usuário com este nível de acesso
                                    pode visualizar esta página neste menu.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-semibold">Ordem:</dt>
                                <dd className="text-gray-600 dark:text-gray-400">
                                    Define a ordem de exibição da página dentro
                                    do menu (menor número aparece primeiro).
                                </dd>
                            </div>
                            <div>
                                <dt className="font-semibold">Dropdown:</dt>
                                <dd className="text-gray-600 dark:text-gray-400">
                                    Define se esta página deve ser exibida como
                                    um item dropdown no menu.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-semibold">
                                    Exibir no Menu:
                                </dt>
                                <dd className="text-gray-600 dark:text-gray-400">
                                    Define se esta página deve aparecer no menu
                                    da sidebar (lib_menu). Se desmarcado, o
                                    usuário terá permissão mas a página não
                                    aparecerá no menu.
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
