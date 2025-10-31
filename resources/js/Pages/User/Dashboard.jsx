import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';

function StatCard({ label, value, trend, variant }) {
    const variants = {
        neutral: 'bg-gray-100 text-gray-700',
        warning: 'bg-amber-100 text-amber-700',
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p className="text-sm font-medium text-gray-500">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-gray-900">{value}</p>
            {trend && (
                <span className={`mt-4 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${variants[variant ?? 'neutral']}`}>
                    {trend}
                </span>
            )}
        </div>
    );
}

function UserDashboardPage() {
    const { stats, accountStatus, tasks } = usePage().props;

    return (
        <>
            <Head title="Panel użytkownika" />
            <div className="space-y-6">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {stats?.map((stat, index) => (
                        <StatCard key={index} {...stat} />
                    ))}
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <p className="text-sm font-medium text-gray-500">Status konta</p>
                        <span className="mt-4 inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-600">
                            <span className="mr-2 h-2 w-2 rounded-full bg-green-500" />
                            {accountStatus}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-gray-900">Nadchodzące zadania</h3>
                        <ul className="mt-4 space-y-4 text-sm text-gray-600">
                            {tasks?.map((task, index) => (
                                <li key={index} className="flex justify-between gap-4">
                                    <div>
                                        <p className="font-medium text-gray-900">{task.title}</p>
                                        <p className="text-xs text-gray-500">{task.description}</p>
                                    </div>
                                    <span className={`inline-flex h-fit rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-600`}>
                                        {task.eta}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-gray-900">Szybkie akcje</h3>
                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Button variant="outline" asChild>
                                <a href="/profile">Przegląd profilu</a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href="/settings">Ustawienia konta</a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href="/support">Pomoc i wsparcie</a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href="/support/tickets">Zgłoś problem</a>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

UserDashboardPage.layout = (page) => <DashboardLayout title="Panel użytkownika">{page}</DashboardLayout>;

export default UserDashboardPage;
