import { Head, Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    running: 'bg-blue-100 text-blue-800', 
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    queued: 'bg-gray-100 text-gray-800',
};

const statusLabels = {
    pending: 'Oczekuje',
    running: 'W trakcie',
    completed: 'Zakończone',
    failed: 'Błąd',
    queued: 'W kolejce',
};

export default function Show({ run }) {
    const samples = run.meta?.samples || [];
    const errors = run.meta?.errors || [];

    return (
        <DashboardLayout title={`Zadanie: ${run.task_name}`}>
            <Head title={`Zadanie: ${run.task_name}`} />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {run.integration_name} → {run.task_name}
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            ID zadania: {run.id}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/task-runs">← Powrót do listy</Link>
                    </Button>
                </div>

                {/* Status i podstawowe informacje */}
                <Card className="p-6">
                    <div className="flex items-center gap-3 mb-4">
                        <h2 className="text-lg font-medium">Status</h2>
                        <Badge className={statusColors[run.status]}>
                            {statusLabels[run.status]}
                        </Badge>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <span className="text-sm font-medium text-gray-500">Przetworzono</span>
                            <div className="text-2xl font-semibold text-gray-900">
                                {run.processed_count || 0}
                            </div>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-gray-500">Sukces</span>
                            <div className="text-2xl font-semibold text-green-600">
                                {run.success_count || 0}
                            </div>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-gray-500">Błędy</span>
                            <div className="text-2xl font-semibold text-red-600">
                                {run.failure_count || 0}
                            </div>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-gray-500">Procent sukcesu</span>
                            <div className="text-2xl font-semibold text-gray-900">
                                {run.processed_count > 0 
                                    ? Math.round((run.success_count / run.processed_count) * 100)
                                    : 0}%
                            </div>
                        </div>
                    </div>

                    {run.message && (
                        <div className="mt-4 p-3 bg-gray-50 rounded-md">
                            <p className="text-sm text-gray-700">{run.message}</p>
                        </div>
                    )}
                </Card>

                {/* Czasy wykonania */}
                <Card className="p-6">
                    <h2 className="text-lg font-medium mb-4">Czas wykonania</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <span className="text-sm font-medium text-gray-500">Rozpoczęto</span>
                            <div className="text-sm text-gray-900">
                                {run.started_at || 'Nie rozpoczęto'}
                            </div>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-gray-500">Zakończono</span>
                            <div className="text-sm text-gray-900">
                                {run.finished_at || 'Nie zakończono'}
                            </div>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-gray-500">Utworzono</span>
                            <div className="text-sm text-gray-900">
                                {run.created_at}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Przykłady przetworzonych produktów */}
                {samples.length > 0 && (
                    <Card className="p-6">
                        <h2 className="text-lg font-medium mb-4">
                            Przykłady przetworzonych produktów
                        </h2>
                        <div className="space-y-4">
                            {samples.map((sample, index) => (
                                <div key={index} className="border-l-4 border-green-400 bg-green-50 p-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span className="font-medium">SKU:</span> {sample.product?.sku}
                                        </div>
                                        <div>
                                            <span className="font-medium">Nazwa:</span> {sample.product?.name}
                                        </div>
                                        {sample.product?.sale_price_net && (
                                            <div>
                                                <span className="font-medium">Cena:</span> {sample.product.sale_price_net} zł
                                            </div>
                                        )}
                                        <div>
                                            <span className="font-medium">Status:</span>
                                            <Badge className={sample.product?.created ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}>
                                                {sample.product?.created ? 'Nowy' : 'Zaktualizowany'}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Błędy */}
                {errors.length > 0 && (
                    <Card className="p-6">
                        <h2 className="text-lg font-medium mb-4">Błędy</h2>
                        <div className="space-y-2">
                            {errors.map((error, index) => (
                                <div key={index} className="border-l-4 border-red-400 bg-red-50 p-4">
                                    <p className="text-sm text-red-700">{error}</p>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Log wykonania */}
                {run.log && run.log.length > 0 && (
                    <Card className="p-6">
                        <h2 className="text-lg font-medium mb-4">Log wykonania</h2>
                        <div className="bg-gray-900 text-gray-100 p-4 rounded-md font-mono text-sm max-h-96 overflow-y-auto">
                            {run.log.map((entry, index) => (
                                <div key={index} className="mb-1">
                                    {entry}
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}