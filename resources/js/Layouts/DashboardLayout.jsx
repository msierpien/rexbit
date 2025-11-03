import { Link, router, usePage, Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Toaster } from 'sonner';

const NAVIGATION_GROUPS = [
    {
        label: 'Dashboard',
        items: [
            { label: 'Panel użytkownika', href: '/dashboard', match: ['/dashboard'], roles: ['admin', 'user'] },
            { label: 'Panel administratora', href: '/admin/dashboard', match: ['/admin'], roles: ['admin'] },
        ],
    },
    {
        label: 'Produkty',
        items: [
            { label: 'Katalogi', href: '/product-catalogs', match: ['/product-catalogs'], roles: ['admin', 'user'] },
            { label: 'Lista produktów', href: '/products', match: ['/products'], roles: ['admin', 'user'] },
            { label: 'Kategorie', href: '/product-categories', match: ['/product-categories'], roles: ['admin', 'user'] },
            { label: 'Ustawienia', href: '/products/settings', match: ['/products/settings'], roles: ['admin', 'user'] },
        ],
    },
    {
        label: 'Magazyn',
        items: [
            {
                label: 'Dokumenty',
                href: '/warehouse/documents',
                match: ['/warehouse/documents'],
                roles: ['admin', 'user'],
            },
            {
                label: 'Dostawy',
                href: '/warehouse/deliveries',
                match: ['/warehouse/deliveries'],
                roles: ['admin', 'user'],
            },
            {
                label: 'Ustawienia',
                href: '/warehouse/settings',
                match: ['/warehouse/settings'],
                roles: ['admin', 'user'],
            },
            {
                label: 'Kontrahenci',
                href: '/warehouse/contractors',
                match: ['/warehouse/contractors'],
                roles: ['admin', 'user'],
            },
        ],
    },
    {
        label: 'Integracje',
        items: [
            { label: 'Integracje', href: '/integrations', match: ['/integrations'], roles: ['admin', 'user'] },
            { label: 'Historia zadań', href: '/task-runs', match: ['/task-runs'], roles: ['admin', 'user'] },
        ],
    },
    {
        label: 'Użytkownicy',
        items: [
            { label: 'Zarządzanie użytkownikami', href: '/admin/users', match: ['/admin/users'], roles: ['admin'] },
        ],
    },
];

export default function DashboardLayout({ title, children }) {
    const { props, url } = usePage();
    const { auth, flash, app } = props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const role = auth?.user?.role ?? 'user';

    const navigationItems = useMemo(() => {
        return NAVIGATION_GROUPS.map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.roles || item.roles.includes(role)),
        }));
    }, [role]);

    const isActive = (item) => item.match.some((segment) => (segment === '/' ? url === '/' : url.startsWith(segment)));

    const handleLogout = (event) => {
        event.preventDefault();
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-gray-100 text-gray-900">
            <Head title={title ? `${title} – ${app?.name ?? 'RexBit'}` : app?.name ?? 'RexBit'} />
            <div className="flex min-h-screen">
                <aside
                    className={`${sidebarOpen ? 'block' : 'hidden'} md:block md:w-72 shrink-0 bg-white border-r border-gray-200 dark:bg-gray-900 dark:border-gray-800`}
                >
                    <div className="flex items-center justify-between px-6 py-6 border-b border-gray-200 dark:border-gray-800">
                        <Link href="/dashboard" className="text-2xl font-bold text-blue-600">
                            {app?.name ?? 'RexBit'}
                        </Link>
                        <button
                            type="button"
                            className="md:hidden rounded-md p-2 text-gray-500 hover:bg-gray-100"
                            onClick={() => setSidebarOpen(false)}
                            aria-label="Zamknij menu"
                        >
                            <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div className="px-3 py-6">
                        <Accordion type="single" collapsible className="space-y-2">
                            {navigationItems.map((group) => (
                                <AccordionItem key={group.label} value={group.label} className="border-none">
                                    <AccordionTrigger className="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:hover:bg-gray-800">
                                        {group.label}
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <div className="flex flex-col gap-2">
                                            {group.items.map((item) => (
                                                <Link
                                                    key={item.href}
                                                    href={item.href}
                                                    className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
                                                        isActive(item)
                                                            ? 'bg-blue-100 text-blue-700'
                                                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                                    }`}
                                                    onClick={() => setSidebarOpen(false)}
                                                >
                                                    {item.label}
                                                </Link>
                                            ))}
                                        </div>
                                    </AccordionContent>
                                </AccordionItem>
                            ))}
                        </Accordion>
                    </div>
                    <div className="px-6 py-6 border-t border-gray-200 dark:border-gray-800 text-sm">
                        <div className="font-semibold text-gray-900 dark:text-gray-100">{auth?.user?.name}</div>
                        <div className="text-gray-500 dark:text-gray-400">{auth?.user?.email}</div>
                        <div className="text-xs text-gray-400 dark:text-gray-500">Rola: {role}</div>
                    </div>
                </aside>
                <div className="flex-1 flex flex-col">
                    <header className="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                className="md:hidden rounded-md p-2 text-gray-500 hover:bg-gray-100"
                                onClick={() => setSidebarOpen(!sidebarOpen)}
                                aria-label="Menu"
                            >
                                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25H12" />
                                </svg>
                            </button>
                            <div>
                                <h1 className="text-lg md:text-xl font-semibold text-gray-900 dark:text-gray-100">{title}</h1>
                                {auth?.has_unread_notifications && (
                                    <p className="text-xs text-blue-500">Masz nowe powiadomienia.</p>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <Link
                                href="/notifications"
                                className="rounded-md border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-100"
                            >
                                Powiadomienia
                            </Link>
                            <button
                                type="button"
                                className="rounded-md bg-red-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-600"
                                onClick={handleLogout}
                            >
                                Wyloguj
                            </button>
                        </div>
                    </header>
                    <main className="flex-1 p-6">
                        {flash?.status && (
                            <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                {flash.status}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {flash.error}
                            </div>
                        )}
                        {children}
                    </main>
                </div>
            </div>
            <Toaster position="top-right" richColors />
        </div>
    );
}
