import { useState, useEffect, useRef, useCallback } from "react";
import { createPortal } from "react-dom";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronLeftIcon,
    Bars3Icon,
} from "@heroicons/react/24/outline";
const SIDEBAR_COLLAPSED_KEY = "sidebar_collapsed";

export default function Sidebar({ isOpen, onClose, collapsed, onToggleCollapse }) {
    const { url } = usePage();
    const [menuGroups, setMenuGroups] = useState({});
    const [expandedSubmenus, setExpandedSubmenus] = useState({});
    const [loading, setLoading] = useState(true);
    const [flyoutMenuId, setFlyoutMenuId] = useState(null);
    const [flyoutPosition, setFlyoutPosition] = useState({ top: 0, left: 0 });
    const flyoutRef = useRef(null);
    const triggerRefs = useRef({});

    useEffect(() => {
        fetchMenus();
    }, []);

    // Expandir automaticamente o submenu que contém a rota ativa
    useEffect(() => {
        if (Object.keys(menuGroups).length > 0 && !collapsed) {
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
    }, [menuGroups, url, collapsed]);

    // Fechar flyout ao clicar fora
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (!flyoutMenuId) return;
            const triggerEl = triggerRefs.current[flyoutMenuId];
            if (
                flyoutRef.current &&
                !flyoutRef.current.contains(e.target) &&
                triggerEl &&
                !triggerEl.contains(e.target)
            ) {
                setFlyoutMenuId(null);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [flyoutMenuId]);

    const openFlyout = useCallback((menuId) => {
        if (flyoutMenuId === menuId) {
            setFlyoutMenuId(null);
            return;
        }
        const triggerEl = triggerRefs.current[menuId];
        if (triggerEl) {
            const rect = triggerEl.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const margin = 8;
            // Posição inicial: alinhado ao topo do botão
            let top = rect.top;
            // Se ultrapassar o limite inferior, empurrar para cima
            // Estimar altura máxima do flyout (limitado a 70vh)
            const maxFlyoutHeight = viewportHeight * 0.7;
            if (top + maxFlyoutHeight > viewportHeight - margin) {
                top = Math.max(margin, viewportHeight - maxFlyoutHeight - margin);
            }
            setFlyoutPosition({
                top,
                left: rect.right + 4,
            });
        }
        setFlyoutMenuId(menuId);
    }, [flyoutMenuId]);

    const fetchMenus = async () => {
        try {
            const response = await fetch("/api/menus/dynamic-sidebar");
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            const validatedData = {
                main: Array.isArray(data?.main) ? data.main : [],
                hr: Array.isArray(data?.hr) ? data.hr : [],
                utility: Array.isArray(data?.utility) ? data.utility : [],
                system: Array.isArray(data?.system) ? data.system : [],
            };
            setMenuGroups(validatedData);
        } catch (error) {
            console.error("Erro ao carregar menus:", error);
            setMenuGroups({ main: [], hr: [], utility: [], system: [] });
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

    const handleMenuClick = (menuName, directItems = []) => {
        // Menu items are already filtered by role + modules on the backend.
        // Navigate to the first direct item's route, or the first dropdown item's route.
        const firstItem = directItems[0];
        if (firstItem?.route) {
            if (firstItem.route === '/logout') {
                router.post('/logout');
            } else {
                router.get(firstItem.route);
            }
        }
        if (window.innerWidth < 1024) {
            onClose();
        }
    };

    const handleLogout = () => {
        router.post("/logout");
    };

    const handleItemClick = (item) => {
        if (item.route) {
            if (item.route === "/logout") handleLogout();
            else router.get(item.route);
            if (window.innerWidth < 1024) onClose();
        }
        setFlyoutMenuId(null);
    };

    const isItemActive = (item) => item.route && url.startsWith(item.route);

    const sidebarWidth = collapsed ? "w-16" : "w-64";

    // --- Renderização de itens ---

    const renderDirectItem = (item, menu) => {
        const active = isItemActive(item);

        if (collapsed) {
            return (
                <div key={item.id} className="relative group">
                    <button
                        onClick={() => handleItemClick(item)}
                        className={`flex items-center justify-center w-full p-2 rounded-md cursor-pointer ${
                            active
                                ? "bg-indigo-50 text-indigo-700"
                                : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        }`}
                        title={item.name}
                    >
                        <i
                            className={`${item.icon || menu.icon} flex-shrink-0 text-base ${
                                active ? "text-indigo-500" : "text-gray-400 group-hover:text-gray-500"
                            }`}
                        ></i>
                    </button>
                    {/* Tooltip */}
                    <div className="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-[60]">
                        {item.name}
                    </div>
                </div>
            );
        }

        return (
            <button
                key={item.id}
                onClick={() => handleItemClick(item)}
                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                    active
                        ? "bg-indigo-50 text-indigo-700 font-medium"
                        : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                }`}
            >
                <i
                    className={`${item.icon || menu.icon} mr-3 flex-shrink-0 ${
                        active ? "text-indigo-500" : "text-gray-400 group-hover:text-gray-500"
                    }`}
                ></i>
                <span className="truncate">{item.name}</span>
            </button>
        );
    };

    const renderDropdownMenu = (menu) => {
        const dropdownItems = menu.dropdown_items || [];
        const isDropdownActive = dropdownItems.some(
            (item) => item.route && url.startsWith(item.route)
        );

        if (collapsed) {
            return (
                <div key={`dropdown-${menu.id}`} className="relative group">
                    <button
                        ref={(el) => (triggerRefs.current[menu.id] = el)}
                        onClick={() => openFlyout(menu.id)}
                        className={`flex items-center justify-center w-full p-2 rounded-md cursor-pointer ${
                            isDropdownActive || flyoutMenuId === menu.id
                                ? "bg-indigo-50 text-indigo-700"
                                : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        }`}
                        title={menu.name}
                    >
                        <i
                            className={`${menu.icon} flex-shrink-0 text-base ${
                                isDropdownActive
                                    ? "text-indigo-500"
                                    : "text-gray-400 group-hover:text-gray-500"
                            }`}
                        ></i>
                    </button>

                    {/* Flyout menu via portal - renderizado fora da sidebar para evitar overflow clip */}
                    {flyoutMenuId === menu.id &&
                        createPortal(
                            <div
                                ref={flyoutRef}
                                className="fixed w-56 bg-white rounded-md shadow-lg border border-gray-200 py-1 z-[9999] max-h-[70vh] overflow-y-auto"
                                style={{
                                    top: flyoutPosition.top,
                                    left: flyoutPosition.left,
                                }}
                            >
                                <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                    {menu.name}
                                </div>
                                {dropdownItems.map((item) => (
                                    <button
                                        key={item.id}
                                        onClick={() => handleItemClick(item)}
                                        className={`flex items-center w-full px-3 py-2 text-sm cursor-pointer ${
                                            isItemActive(item)
                                                ? "bg-indigo-50 text-indigo-700 font-medium"
                                                : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                                        }`}
                                    >
                                        {item.icon && (
                                            <i
                                                className={`${item.icon} mr-2 flex-shrink-0 text-sm ${
                                                    isItemActive(item)
                                                        ? "text-indigo-500"
                                                        : "text-gray-400"
                                                }`}
                                            ></i>
                                        )}
                                        <span className="truncate">{item.name}</span>
                                    </button>
                                ))}
                            </div>,
                            document.body
                        )}

                    {/* Tooltip (só mostra se flyout não estiver aberto) */}
                    {flyoutMenuId !== menu.id && (
                        <div className="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-[60]">
                            {menu.name}
                        </div>
                    )}
                </div>
            );
        }

        // Expandido - comportamento original com accordion
        return (
            <div key={`dropdown-${menu.id}`}>
                <button
                    onClick={() => toggleSubmenu(menu.id)}
                    className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                        isDropdownActive
                            ? "bg-indigo-50 text-indigo-700 font-medium"
                            : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                    }`}
                >
                    <i
                        className={`${menu.icon} mr-3 flex-shrink-0 ${
                            isDropdownActive
                                ? "text-indigo-500"
                                : "text-gray-400 group-hover:text-gray-500"
                        }`}
                    ></i>
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
                                onClick={() => handleItemClick(item)}
                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                                    isItemActive(item)
                                        ? "bg-indigo-50 text-indigo-700 font-medium"
                                        : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                }`}
                            >
                                {item.icon && (
                                    <i
                                        className={`${item.icon} mr-3 flex-shrink-0 text-sm ${
                                            isItemActive(item)
                                                ? "text-indigo-500"
                                                : "text-gray-400 group-hover:text-gray-500"
                                        }`}
                                    ></i>
                                )}
                                <span className="truncate text-sm">{item.name}</span>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    // --- Loading state ---
    if (loading) {
        return (
            <>
                {/* Desktop sidebar placeholder */}
                <div
                    className={`hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 ${sidebarWidth} bg-white shadow-lg transition-all duration-300`}
                >
                    <div className="flex items-center justify-center h-full">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                </div>

                {/* Mobile sidebar */}
                <div
                    className={`lg:hidden fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                        isOpen ? "translate-x-0" : "-translate-x-full"
                    }`}
                >
                    <div className="flex items-center justify-center h-full">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                </div>
            </>
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

            {/* Desktop Sidebar */}
            <div
                className={`hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 ${sidebarWidth} bg-white shadow-lg transition-all duration-300`}
            >
                {/* Header */}
                <div className="flex items-center h-16 px-4 border-b border-gray-200">
                    {collapsed ? (
                        <button
                            onClick={onToggleCollapse}
                            className="w-full flex items-center justify-center p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 cursor-pointer"
                            title="Expandir menu"
                        >
                            <Bars3Icon className="h-6 w-6" />
                        </button>
                    ) : (
                        <>
                            <h2 className="text-lg font-semibold text-gray-800 flex-1">
                                Mercury
                            </h2>
                            <button
                                onClick={onToggleCollapse}
                                className="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 cursor-pointer"
                                title="Recolher menu"
                            >
                                <ChevronLeftIcon className="h-5 w-5" />
                            </button>
                        </>
                    )}
                </div>

                {/* Navigation */}
                <nav className={`flex-1 ${collapsed ? "px-2" : "px-4"} py-4 space-y-1 overflow-y-auto`}>
                    {Object.values(menuGroups)
                        .flat()
                        .map((menu) => {
                            const directItems = menu.direct_items || [];
                            const dropdownItems = menu.dropdown_items || [];
                            const hasDropdown = dropdownItems.length > 0;

                            return (
                                <div key={menu.id}>
                                    {directItems.map((item) => renderDirectItem(item, menu))}
                                    {hasDropdown && renderDropdownMenu(menu)}
                                </div>
                            );
                        })}
                </nav>

                {/* Footer */}
                <div className="border-t border-gray-200 p-4">
                    {collapsed ? (
                        <div className="text-xs text-gray-500 text-center" title="Mercury System v1.0">
                            v1.0
                        </div>
                    ) : (
                        <div className="text-xs text-gray-500 text-center">
                            Mercury System v1.0
                        </div>
                    )}
                </div>
            </div>

            {/* Mobile Sidebar - sempre expandido */}
            <div
                className={`lg:hidden fixed inset-y-0 left-0 z-50 w-[85vw] max-w-[16rem] bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                {/* Header */}
                <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200">
                    <h2 className="text-lg font-semibold text-gray-800">Mercury</h2>
                    <button
                        onClick={onClose}
                        className="p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                {/* Navigation - mobile sempre expandido */}
                <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                    {Object.values(menuGroups)
                        .flat()
                        .map((menu) => {
                            const directItems = menu.direct_items || [];
                            const dropdownItems = menu.dropdown_items || [];
                            const hasDropdown = dropdownItems.length > 0;
                            const isDropdownActive =
                                hasDropdown &&
                                dropdownItems.some((item) => item.route && url.startsWith(item.route));

                            return (
                                <div key={menu.id}>
                                    {directItems.map((item) => (
                                        <button
                                            key={item.id}
                                            onClick={() => handleItemClick(item)}
                                            className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                                                isItemActive(item)
                                                    ? "bg-indigo-50 text-indigo-700 font-medium"
                                                    : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                            }`}
                                        >
                                            <i
                                                className={`${item.icon || menu.icon} mr-3 flex-shrink-0 ${
                                                    isItemActive(item)
                                                        ? "text-indigo-500"
                                                        : "text-gray-400 group-hover:text-gray-500"
                                                }`}
                                            ></i>
                                            <span className="truncate">{item.name}</span>
                                        </button>
                                    ))}

                                    {hasDropdown && (
                                        <>
                                            <button
                                                onClick={() => toggleSubmenu(menu.id)}
                                                className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                                                    isDropdownActive
                                                        ? "bg-indigo-50 text-indigo-700 font-medium"
                                                        : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                                }`}
                                            >
                                                <i
                                                    className={`${menu.icon} mr-3 flex-shrink-0 ${
                                                        isDropdownActive
                                                            ? "text-indigo-500"
                                                            : "text-gray-400 group-hover:text-gray-500"
                                                    }`}
                                                ></i>
                                                <span className="truncate">{menu.name}</span>
                                                {expandedSubmenus[menu.id] ? (
                                                    <ChevronDownIcon className="ml-auto h-4 w-4" />
                                                ) : (
                                                    <ChevronRightIcon className="ml-auto h-4 w-4" />
                                                )}
                                            </button>

                                            {expandedSubmenus[menu.id] && (
                                                <div className="ml-6 mt-1 space-y-1">
                                                    {dropdownItems.map((subItem) => (
                                                        <button
                                                            key={subItem.id}
                                                            onClick={() => handleItemClick(subItem)}
                                                            className={`flex items-center w-full px-3 py-2 text-sm rounded-md group cursor-pointer ${
                                                                isItemActive(subItem)
                                                                    ? "bg-indigo-50 text-indigo-700 font-medium"
                                                                    : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                                            }`}
                                                        >
                                                            {subItem.icon && (
                                                                <i
                                                                    className={`${subItem.icon} mr-3 flex-shrink-0 text-sm ${
                                                                        isItemActive(subItem)
                                                                            ? "text-indigo-500"
                                                                            : "text-gray-400 group-hover:text-gray-500"
                                                                    }`}
                                                                ></i>
                                                            )}
                                                            <span className="truncate text-sm">
                                                                {subItem.name}
                                                            </span>
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
                    <div className="text-xs text-gray-500 text-center">Mercury System v1.0</div>
                </div>
            </div>
        </>
    );
}
