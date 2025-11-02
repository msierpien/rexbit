import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import DocumentItems from '@/components/warehouse/document-items.jsx';

function Field({ label, children }) {
    return (
        <label className="flex flex-col gap-1 text-sm text-gray-700">
            <span className="font-medium text-gray-900">{label}</span>
            {children}
        </label>
    );
}

export default function WarehouseDocumentEdit() {
    const { document, products, warehouses, contractors, flash, errors } = usePage().props;
    const [items, setItems] = useState(
        document.items.length ? document.items : [{ product_id: '', quantity: 1, unit_price: '', vat_rate: '' }],
    );

    const { data, setData, put, processing } = useForm({
        number: document.number ?? '',
        type: document.type ?? 'PZ',
        warehouse_location_id: document.warehouse_location_id ?? '',
        contractor_id: document.contractor_id ?? '',
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

    return (
        <>
            <Head title="Edycja dokumentu magazynowego" />
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
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Field label="Numer">
                            <input
                                type="text"
                                value={data.number ?? ''}
                                onChange={(event) => setData('number', event.target.value)}
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            {errors.number && <p className="text-xs text-red-600">{errors.number}</p>}
                        </Field>

                        <Field label="Typ dokumentu">
                            <select
                                value={data.type}
                                onChange={(event) => setData('type', event.target.value)}
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="PZ">PZ - Przyjęcie zewnętrzne</option>
                                <option value="WZ">WZ - Wydanie zewnętrzne</option>
                                <option value="IN">IN - Przyjęcie wewnętrzne</option>
                                <option value="OUT">OUT - Wydanie wewnętrzne</option>
                            </select>
                            {errors.type && <p className="text-xs text-red-600">{errors.type}</p>}
                        </Field>

                        <Field label="Magazyn">
                            <select
                                value={data.warehouse_location_id ?? ''}
                                onChange={(event) => setData('warehouse_location_id', event.target.value)}
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">Wybierz magazyn</option>
                                {warehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>
                                        {warehouse.name}
                                    </option>
                                ))}
                            </select>
                            {errors.warehouse_location_id && (
                                <p className="text-xs text-red-600">{errors.warehouse_location_id}</p>
                            )}
                        </Field>

                        <Field label="Data">
                            <input
                                type="date"
                                value={data.issued_at ?? ''}
                                onChange={(event) => setData('issued_at', event.target.value)}
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            />
                            {errors.issued_at && <p className="text-xs text-red-600">{errors.issued_at}</p>}
                        </Field>
                    </div>

                    <Field label="Kontrahent">
                        <select
                            value={data.contractor_id ?? ''}
                            onChange={(event) => setData('contractor_id', event.target.value)}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">Wybierz kontrahenta</option>
                            {contractors.map((contractor) => (
                                <option key={contractor.id} value={contractor.id}>
                                    {contractor.name}
                                </option>
                            ))}
                        </select>
                        {errors.contractor_id && <p className="text-xs text-red-600">{errors.contractor_id}</p>}
                    </Field>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="mb-4">
                        <h3 className="text-sm font-semibold text-gray-900">Pozycje dokumentu</h3>
                        <p className="text-xs text-gray-500">Edytuj listę produktów wchodzących w skład dokumentu.</p>
                    </div>
                    <DocumentItems 
                        items={items} 
                        onChange={setItems} 
                        products={products} 
                        warehouseId={data.warehouse_location_id}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        Zapisz zmiany
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/warehouse/documents">Wróć</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

WarehouseDocumentEdit.layout = (page) => <DashboardLayout title="Edycja dokumentu magazynowego">{page}</DashboardLayout>;
