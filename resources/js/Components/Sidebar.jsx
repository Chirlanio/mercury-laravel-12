import { useState, useEffect } from "react";
import { Link, router } from "@inertiajs/react";
import { ChevronDownIcon, ChevronRightIcon } from "@heroicons/react/24/outline";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";

export default function Sidebar({ isOpen, onClose }) {
    const { hasPermission } = usePermissions();
    const [menuGroups, setMenuGroups] = useState({});
    const [expandedGroups, setExpandedGroups] = useState({
        navigation: true,
        main: true,
        hr: false,
        utility: false,
        system: false,
    });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchMenus();
    }, []);

    const fetchMenus = async () => {
        try {
            const response = await fetch("/api/menus/sidebar");
            const data = await response.json();
            setMenuGroups(data);
        } catch (error) {
            console.error("Erro ao carregar menus:", error);
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

    const getGroupTitle = (groupKey) => {
        const titles = {
            navigation: "Navegação",
            main: "Menu Principal",
            hr: "Recursos Humanos",
            utility: "Utilidades",
            system: "Sistema",
        };
        return titles[groupKey] || groupKey;
    };

    const getGroupColor = (groupKey) => {
        const colors = {
            navigation: "text-indigo-600",
            main: "text-blue-600",
            hr: "text-purple-600",
            utility: "text-green-600",
            system: "text-gray-600",
        };
        return colors[groupKey] || "text-gray-600";
    };

    const getMenuRoute = (menuName) => {
        const menuRoutes = {
            Home: "/dashboard",
            Usuário: "/users",
            "Dashboard's": "/dashboard",
            Configurações: "/admin",
            "FAQ's": "/support",
            Páginas: "/pages",
            "Níveis de Acesso": "/access-levels",
            // Adicione mais mapeamentos conforme necessário
        };
        return menuRoutes[menuName] || null;
    };

    const handleMenuClick = (menuName) => {
        const route = getMenuRoute(menuName);

        if (route) {
            router.get(route);
        } else {
            // Para menus sem rota definida, mostrar mensagem
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

    const getNavigationItems = () => {
        const items = [
            {
                id: "dashboard",
                name: "Dashboard",
                icon: "fas fa-tachometer-alt",
                route: "/dashboard",
                permission: null,
            },
            {
                id: "users",
                name: "Gerenciar Usuários",
                icon: "fas fa-users",
                route: "/users",
                permission: PERMISSIONS.VIEW_USERS,
            },
            {
                id: "admin",
                name: "Administração",
                icon: "fas fa-cogs",
                route: "/admin",
                permission: PERMISSIONS.ACCESS_ADMIN_PANEL,
            },
            {
                id: "support",
                name: "Suporte",
                icon: "fas fa-headset",
                route: "/support",
                permission: PERMISSIONS.ACCESS_SUPPORT_PANEL,
            },
            {
                id: "activity-logs",
                name: "Logs de Atividade",
                icon: "fas fa-list-alt",
                route: "/activity-logs",
                permission: PERMISSIONS.VIEW_ACTIVITY_LOGS,
            },
            {
                id: "pages",
                name: "Páginas",
                icon: "fas fa-file-alt",
                route: "/pages",
                permission: PERMISSIONS.VIEW_USERS,
            },
            {
                id: "access-levels",
                name: "Níveis de Acesso",
                icon: "fas fa-shield-alt",
                route: "/access-levels",
                permission: PERMISSIONS.VIEW_USERS,
            },
        ];

        return items.filter(
            (item) => !item.permission || hasPermission(item.permission)
        );
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
                    {/* Navigation Items */}
                    <div className="mb-4">
                        <button
                            onClick={() => toggleGroup('navigation')}
                            className="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                        >
                            <span className="text-indigo-600">Navegação</span>
                            {expandedGroups.navigation ? (
                                <ChevronDownIcon className="h-4 w-4" />
                            ) : (
                                <ChevronRightIcon className="h-4 w-4" />
                            )}
                        </button>

                        {expandedGroups.navigation && (
                            <div className="ml-4 mt-1 space-y-1">
                                {getNavigationItems().map((item) => (
                                    <Link
                                        key={item.id}
                                        href={item.route}
                                        className="flex items-center w-full px-3 py-2 text-sm rounded-md group text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                    >
                                        <i className={`${item.icon} mr-3 flex-shrink-0 text-gray-400 group-hover:text-gray-500`}></i>
                                        <span className="truncate">{item.name}</span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

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
                                                const hasRoute =
                                                    getMenuRoute(menu.name) ||
                                                    menu.name === "Sair";
                                                return (
                                                    <button
                                                        key={menu.id}
                                                        onClick={() =>
                                                            menu.name === "Sair"
                                                                ? handleLogout()
                                                                : handleMenuClick(
                                                                      menu.name
                                                                  )
                                                        }
                                                        className={`flex items-center w-full px-3 py-2 text-sm rounded-md group ${
                                                            hasRoute
                                                                ? "text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer"
                                                                : "text-gray-400 cursor-not-allowed"
                                                        }`}
                                                        disabled={!hasRoute}
                                                    >
                                                        <i
                                                            className={`${
                                                                menu.icon
                                                            } mr-3 flex-shrink-0 ${
                                                                hasRoute
                                                                    ? "text-gray-400 group-hover:text-gray-500"
                                                                    : "text-gray-300"
                                                            }`}
                                                        ></i>
                                                        <span className="truncate">
                                                            {menu.name}
                                                        </span>
                                                        {hasRoute && (
                                                            <svg
                                                                className="ml-auto h-4 w-4 opacity-0 group-hover:opacity-100 transition-opacity"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                    strokeWidth={
                                                                        2
                                                                    }
                                                                    d="M9 5l7 7-7 7"
                                                                />
                                                            </svg>
                                                        )}
                                                    </button>
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
