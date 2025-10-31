import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';
import {
    Plug as PlugIcon,
    Filter as FilterIcon,
    RefreshCcw,
    Trash2,
    Edit3,
    Store as StoreIcon,
    Layers3,
    Activity,
    AlertTriangle,
    PauseCircle,
    Clock,
} from 'lucide-react';

const TYPE_ICONS = {
    store: StoreIcon,
    'file-stack': Layers3,
};

function StatCard({ title, value, icon: Icon, accent }) {
    return (
        <Card className="flex flex-1 items-center gap-3 border border-border/80 bg-card">
            <div
                className={`ml-4 flex size-10 items-center justify-center rounded-full text-white ${accent}`}
            >
                <Icon className="size-5" />
            </div>
            <CardHeader className="p-4 pr-6">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
                <CardDescription className="text-xl font-semibold text-foreground">
                    {value}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

function IntegrationCard({ integration, onTest, onDelete, testingId, deletingId }) {
    const Icon = TYPE_ICONS[integration.type_icon] ?? PlugIcon;
    const isTesting = testingId === integration.id;
    const isDeleting = deletingId === integration.id;

    return (
        <Card className="border border-border/70 shadow-sm transition hover:shadow-md">
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                    <div className="flex items-center gap-2">
                        <Icon className="size-5 text-primary" />
                        <CardTitle className="text-lg font-semibold text-foreground">
                            {integration.name}
                        </CardTitle>
                    </div>
                    <CardDescription className="mt-2 text-sm">
                        Typ: <span className="font-medium text-foreground">{integration.type_label}</span>
                    </CardDescription>
                </div>
                <Badge variant={integration.status_variant}>
                    {integration.status_label}
                </Badge>
            </CardHeader>

            <CardContent className="space-y-3 text-sm text-muted-foreground">
                <div className="flex items-center gap-2">
                    <Clock className="size-4" />
                    <span>
                        Ostatnia synchronizacja:{' '}
                        <span className="font-medium text-foreground">
                            {integration.last_synced_human ?? 'Brak danych'}
                        </span>
                    </span>
                </div>
                <div>Utworzono: {integration.created_at_human}</div>

                <div className="flex flex-wrap gap-2 pt-2">
                    <Button
                        size="sm"
                        variant="outline"
                        asChild
                    >
                        <Link href={`/integrations/${integration.id}/edit`}>
                            <Edit3 className="mr-2 size-4" />
                            Edytuj
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => onTest(integration.id)}
                        disabled={isTesting}
                    >
                        <RefreshCcw className={`mr-2 size-4 ${isTesting ? 'animate-spin' : ''}`} />
                        {isTesting ? 'Testuję...' : 'Testuj połączenie'}
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => onDelete(integration.id)}
                        disabled={isDeleting}
                    >
                        <Trash2 className="mr-2 size-4" />
                        {isDeleting ? 'Usuwanie...' : 'Usuń'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function IntegrationsIndex() {
    const { integrations, filters, types, stats, can, errors } = usePage().props;
    const [testingId, setTestingId] = useState(null);
    const [deletingId, setDeletingId] = useState(null);

    const ALL_VALUE = 'all';

    const typeOptions = useMemo(
        () => [
            { value: ALL_VALUE, label: 'Wszystkie integracje' },
            ...types.map((type) => ({ value: type.value, label: type.label })),
        ],
        [types],
    );

    const handleFilterChange = (value) => {
        const params = {};
        if (value && value !== ALL_VALUE) {
            params.type = value;
        }

        router.get('/integrations', params, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleTest = (id) => {
        setTestingId(id);
        router.post(
            `/integrations/${id}/test`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTestingId(null),
            },
        );
    };

    const handleDelete = (id) => {
        if (!confirm('Czy na pewno chcesz usunąć tę integrację?')) {
            return;
        }

        setDeletingId(id);
        router.delete(`/integrations/${id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <>
            <Head title="Integracje" />

            <div className="space-y-6">
                <div className="grid gap-3 md:grid-cols-4">
                    <StatCard title="Łącznie" value={stats.total} icon={PlugIcon} accent="bg-blue-500" />
                    <StatCard title="Aktywne" value={stats.active} icon={Activity} accent="bg-emerald-500" />
                    <StatCard title="Błędy" value={stats.error} icon={AlertTriangle} accent="bg-red-500" />
                    <StatCard title="Nieaktywne" value={stats.inactive} icon={PauseCircle} accent="bg-slate-500" />
                </div>

                <Card className="border border-dashed border-border/80 bg-card/80">
                    <CardContent className="flex flex-col gap-4 py-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3 text-sm text-muted-foreground">
                            <FilterIcon className="size-4" />
                            <span>Filtruj po typie integracji</span>
                        </div>

                    <div className="flex flex-wrap items-center gap-3">
                            <Select value={filters.type ?? ALL_VALUE} onValueChange={handleFilterChange}>
                                <SelectTrigger className="w-56 justify-between">
                                    <SelectValue placeholder="Wszystkie integracje" />
                                </SelectTrigger>
                                <SelectContent>
                                    {typeOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value ?? ''}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {can?.create && (
                                <Button asChild>
                                    <Link href="/integrations/create">Dodaj integrację</Link>
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {errors?.integration && (
                    <Alert variant="destructive">
                        <AlertTitle>Problemy z integracją</AlertTitle>
                        <AlertDescription>{errors.integration}</AlertDescription>
                    </Alert>
                )}

                {integrations.length === 0 ? (
                    <Card className="border border-dashed border-border/80">
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            <p>Nie masz jeszcze żadnych integracji.</p>
                            {can?.create && (
                                <Button className="mt-4" asChild>
                                    <Link href="/integrations/create">Dodaj pierwszą integrację</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 xl:grid-cols-2">
                        {integrations.map((integration) => (
                            <IntegrationCard
                                key={integration.id}
                                integration={integration}
                                onTest={handleTest}
                                onDelete={handleDelete}
                                testingId={testingId}
                                deletingId={deletingId}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

IntegrationsIndex.layout = (page) => <DashboardLayout title="Integracje">{page}</DashboardLayout>;

export default IntegrationsIndex;
