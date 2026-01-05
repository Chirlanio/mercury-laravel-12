import { useState, useEffect } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { ChevronDownIcon, ChevronRightIcon } from "@heroicons/react/24/outline";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";

export default function Sidebar({ isOpen, onClose }) {
    const { url } = usePage();
    const { hasPermission } = usePermissions();
    const [menuGroups, setMenuGroups] = useState({});
    const [expandedSubmenus, setExpandedSubmenus] = useState({});
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchMenus();
    }, []);

    // Expandir automaticamente o submenu que contém a rota ativa
    useEffect(() => {
        if (Object.keys(menuGroups).length > 0) {
            for (const menus of Object.values(menuGroups)) {
                for (const menu of menus) {
                    const dropdownItems = menu.dropdown_items || [];
                    if (dropdownItems.some(item => item.route && url.startsWith(item.route))) {
                        setExpandedSubmenus(prev => ({ ...prev, [menu.id]: true }));
                        return;
                    }
                }
            }
        }
    }, [menuGroups, url]);

    const fetchMenus = async () => {
        try {
            // Usar endpoint dinâmico baseado em access_level_pages
            const response = await fetch("/api/menus/dynamic-sidebar");

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Garantir que data é um objeto com as chaves esperadas
            const validatedData = {
                main: Array.isArray(data?.main) ? data.main : [],
                hr: Array.isArray(data?.hr) ? data.hr : [],
                utility: Array.isArray(data?.utility) ? data.utility : [],
                system: Array.isArray(data?.system) ? data.system : [],
            };

            setMenuGroups(validatedData);
        } catch (error) {
            console.error("Erro ao carregar menus:", error);
            // Em caso de erro, usar estrutura vazia mas válida
            setMenuGroups({
                main: [],
                hr: [],
                utility: [],
                system: [],
            });
        } finally {
            setLoading(false);
        }
    };

    const toggleSubmenu = (menuId) => {
        setExpandedSubmenus((prev) => ({
            ...prev,
            [menuId]: !prev[menuId],
        }));
    };

    const getMenuRoute = (menuName) => {
        const menuRoutes = {
            // Menu Principal
            Home: { route: "/dashboard", permission: null },
            Usuário: { route: "/users", permission: PERMISSIONS.VIEW_USERS },

            // Utilidades
            "FAQ's": {
                route: "/support",
                permission: PERMISSIONS.ACCESS_SUPPORT_PANEL,
            },

            // Sistema
            "Dashboard's": { route: "/dashboard", permission: null },
            Configurações: {
                route: "/admin",
                permission: PERMISSIONS.ACCESS_ADMIN_PANEL,
            },
            "Gerenciar Níveis": {
                route: "/access-levels",
                permission: PERMISSIONS.VIEW_USERS,
            },
            "Gerenciar Menus": {
                route: "/menus",
                permission: PERMISSIONS.VIEW_USERS,
            },
            "Gerenciar Páginas": {
                route: "/pages",
                permission: PERMISSIONS.VIEW_USERS,
            },
            "Logs de Atividade": {
                route: "/activity-logs",
                permission: PERMISSIONS.VIEW_ACTIVITY_LOGS,
            },
            "Configurações de Email": {
                route: "/admin/email-settings",
                permission: PERMISSIONS.MANAGE_SETTINGS,
            },

            // Páginas básicas
            Produto: { route: "/produto", permission: null },
            Planejamento: { route: "/planejamento", permission: null },
            Financeiro: { route: "/financeiro", permission: null },
            "Ativo Fixo": { route: "/ativo-fixo", permission: null },
            Comercial: { route: "/comercial", permission: null },
            Delivery: { route: "/delivery", permission: null },
            Rotas: { route: "/rotas", permission: null },
            "E-commerce": { route: "/ecommerce", permission: null },
            Qualidade: { route: "/qualidade", permission: null },
            "Pessoas & Cultura": {
                route: "/pessoas-cultura",
                permission: null,
            },
            "Departamento Pessoal": {
                route: "/departamento-pessoal",
                permission: null,
            },
            "Escola Digital": { route: "/escola-digital", permission: null },
            Movidesk: { route: "/movidesk", permission: null },
            "Biblioteca de Processos": {
                route: "/biblioteca-processos",
                permission: null,
            },
            Funcionários: {
                route: "/employees",
                permission: PERMISSIONS.VIEW_USERS,
            },
            "Controle de Jornada": {
                route: "/work-shifts",
                permission: PERMISSIONS.VIEW_USERS,
            },
        };
        return menuRoutes[menuName] || null;
    };

    const handleMenuClick = (menuName) => {
        const menuConfig = getMenuRoute(menuName);

        if (menuConfig) {
            // Verificar permissão se necessário
            if (
                !menuConfig.permission ||
                hasPermission(menuConfig.permission)
            ) {
                router.get(menuConfig.route);
            } else {
                console.warn(`Sem permissão para acessar "${menuName}"`);
                return;
            }
        } else {
            console.log(`Navegação para "${menuName}" ainda não implementada`);
        }

        // Fechar sidebar em dispositivos móveis
        if (window.innerWidth < 1024) {
            onClose();
        }
    };

    const handleLogout = () => {
        router.post("/logout");
    };

    if (loading) {
        return (
            <div
                className={`fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                } lg:translate-x-0 lg:static lg:inset-0`}
            >
                <div className="flex items-center justify-center h-full">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
            </div>
        );
    }

    return (
        <>
            {/* Overlay para dispositivos móveis */}
            {isOpen && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
                    onClick={onClose}
                ></div>
            )}

            {/* Sidebar */}
            <div
                className={`fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                } lg:translate-x-0 lg:static lg:inset-0`}
            >
                {/* Header da Sidebar */}
                <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200">
                    <h2 className="text-lg font-semibold text-gray-800">
                        Mercury
                    </h2>
                    <button
                        onClick={onClose}
                        className="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <svg
                            className="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                    {/* Menu Items - Exibidos diretamente sem agrupamento */}
                    {Object.values(menuGroups).flat().map((menu) => {
                        const directItems = menu.direct_items || [];
                        const dropdownItems = menu.dropdown_items || [];
                        const hasDropdown = dropdownItems.length > 0;

                        // Lógica de Ativação: O menu dropdown está ativo se um de seus filhos estiver ativo
                        const isDropdownActive = hasDropdown && dropdownItems.some(item => item.route && url.startsWith(item.route));

                        return (
                            <div key={menu.id}>
                                {/* Renderizar Itens Diretos */}
                                {directItems.map(item => (
                                    <button
                                        key={item.id}
                                        onClick={() => {
                                            if (item.route) {
                                                if (item.route === '/logout') handleLogout();
                                                else router.get(item.route);
                                                if (window.innerWidth < 1024) onClose();
                                            }
                                        }}
                                        className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${item.route && url.startsWith(item.route) ? "bg-indigo-50 text-indigo-700 font-medium" : ""}`}
                                    >
                                        <i className={`${item.icon || menu.icon} mr-3 flex-shrink-0 ${item.route && url.startsWith(item.route) ? "text-indigo-500" : "text-gray-400 group-hover:text-gray-500"}`}></i>
                                        <span className="truncate">{item.name}</span>
                                    </button>
                                ))}

                                {/* Renderizar Menu com Dropdown */}
                                {hasDropdown && (
                                    <>
                                        <button
                                            onClick={() => toggleSubmenu(menu.id)}
                                            className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${isDropdownActive ? "bg-indigo-50 text-indigo-700 font-medium" : ""}`}
                                        >
                                            <i className={`${menu.icon} mr-3 flex-shrink-0 ${isDropdownActive ? "text-indigo-500" : "text-gray-400 group-hover:text-gray-500"}`}></i>
                                            <span className="truncate">{menu.name}</span>
                                            {expandedSubmenus[menu.id] ? (
                                                <ChevronDownIcon className="ml-auto h-4 w-4" />
                                            ) : (
                                                <ChevronRightIcon className="ml-auto h-4 w-4" />
                                            )}
                                        </button>

                                        {expandedSubmenus[menu.id] && (
                                            <div className="ml-6 mt-1 space-y-1">
                                                {dropdownItems.map((item) => (
                                                    <button
                                                        key={item.id}
                                                        onClick={() => {
                                                            if (item.route) {
                                                                if (item.route === '/logout') handleLogout();
                                                                else router.get(item.route);
                                                                if (window.innerWidth < 1024) onClose();
                                                            }
                                                        }}
                                                        className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${item.route && url.startsWith(item.route) ? "bg-indigo-50 text-indigo-700 font-medium" : ""}`}
                                                    >
                                                        {item.icon && <i className={`${item.icon} mr-3 flex-shrink-0 text-sm ${item.route && url.startsWith(item.route) ? "text-indigo-500" : "text-gray-400 group-hover:text-gray-500"}`}></i>}
                                                        <span className="truncate text-sm">{item.name}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        );
                    })}
                </nav>

                {/* Footer */}
                <div className="border-t border-gray-200 p-4">
                    <div className="text-xs text-gray-500 text-center">
                        Mercury System v1.0
                    </div>
                </div>
            </div>
        </>
    );
}
