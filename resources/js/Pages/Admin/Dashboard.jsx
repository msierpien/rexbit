import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';

function StatCard({ label, value, trend, trendVariant }) {
    const colors = {
        success: 'text-green-600 bg-green-100',
        warning: 'text-amber-600 bg-amber-100',
        neutral: 'text-gray-600 bg-gray-100',
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p className="text-sm font-medium text-gray-500">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-gray-900">{value}</p>
            {trend && (
                <span
                    className={`mt-4 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                        colors[trendVariant ?? 'neutral']
                    }`}
                >
                    {trend}
                </span>
            )}
        </div>
    );
}

function Card({ title, subtitle, children }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                {subtitle && <p className="text-xs text-gray-500">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

function AdminDashboardPage() {
    const { stats, latestLogins, roleStats } = usePage().props;

    return (
        <>
            <Head title="Panel administratora" />
            <div className="space-y-6">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {stats?.map((stat, index) => (
                        <StatCard key={index} {...stat} />
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card title="Ostatnie logowania" subtitle="Monitoruj aktywność administratorów i użytkowników">
                        <ul className="space-y-3 text-sm text-gray-600">
                            {latestLogins?.map((login, index) => (
                                <li key={index} className="flex items-center justify-between">
                                    <span className="font-medium text-gray-900">{login.name}</span>
                                    <span className="text-xs text-gray-500">{login.time}</span>
                                </li>
                            ))}
                            {latestLogins?.length === 0 && (
                                <li className="text-xs text-gray-400">Brak danych logowań.</li>
                            )}
                        </ul>
                    </Card>

                    <Card title="Statystyki ról" subtitle="Rozkład uprawnień w systemie">
                        <div className="space-y-4">
                            {roleStats?.map((stat, index) => (
                                <div key={index}>
                                    <div className="flex items-center justify-between text-sm font-medium text-gray-600">
                                        <span>{stat.label}</span>
                                        <span>{stat.count}</span>
                                    </div>
                                    <div className="mt-2 h-2 rounded-full bg-gray-200">
                                        <div
                                            className="h-2 rounded-full bg-blue-500"
                                            style={{ width: `${stat.progress}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 className="text-sm font-semibold text-gray-900">Zarządzanie</h3>
                    <p className="mt-1 text-xs text-gray-500">
                        Szybkie linki do najważniejszych akcji dla administratora.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <Button asChild variant="outline">
                            <a href="/admin/users">Zarządzaj użytkownikami</a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href="/integrations">Integracje</a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href="/warehouse/settings">Ustawienia magazynu</a>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

AdminDashboardPage.layout = (page) => <DashboardLayout title="Panel administratora">{page}</DashboardLayout>;

export default AdminDashboardPage;
