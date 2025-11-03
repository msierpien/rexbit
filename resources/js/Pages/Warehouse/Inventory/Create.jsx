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
import { Alert, AlertDescription } from '@/components/ui/alert.jsx';

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

export default function InventoryCountCreate() {
    const { warehouses, errors } = usePage().props;
    
    const { data, setData, post, processing } = useForm({
        name: '',
        description: '',
        warehouse_location_id: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/inventory-counts');
    };

    return (
        <>
            <Head title="Nowa inwentaryzacja" />
            
            <form onSubmit={submit} className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" asChild>
                        <Link href="/inventory-counts">← Powrót</Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">Nowa inwentaryzacja</h1>
                        <p className="text-sm text-muted-foreground">
                            Utwórz nową inwentaryzację magazynową
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Podstawowe informacje</CardTitle>
                        <CardDescription>
                            Podaj nazwę inwentaryzacji i wybierz magazyn, który chcesz zinwentaryzować.
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
                            hint="Wybierz magazyn, którego stany chcesz zinwentaryzować"
                            error={errors.warehouse_location_id}
                        >
                            <Select
                                value={data.warehouse_location_id ? String(data.warehouse_location_id) : ''}
                                onValueChange={(value) => setData('warehouse_location_id', Number(value))}
                            >
                                <SelectTrigger id="warehouse">
                                    <SelectValue placeholder="Wybierz magazyn" />
                                </SelectTrigger>
                                <SelectContent>
                                    {warehouses.map((warehouse) => (
                                        <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                                            {warehouse.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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

                <Card>
                    <CardHeader>
                        <CardTitle>Proces inwentaryzacji</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-lg bg-muted p-4 space-y-4">
                            <h4 className="font-medium">Po utworzeniu inwentaryzacji będziesz mógł:</h4>
                            <ol className="list-decimal list-inside space-y-2 text-sm text-muted-foreground">
                                <li>Rozpocząć inwentaryzację - system wczyta wszystkie produkty z aktualnych stanów</li>
                                <li>Skanować kody EAN produktów lub wprowadzać ilości ręcznie</li>
                                <li>System automatycznie porówna policzony stan z stanem systemowym</li>
                                <li>Po zakończeniu można zatwierdzić inwentaryzację</li>
                                <li>System utworzy dokumenty korygujące dla rozbieżności</li>
                            </ol>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-4">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Tworzenie...' : 'Utwórz inwentaryzację'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/inventory-counts">Anuluj</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

InventoryCountCreate.layout = (page) => (
    <DashboardLayout title="Nowa inwentaryzacja">{page}</DashboardLayout>
);