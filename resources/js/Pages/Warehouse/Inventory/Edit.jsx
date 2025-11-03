import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Label } from '@/components/ui/label.jsx';
import { Textarea } from '@/components/ui/textarea.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.jsx';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import { Badge } from '@/components/ui/badge.jsx';

function FormField({ id, label, hint, error, children }) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}

const getStatusColor = (status) => {
    const colors = {
        draft: 'bg-slate-100 text-slate-700 border-slate-200',
        in_progress: 'bg-blue-100 text-blue-700 border-blue-200',
        completed: 'bg-amber-100 text-amber-700 border-amber-200',
        approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        cancelled: 'bg-red-100 text-red-700 border-red-200',
    };
    return colors[status] || colors.draft;
};

export default function InventoryCountEdit() {
    const { inventoryCount, warehouses, errors, flash } = usePage().props;
    
    const { data, setData, put, processing } = useForm({
        name: inventoryCount.name,
        description: inventoryCount.description || '',
    });

    const submit = (event) => {
        event.preventDefault();
        put(`/inventory-counts/${inventoryCount.id}`);
    };

    if (!inventoryCount.allows_editing) {
        return (
            <>
                <Head title={`Edytuj: ${inventoryCount.name}`} />
                
                <div className="space-y-6">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" asChild>
                            <Link href={`/inventory-counts/${inventoryCount.id}`}>← Powrót</Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">Edycja inwentaryzacji</h1>
                            <p className="text-sm text-muted-foreground">
                                {inventoryCount.name}
                            </p>
                        </div>
                    </div>

                    <Alert variant="destructive">
                        <AlertTitle>Nie można edytować</AlertTitle>
                        <AlertDescription>
                            Inwentaryzacja w statusie "{inventoryCount.status_label}" nie może być edytowana.
                            Edycja możliwa tylko dla inwentaryzacji w statusie "Projekt" lub "W trakcie".
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Edytuj: ${inventoryCount.name}`} />
            
            <form onSubmit={submit} className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" asChild>
                        <Link href={`/inventory-counts/${inventoryCount.id}`}>← Powrót</Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">Edycja inwentaryzacji</h1>
                            <Badge className={getStatusColor(inventoryCount.status)}>
                                {inventoryCount.status_label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Edytuj podstawowe informacje o inwentaryzacji
                        </p>
                    </div>
                </div>

                {/* Flash Messages */}
                {(flash?.status || flash?.error) && (
                    <div className="space-y-2">
                        {flash?.status && (
                            <Alert>
                                <AlertTitle>Sukces</AlertTitle>
                                <AlertDescription>{flash.status}</AlertDescription>
                            </Alert>
                        )}
                        {flash?.error && (
                            <Alert variant="destructive">
                                <AlertTitle>Błąd</AlertTitle>
                                <AlertDescription>{flash.error}</AlertDescription>
                            </Alert>
                        )}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Podstawowe informacje</CardTitle>
                        <CardDescription>
                            Zaktualizuj dane inwentaryzacji. Magazyn nie może być zmieniony po utworzeniu inwentaryzacji.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <FormField
                            id="name"
                            label="Nazwa inwentaryzacji"
                            hint="Np. 'Inwentaryzacja Q4 2024' lub 'Kontrola stanów - magazyn główny'"
                            error={errors.name}
                        >
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="Wprowadź nazwę inwentaryzacji"
                                required
                            />
                        </FormField>

                        <FormField
                            id="warehouse"
                            label="Magazyn"
                            hint="Magazyn nie może być zmieniony po utworzeniu inwentaryzacji"
                        >
                            <div className="flex items-center gap-2 p-3 bg-muted rounded-md">
                                <span className="font-medium">
                                    {warehouses.find(w => w.id === inventoryCount.warehouse_location_id)?.name}
                                </span>
                                <Badge variant="secondary">Nie można zmienić</Badge>
                            </div>
                        </FormField>

                        <FormField
                            id="description"
                            label="Opis (opcjonalny)"
                            hint="Dodatkowe informacje o inwentaryzacji"
                            error={errors.description}
                        >
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder="Wprowadź opis inwentaryzacji..."
                                rows={3}
                            />
                        </FormField>
                    </CardContent>
                </Card>

                {inventoryCount.status === 'in_progress' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Inwentaryzacja w trakcie</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-lg bg-blue-50 border border-blue-200 p-4 space-y-2">
                                <h4 className="font-medium text-blue-900">Inwentaryzacja jest aktywna</h4>
                                <p className="text-sm text-blue-700">
                                    Możesz edytować podstawowe informacje, ale pamiętaj, że inwentaryzacja jest już w trakcie. 
                                    Użyj skanera lub wprowadzaj ilości ręcznie na stronie szczegółów.
                                </p>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={`/inventory-counts/${inventoryCount.id}`}>
                                        Przejdź do liczenia produktów
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="flex gap-4">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Zapisywanie...' : 'Zapisz zmiany'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={`/inventory-counts/${inventoryCount.id}`}>Anuluj</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

InventoryCountEdit.layout = (page) => (
    <DashboardLayout title="Edytuj inwentaryzację">{page}</DashboardLayout>
);