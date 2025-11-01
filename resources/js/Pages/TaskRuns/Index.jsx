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

export default function Index({ runs }) {
    return (
        <DashboardLayout title="Historia zadań">
            <Head title="Historia zadań" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">Historia zadań</h1>
                </div>

                <div className="space-y-4">
                    {runs.data.length === 0 ? (
                        <Card className="p-6">
                            <div className="text-center text-gray-500">
                                <p>Brak wykonanych zadań</p>
                                <p className="text-sm mt-1">
                                    Zadania pojawią się tutaj po uruchomieniu importu w integracji
                                </p>
                            </div>
                        </Card>
                    ) : (
                        runs.data.map((run) => (
                            <Card key={run.id} className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <h3 className="font-medium text-gray-900">
                                                {run.integration_name} → {run.task_name}
                                            </h3>
                                            <Badge className={statusColors[run.status]}>
                                                {statusLabels[run.status]}
                                            </Badge>
                                        </div>
                                        
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-3">
                                            <div>
                                                <span className="font-medium">Przetworzono:</span>
                                                <div className="text-gray-900">{run.processed_count || 0}</div>
                                            </div>
                                            <div>
                                                <span className="font-medium">Sukces:</span>
                                                <div className="text-green-600">{run.success_count || 0}</div>
                                            </div>
                                            <div>
                                                <span className="font-medium">Błędy:</span>
                                                <div className="text-red-600">{run.failure_count || 0}</div>
                                            </div>
                                            <div>
                                                <span className="font-medium">Rozpoczęto:</span>
                                                <div className="text-gray-900">
                                                    {run.started_at || run.created_at}
                                                </div>
                                            </div>
                                        </div>

                                        {run.message && (
                                            <p className="text-sm text-gray-600 mb-3">{run.message}</p>
                                        )}

                                        <div className="text-xs text-gray-500">
                                            {run.finished_at ? (
                                                <>Zakończono: {run.finished_at}</>
                                            ) : (
                                                <>Utworzono: {run.created_at}</>
                                            )}
                                        </div>
                                    </div>
                                    
                                    <div className="ml-4">
                                        <Button 
                                            asChild 
                                            variant="outline" 
                                            size="sm"
                                        >
                                            <Link href={`/task-runs/${run.id}`}>
                                                Szczegóły
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {runs.links && runs.links.length > 3 && (
                    <div className="flex justify-center mt-6">
                        <nav className="flex space-x-1">
                            {runs.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    className={`px-3 py-2 text-sm rounded-md ${
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                            ? 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                            : 'text-gray-300 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}