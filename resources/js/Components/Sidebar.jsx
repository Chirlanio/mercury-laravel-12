import { useState, useEffect } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { ChevronDownIcon, ChevronRightIcon } from "@heroicons/react/24/outline";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";

export default function Sidebar({ isOpen, onClose }) {
    const { url } = usePage();
    const { hasPermission } = usePermissions();
    const [menuGroups, setMenuGroups] = useState({});
    const [expandedGroups, setExpandedGroups] = useState({});
    const [expandedSubmenus, setExpandedSubmenus] = useState({});
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchMenus();
    }, []);

    useEffect(() => {
        if (Object.keys(menuGroups).length > 0) {
            let activeGroup = null;
            let activeSubmenu = null;

            for (const [groupKey, menus] of Object.entries(menuGroups)) {
                for (const menu of menus) {
                    const menuConfig = getMenuRoute(menu.name);
                    if (menuConfig && url.startsWith(menuConfig.route)) {
                        activeGroup = groupKey;
                        break;
                    }

                    // Suportar ambas as estruturas: items (nova) e children (antiga)
                    const menuItems = menu.items || menu.children || [];

                    if (menuItems.length > 0) {
                        for (const item of menuItems) {
                            // Nova estrutura tem 'route' diretamente, antiga precisa de fallback
                            const itemRoute = item.route || (item.name === "Sair" ? "/logout" : getMenuRoute(item.name)?.route);
                            if (itemRoute && itemRoute !== "/logout" && url.startsWith(itemRoute)) {
                                activeGroup = groupKey;
                                activeSubmenu = menu.id;
                                break;
                            }
                        }
                    }
                    if (activeGroup) break;
                }
                if (activeGroup) break;
            }

            if (activeGroup) {
                setExpandedGroups({ [activeGroup]: true });
                if (activeSubmenu) {
                    setExpandedSubmenus({ [activeSubmenu]: true });
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

    const toggleGroup = (groupKey) => {
        setExpandedGroups((prev) => ({
            ...prev,
            [groupKey]: !prev[groupKey],
        }));
    };

    const toggleSubmenu = (menuId) => {
        setExpandedSubmenus((prev) => ({
            ...prev,
            [menuId]: !prev[menuId],
        }));
    };

    const getGroupTitle = (groupKey) => {
        const titles = {
            main: "Menu Principal",
            hr: "Recursos Humanos",
            utility: "Utilidades",
            system: "Sistema",
        };
        return titles[groupKey] || groupKey;
    };

    const getGroupColor = (groupKey) => {
        const colors = {
            main: "text-blue-600",
            hr: "text-purple-600",
            utility: "text-green-600",
            system: "text-gray-600",
        };
        return colors[groupKey] || "text-gray-600";
    };

    const getMenuRoute = (menuName) => {
        const menuRoutes = {
            // Menu Principal
            Home: { route: "/dashboard", permission: null },
            Usuário: { route: "/users", permission: PERMISSIONS.VIEW_USERS },

            // Utilidades
            "FAQ's": { route: "/support", permission: PERMISSIONS.ACCESS_SUPPORT_PANEL },

            // Sistema
            "Dashboard's": { route: "/dashboard", permission: null },
            Configurações: { route: "/admin", permission: PERMISSIONS.ACCESS_ADMIN_PANEL },
            "Gerenciar Níveis": { route: "/access-levels", permission: PERMISSIONS.VIEW_USERS },
            "Gerenciar Menus": { route: "/menus", permission: PERMISSIONS.VIEW_USERS },
            "Gerenciar Páginas": { route: "/pages", permission: PERMISSIONS.VIEW_USERS },
            "Logs de Atividade": { route: "/activity-logs", permission: PERMISSIONS.VIEW_ACTIVITY_LOGS },
            "Configurações de Email": { route: "/admin/email-settings", permission: PERMISSIONS.MANAGE_SETTINGS },

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
            "Pessoas & Cultura": { route: "/pessoas-cultura", permission: null },
            "Departamento Pessoal": { route: "/departamento-pessoal", permission: null },
            "Escola Digital": { route: "/escola-digital", permission: null },
            Movidesk: { route: "/movidesk", permission: null },
            "Biblioteca de Processos": { route: "/biblioteca-processos", permission: null },
            "Funcionários": { route: "/employees", permission: PERMISSIONS.VIEW_USERS },
            "Controle de Jornada": { route: "/work-shifts", permission: PERMISSIONS.VIEW_USERS },
        };
        return menuRoutes[menuName] || null;
    };

    const handleMenuClick = (menuName) => {
        const menuConfig = getMenuRoute(menuName);

        if (menuConfig) {
            // Verificar permissão se necessário
            if (!menuConfig.permission || hasPermission(menuConfig.permission)) {
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

                    {/* Menu Groups from API */}
                    {Object.entries(menuGroups).map(
                        ([groupKey, menus]) =>
                            menus.length > 0 && (
                                <div key={groupKey} className="mb-4">
                                    {/* Group Header */}
                                    <button
                                        onClick={() => toggleGroup(groupKey)}
                                        className="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                                    >
                                        <span
                                            className={getGroupColor(groupKey)}
                                        >
                                            {getGroupTitle(groupKey)}
                                        </span>
                                        {expandedGroups[groupKey] ? (
                                            <ChevronDownIcon className="h-4 w-4" />
                                        ) : (
                                            <ChevronRightIcon className="h-4 w-4" />
                                        )}
                                    </button>

                                    {/* Group Items */}
                                    {expandedGroups[groupKey] && (
                                        <div className="ml-4 mt-1 space-y-1">
                                            {menus.map((menu) => {
                                                // Nova estrutura com direct_items e dropdown_items
                                                const directItems = menu.direct_items || [];
                                                const dropdownItems = menu.dropdown_items || [];

                                                // Fallback para estrutura antiga
                                                const menuItems = menu.items || menu.children || [];
                                                const hasOldStructure = menuItems.length > 0 && !menu.direct_items && !menu.dropdown_items;

                                                // Estrutura antiga - manter comportamento compatível
                                                if (hasOldStructure) {
                                                    const menuConfig = getMenuRoute(menu.name);
                                                    const hasRoute = menuConfig || menu.name === "Sair";
                                                    const hasMenuPermission = !menuConfig?.permission || hasPermission(menuConfig.permission);
                                                    const isAccessible = hasRoute && (menu.name === "Sair" || hasMenuPermission);

                                                    const accessibleItems = menuItems.filter(item => {
                                                        const itemConfig = getMenuRoute(item.name);
                                                        const hasItemPermission = !itemConfig?.permission || hasPermission(itemConfig.permission);
                                                        return item.name === "Sair" || hasItemPermission;
                                                    });

                                                    if (!isAccessible && accessibleItems.length === 0) {
                                                        return null;
                                                    }

                                                    return (
                                                        <div key={menu.id}>
                                                            <button
                                                                onClick={() => {
                                                                    if (accessibleItems.length > 0) {
                                                                        toggleSubmenu(menu.id);
                                                                    } else if (menu.name === "Sair") {
                                                                        handleLogout();
                                                                    } else {
                                                                        handleMenuClick(menu.name);
                                                                    }
                                                                }}
                                                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${
                                                                    menuConfig && url.startsWith(menuConfig.route)
                                                                        ? "bg-gray-100 text-gray-900"
                                                                        : ""
                                                                }`}
                                                            >
                                                                <i className={`${menu.icon} mr-3 flex-shrink-0 text-gray-400 group-hover:text-gray-500`}></i>
                                                                <span className="truncate">{menu.name}</span>
                                                                {accessibleItems.length > 0 ? (
                                                                    expandedSubmenus[menu.id] ? (
                                                                        <ChevronDownIcon className="ml-auto h-4 w-4" />
                                                                    ) : (
                                                                        <ChevronRightIcon className="ml-auto h-4 w-4" />
                                                                    )
                                                                ) : (
                                                                    <svg className="ml-auto h-4 w-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                                    </svg>
                                                                )}
                                                            </button>

                                                            {accessibleItems.length > 0 && expandedSubmenus[menu.id] && (
                                                                <div className="ml-6 mt-1 space-y-1">
                                                                    {accessibleItems.map((item) => {
                                                                        const itemRoute = item.route || (item.name === "Sair" ? "/logout" : getMenuRoute(item.name)?.route);
                                                                        return (
                                                                            <button
                                                                                key={item.id}
                                                                                onClick={() => {
                                                                                    if (item.name === "Sair" || itemRoute === "/logout") {
                                                                                        handleLogout();
                                                                                    } else if (itemRoute) {
                                                                                        router.get(itemRoute);
                                                                                        if (window.innerWidth < 1024) {
                                                                                            onClose();
                                                                                        }
                                                                                    }
                                                                                }}
                                                                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${
                                                                                    itemRoute && url.startsWith(itemRoute) ? "bg-gray-100 text-gray-900" : ""
                                                                                }`}
                                                                            >
                                                                                {item.icon && <i className={`${item.icon} mr-3 flex-shrink-0 text-sm text-gray-400 group-hover:text-gray-500`}></i>}
                                                                                <span className="truncate text-sm">{item.name}</span>
                                                                            </button>
                                                                        );
                                                                    })}
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                }

                                                // Nova estrutura - renderizar direct_items e dropdown_items separadamente
                                                return (
                                                    <div key={menu.id}>
                                                        {/* Renderizar direct items diretamente (sem dropdown) */}
                                                        {directItems.map((item) => (
                                                            <button
                                                                key={item.id}
                                                                onClick={() => {
                                                                    if (item.name === "Sair" || item.route === "/logout") {
                                                                        handleLogout();
                                                                    } else if (item.route) {
                                                                        router.get(item.route);
                                                                        if (window.innerWidth < 1024) {
                                                                            onClose();
                                                                        }
                                                                    }
                                                                }}
                                                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${
                                                                    item.route && url.startsWith(item.route) ? "bg-gray-100 text-gray-900" : ""
                                                                }`}
                                                            >
                                                                {item.icon && <i className={`${item.icon} mr-3 flex-shrink-0 text-gray-400 group-hover:text-gray-500`}></i>}
                                                                <span className="truncate">{item.name}</span>
                                                                <svg className="ml-auto h-4 w-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                                </svg>
                                                            </button>
                                                        ))}

                                                        {/* Renderizar menu com dropdown items (se existir) */}
                                                        {dropdownItems.length > 0 && (
                                                            <>
                                                                <button
                                                                    onClick={() => toggleSubmenu(menu.id)}
                                                                    className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer`}
                                                                >
                                                                    <i className={`${menu.icon} mr-3 flex-shrink-0 text-gray-400 group-hover:text-gray-500`}></i>
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
                                                                                    if (item.name === "Sair" || item.route === "/logout") {
                                                                                        handleLogout();
                                                                                    } else if (item.route) {
                                                                                        router.get(item.route);
                                                                                        if (window.innerWidth < 1024) {
                                                                                            onClose();
                                                                                        }
                                                                                    }
                                                                                }}
                                                                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer ${
                                                                                    item.route && url.startsWith(item.route) ? "bg-gray-100 text-gray-900" : ""
                                                                                }`}
                                                                            >
                                                                                {item.icon && <i className={`${item.icon} mr-3 flex-shrink-0 text-sm text-gray-400 group-hover:text-gray-500`}></i>}
                                                                                <span className="truncate text-sm">{item.name}</span>
                                                                                <svg className="ml-auto h-3 w-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                                                </svg>
                                                                            </button>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )
                    )}
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
