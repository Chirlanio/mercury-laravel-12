import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import {
    HomeIcon,
    BuildingOffice2Icon,
    CreditCardIcon,
    CubeIcon,
    Bars3BottomLeftIcon,
    ShieldCheckIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

const navigation = [
    { name: 'Dashboard', href: '/admin', icon: HomeIcon },
    { name: 'Tenants', href: '/admin/tenants', icon: BuildingOffice2Icon },
    { name: 'Planos', href: '/admin/plans', icon: CreditCardIcon },
    { name: 'Módulos', href: '/admin/modules', icon: CubeIcon },
    { name: 'Navegação', href: '/admin/navigation', icon: Bars3BottomLeftIcon },
    { name: 'Roles', href: '/admin/roles', icon: ShieldCheckIcon },
];

export default function CentralLayout({ children, title }) {
    const { props } = usePage();
    const user = props.auth?.user;
    const flash = props.flash;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warn(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const currentPath = window.location.pathname;

    return (
        <div className="min-h-screen bg-gray-50">
            <ToastContainer position="top-right" autoClose={4000} />

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="fixed inset-0 bg-gray-600/75" onClick={() => setSidebarOpen(false)} />
                    <div className="fixed inset-y-0 left-0 flex w-64 flex-col bg-indigo-900">
                        <SidebarContent currentPath={currentPath} onClose={() => setSidebarOpen(false)} />
                    </div>
                </div>
            )}

            {/* Desktop sidebar */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <div className="flex min-h-0 flex-1 flex-col bg-indigo-900">
                    <SidebarContent currentPath={currentPath} />
                </div>
            </div>

            {/* Main content */}
            <div className="lg:pl-64">
                {/* Top bar */}
                <div className="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        className="-m-2.5 p-2.5 text-gray-700 lg:hidden"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Bars3Icon className="h-6 w-6" />
                    </button>

                    <div className="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
                        <div className="flex flex-1 items-center">
                            {title && (
                                <h1 className="text-lg font-semibold text-gray-900">{title}</h1>
                            )}
                        </div>
                        <div className="flex items-center gap-x-4 lg:gap-x-6">
                            {user && (
                                <div className="flex items-center gap-3">
                                    <span className="text-sm text-gray-700">{user.name}</span>
                                    <span className="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                        {user.role}
                                    </span>
                                    <button
                                        onClick={() => router.post('/logout')}
                                        className="text-gray-400 hover:text-gray-500"
                                        title="Sair"
                                    >
                                        <ArrowRightOnRectangleIcon className="h-5 w-5" />
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <main className="py-6">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}

function SidebarContent({ currentPath, onClose }) {
    return (
        <>
            <div className="flex h-16 shrink-0 items-center px-6 justify-between">
                <span className="text-xl font-bold text-white">Mercury SaaS</span>
                {onClose && (
                    <button onClick={onClose} className="text-indigo-200 hover:text-white lg:hidden">
                        <XMarkIcon className="h-6 w-6" />
                    </button>
                )}
            </div>
            <nav className="flex flex-1 flex-col px-4 pb-4">
                <ul className="flex flex-1 flex-col gap-y-1">
                    {navigation.map((item) => {
                        const isActive = currentPath === item.href || (item.href !== '/admin' && currentPath.startsWith(item.href));
                        return (
                            <li key={item.name}>
                                <Link
                                    href={item.href}
                                    className={`group flex items-center gap-x-3 rounded-md p-2.5 text-sm font-medium leading-6 ${
                                        isActive
                                            ? 'bg-indigo-800 text-white'
                                            : 'text-indigo-200 hover:bg-indigo-800 hover:text-white'
                                    }`}
                                >
                                    <item.icon className="h-5 w-5 shrink-0" />
                                    {item.name}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </>
    );
}
