import { useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import DocumentItems from '@/components/warehouse/document-items.jsx';
import DocumentStatusActions from '@/components/warehouse/document-status-actions.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { cn } from '@/lib/utils.js';

const documentTypeOptions = [
    { value: 'PZ', label: 'PZ – Przyjęcie zewnętrzne' },
    { value: 'WZ', label: 'WZ – Wydanie zewnętrzne' },
    { value: 'IN', label: 'IN – Przyjęcie wewnętrzne' },
    { value: 'OUT', label: 'OUT – Wydanie wewnętrzne' },
];

const statusClasses = {
    draft: 'bg-slate-100 text-slate-700 border-slate-200',
    posted: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-100 text-red-700 border-red-200',
    archived: 'bg-blue-100 text-blue-700 border-blue-200',
};

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

export default function WarehouseDocumentEdit() {
    const { document, products, warehouses, contractors, flash, errors } = usePage().props;
    const initialItems =
        document.items.length > 0
            ? document.items.map((item) => ({
                  product_id: item.product_id ?? '',
                  quantity: item.quantity ?? '',
                  unit_price: item.unit_price ?? '',
                  vat_rate: item.vat_rate ?? '',
              }))
            : [{ product_id: '', quantity: 1, unit_price: '', vat_rate: '' }];

    const [items, setItems] = useState(initialItems);

    const { data, setData, put, processing } = useForm({
        number: document.number ?? '',
        type: document.type ?? 'PZ',
        warehouse_location_id: document.warehouse_location_id ? String(document.warehouse_location_id) : '',
        contractor_id: document.contractor_id ? String(document.contractor_id) : '',
        issued_at: document.issued_at ?? new Date().toISOString().slice(0, 10),
        items,
    });

    useEffect(() => {
        setData('items', items);
    }, [items]);

    const submit = (event) => {
        event.preventDefault();
        put(`/warehouse/documents/${document.id}`);
    };

    const handleDelete = () => {
        if (!document.can_be_deleted) {
            alert(document.deletion_block_reason || 'Nie można usunąć tego dokumentu.');
            return;
        }

        if (confirm(`Usunąć dokument ${document.number}?`)) {
            router.delete(`/warehouse/documents/${document.id}`);
        }
    };

    return (
        <>
            <Head title="Edycja dokumentu magazynowego" />

            <div className="space-y-4">
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
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Edycja dokumentu</CardTitle>
                            <CardDescription>
                                Aktualny status dokumentu oraz dostępne akcje możesz zmienić bezpośrednio tutaj.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className={cn('border', statusClasses[document.status] ?? '')}>
                                {document.status_label}
                            </Badge>
                            <DocumentStatusActions document={document} />
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button variant="ghost" asChild>
                            <Link href="/warehouse/documents">Powrót</Link>
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={!document.can_be_deleted}
                            title={document.deletion_block_reason || ''}
                            onClick={handleDelete}
                        >
                            Usuń dokument
                        </Button>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Podstawowe informacje</CardTitle>
                            <CardDescription>
                                Uzupełnij dane dokumentu. Zmiana numeru i typu może wymagać nadania nowego numeru.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 md:grid-cols-2">
                            <FormField id="number" label="Numer dokumentu" error={errors.number}>
                                <Input
                                    id="number"
                                    value={data.number ?? ''}
                                    onChange={(event) => setData('number', event.target.value)}
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
                                    value={data.issued_at ?? ''}
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
                                Edytuj produkty i ich parametry. Widzisz aktualne stany magazynowe dla wybranego
                                magazynu.
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
                            Zapisz zmiany
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/warehouse/documents">Anuluj</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

WarehouseDocumentEdit.layout = (page) => (
    <DashboardLayout title="Edycja dokumentu magazynowego">{page}</DashboardLayout>
);
