import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Label } from '@/components/ui/label.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.jsx';
import DocumentItems from '@/components/warehouse/document-items.jsx';

const documentTypeOptions = [
    { value: 'PZ', label: 'PZ – Przyjęcie zewnętrzne' },
    { value: 'WZ', label: 'WZ – Wydanie zewnętrzne' },
    { value: 'IN', label: 'IN – Przyjęcie wewnętrzne' },
    { value: 'OUT', label: 'OUT – Wydanie wewnętrzne' },
];

function FormField({ id, label, hint, error, children }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function WarehouseDocumentCreate() {
    const { products, warehouses, contractors, defaults, errors } = usePage().props;
    const initialItems = [{ product_id: '', quantity: 1, unit_price: '', vat_rate: '' }];
    const [items, setItems] = useState(initialItems);

    const { data, setData, post, processing } = useForm({
        number: '',
        type: defaults?.type ?? 'PZ',
        warehouse_location_id: '',
        contractor_id: '',
        issued_at: defaults?.issued_at ?? new Date().toISOString().slice(0, 10),
        items,
    });

    useEffect(() => {
        setData('items', items);
    }, [items]);

    const submit = (event) => {
        event.preventDefault();
        post('/warehouse/documents');
    };

    return (
        <>
            <Head title="Nowy dokument magazynowy" />
            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Podstawowe informacje</CardTitle>
                        <CardDescription>
                            Uzupełnij dane nagłówka dokumentu. Część pól może być wypełniona automatycznie po
                            zapisaniu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 md:grid-cols-2">
                        <FormField
                            id="number"
                            label="Numer dokumentu"
                            hint="Pozostaw puste, aby numer został nadany automatycznie."
                            error={errors.number}
                        >
                            <Input
                                id="number"
                                value={data.number}
                                onChange={(event) => setData('number', event.target.value)}
                                placeholder="Generowany automatycznie"
                            />
                        </FormField>

                        <FormField id="type" label="Typ dokumentu" error={errors.type}>
                            <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Wybierz typ dokumentu" />
                                </SelectTrigger>
                                <SelectContent>
                                    {documentTypeOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField id="warehouse" label="Magazyn" error={errors.warehouse_location_id}>
                            <Select
                                value={data.warehouse_location_id ? String(data.warehouse_location_id) : 'none'}
                                onValueChange={(value) =>
                                    setData('warehouse_location_id', value === 'none' ? '' : Number(value))
                                }
                            >
                                <SelectTrigger id="warehouse">
                                    <SelectValue placeholder="Wybierz magazyn" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Brak magazynu</SelectItem>
                                    {warehouses.map((warehouse) => (
                                        <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                                            {warehouse.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField id="issued_at" label="Data wystawienia" error={errors.issued_at}>
                            <Input
                                id="issued_at"
                                type="date"
                                value={data.issued_at}
                                onChange={(event) => setData('issued_at', event.target.value)}
                                required
                            />
                        </FormField>

                        <FormField id="contractor" label="Kontrahent" error={errors.contractor_id}>
                            <Select
                                value={data.contractor_id ? String(data.contractor_id) : 'none'}
                                onValueChange={(value) =>
                                    setData('contractor_id', value === 'none' ? '' : Number(value))
                                }
                            >
                                <SelectTrigger id="contractor">
                                    <SelectValue placeholder="Wybierz kontrahenta" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Brak kontrahenta</SelectItem>
                                    {contractors.map((contractor) => (
                                        <SelectItem key={contractor.id} value={String(contractor.id)}>
                                            {contractor.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pozycje dokumentu</CardTitle>
                        <CardDescription>
                            Dodaj produkty wraz z ilościami i cenami. Podsumowanie aktualizuje się automatycznie.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <DocumentItems
                            items={items}
                            onChange={setItems}
                            products={products}
                            warehouseId={data.warehouse_location_id}
                        />
                        {errors.items && <p className="text-xs text-destructive">{errors.items}</p>}
                    </CardContent>
                </Card>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        Zapisz dokument
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/warehouse/documents">Anuluj</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

WarehouseDocumentCreate.layout = (page) => (
    <DashboardLayout title="Nowy dokument magazynowy">{page}</DashboardLayout>
);
