import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.jsx';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table.jsx';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import InventoryScanner from '@/components/warehouse/inventory-scanner.jsx';
import { 
    Edit, 
    Play, 
    CheckCircle, 
    XCircle, 
    Clock,
    TrendingUp,
    TrendingDown,
    Minus,
    Scan,
    Calculator
} from 'lucide-react';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: 'PLN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

const formatQuantity = (value) => {
    return new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(value);
};

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

const getStatusIcon = (status) => {
    const icons = {
        draft: <Edit className="h-4 w-4" />,
        in_progress: <Clock className="h-4 w-4" />,
        completed: <CheckCircle className="h-4 w-4" />,
        approved: <CheckCircle className="h-4 w-4" />,
        cancelled: <XCircle className="h-4 w-4" />,
    };
    return icons[status] || icons.draft;
};

const getDiscrepancyIcon = (type) => {
    const icons = {
        surplus: <TrendingUp className="h-4 w-4 text-green-600" />,
        shortage: <TrendingDown className="h-4 w-4 text-red-600" />,
        match: <Minus className="h-4 w-4 text-gray-400" />,
    };
    return icons[type] || icons.match;
};

const getDiscrepancyColor = (type) => {
    const colors = {
        surplus: 'text-green-600 bg-green-50',
        shortage: 'text-red-600 bg-red-50',
        match: 'text-gray-600 bg-gray-50',
    };
    return colors[type] || colors.match;
};

function InfoItem({ label, value }) {
    return (
        <div>
            <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
            <dd className="text-sm text-foreground">{value || '—'}</dd>
        </div>
    );
}

function StatusActions({ inventoryCount }) {
    const handleAction = (action) => {
        const confirmMessages = {
            start: 'Czy na pewno chcesz rozpocząć inwentaryzację? System wczyta wszystkie produkty z aktualnych stanów.',
            complete: 'Czy na pewno chcesz zakończyć inwentaryzację? Po zakończeniu nie będzie możliwe dodawanie nowych pozycji.',
            approve: 'Czy na pewno chcesz zatwierdzić inwentaryzację? System utworzy dokumenty korygujące dla wszystkich rozbieżności.',
            cancel: 'Czy na pewno chcesz anulować inwentaryzację? Wszystkie dane zostaną utracone.',
        };

        if (confirm(confirmMessages[action])) {
            router.post(`/inventory-counts/${inventoryCount.id}/${action}`);
        }
    };

    return (
        <div className="flex gap-2">
            {inventoryCount.can_be_started && (
                <Button onClick={() => handleAction('start')} className="gap-2">
                    <Play className="h-4 w-4" />
                    Rozpocznij
                </Button>
            )}
            
            {inventoryCount.can_be_completed && (
                <Button onClick={() => handleAction('complete')} variant="outline" className="gap-2">
                    <CheckCircle className="h-4 w-4" />
                    Zakończ
                </Button>
            )}
            
            {inventoryCount.can_be_approved && (
                <Button onClick={() => handleAction('approve')} className="gap-2">
                    <CheckCircle className="h-4 w-4" />
                    Zatwierdź
                </Button>
            )}
            
            {inventoryCount.allows_editing && (
                <Button asChild variant="outline">
                    <Link href={`/inventory-counts/${inventoryCount.id}/edit`}>
                        <Edit className="h-4 w-4" />
                        Edytuj
                    </Link>
                </Button>
            )}
            
            {inventoryCount.can_be_cancelled && (
                <Button onClick={() => handleAction('cancel')} variant="destructive" className="gap-2">
                    <XCircle className="h-4 w-4" />
                    Anuluj
                </Button>
            )}
        </div>
    );
}

export default function InventoryCountShow() {
    const { inventoryCount, items, flash } = usePage().props;
    const [showOnlyDiscrepancies, setShowOnlyDiscrepancies] = useState(false);
    const [itemsState, setItemsState] = useState(items);

    useEffect(() => {
        setItemsState(items);
    }, [items]);

    const filteredItems = useMemo(() => {
        if (showOnlyDiscrepancies) {
            return itemsState.filter((item) => item.discrepancy_type !== 'match');
        }
        return itemsState;
    }, [itemsState, showOnlyDiscrepancies]);

    const products = useMemo(() => {
        return itemsState.map((item) => ({
            id: item.product.id,
            name: item.product.name,
            sku: item.product.sku,
            ean: item.product.ean,
        }));
    }, [itemsState]);

    const totals = useMemo(() => {
        const totalProducts = itemsState.length;
        let discrepancyCount = 0;
        let discrepancyValue = 0;

        itemsState.forEach((item) => {
            if (item.discrepancy_type !== 'match') {
                discrepancyCount += 1;
            }
            discrepancyValue += item.value_difference ?? 0;
        });

        return {
            totalProducts,
            discrepancyCount,
            discrepancyValue,
        };
    }, [itemsState]);

    const handleProductScanned = useCallback(async (product, newQuantity, ean = null) => {
        if (!inventoryCount.allows_editing) {
            return;
        }

        try {
            // Use EAN if provided, otherwise use product id
            const requestBody = ean 
                ? { ean: ean, counted_quantity: newQuantity }
                : { product_id: product.id, counted_quantity: newQuantity, scanned_ean: product.ean };

            const response = await fetch(`/inventory-counts/${inventoryCount.id}/update-quantity`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(requestBody),
            });

            if (response.ok) {
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Błąd podczas aktualizacji ilości');
                }

                const normalizedItem = {
                    id: data.item.id,
                    product: data.item.product,
                    system_quantity: data.item.system_quantity,
                    counted_quantity: data.item.counted_quantity,
                    quantity_difference: data.item.quantity_difference,
                    unit_cost: data.item.unit_cost,
                    value_difference: data.item.value_difference,
                    discrepancy_type: data.item.discrepancy_type,
                    notes: data.item.notes,
                    counted_at: data.item.counted_at,
                };

                setItemsState((prev) => {
                    const index = prev.findIndex((entry) => entry.product.id === normalizedItem.product.id);
                    if (index >= 0) {
                        const next = [...prev];
                        next[index] = { ...next[index], ...normalizedItem };
                        return next;
                    }
                    return [...prev, normalizedItem];
                });

                return normalizedItem;
            } else {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Błąd podczas aktualizacji ilości');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            throw error; // Re-throw so scanner component can handle it
        }
    }, [inventoryCount.allows_editing, inventoryCount.id, setItemsState]);

    return (
        <>
            <Head title={`Inwentaryzacja: ${inventoryCount.name}`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" asChild>
                            <Link href="/inventory-counts">← Powrót</Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold">{inventoryCount.name}</h1>
                                <Badge className={getStatusColor(inventoryCount.status)}>
                                    {getStatusIcon(inventoryCount.status)}
                                    {inventoryCount.status_label}
                                </Badge>
                            </div>
                            {inventoryCount.description && (
                                <p className="text-sm text-muted-foreground mt-1">
                                    {inventoryCount.description}
                                </p>
                            )}
                        </div>
                    </div>
                    <StatusActions inventoryCount={inventoryCount} />
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

                {/* Scanner Component */}
                {inventoryCount.allows_editing && (
                    <InventoryScanner
                        products={products}
                        onQuantityUpdate={handleProductScanned}
                        enabled={true}
                        currentItems={itemsState}
                    />
                )}

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                                    <div className="flex items-center gap-2">
                                        <Calculator className="h-4 w-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-2xl font-bold">{totals.totalProducts}</p>
                                            <p className="text-xs text-muted-foreground">Produktów policzonych</p>
                                        </div>
                                    </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardContent className="pt-6">
                                    <div className="flex items-center gap-2">
                                        <TrendingUp className="h-4 w-4 text-amber-500" />
                                        <div>
                                            <p className="text-2xl font-bold text-amber-600">
                                                {totals.discrepancyCount}
                                            </p>
                                            <p className="text-xs text-muted-foreground">Rozbieżności</p>
                                        </div>
                                    </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardContent className="pt-6">
                                        <div className="flex items-center gap-2">
                                            <div>
                                                <p className="text-2xl font-bold">
                                                    {formatCurrency(totals.discrepancyValue)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Wartość rozbieżności</p>
                                            </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardContent className="pt-6">
                            <div>
                                <p className="text-sm font-medium">{inventoryCount.warehouse_name}</p>
                                <p className="text-xs text-muted-foreground">Magazyn</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Inventory Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Informacje</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <InfoItem label="Utworzona" value={inventoryCount.created_at} />
                            <InfoItem label="Rozpoczęta" value={inventoryCount.started_at} />
                            <InfoItem label="Zakończona" value={inventoryCount.completed_at} />
                            <InfoItem label="Liczył" value={inventoryCount.counted_by} />
                            <InfoItem label="Zatwierdził" value={inventoryCount.approved_by} />
                        </dl>
                    </CardContent>
                </Card>

                {/* Items Table */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>
                            Pozycje inwentaryzacji 
                            {filteredItems.length !== itemsState.length && (
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    ({filteredItems.length} z {itemsState.length})
                                </span>
                            )}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Button
                                variant={showOnlyDiscrepancies ? "default" : "outline"}
                                size="sm"
                                onClick={() => setShowOnlyDiscrepancies(!showOnlyDiscrepancies)}
                            >
                                {showOnlyDiscrepancies ? 'Pokaż wszystkie' : 'Tylko rozbieżności'}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produkt</TableHead>
                                        <TableHead className="text-center">Stan systemowy</TableHead>
                                        <TableHead className="text-center">Policzono</TableHead>
                                        <TableHead className="text-center">Różnica</TableHead>
                                        <TableHead className="text-center">Wartość różnicy</TableHead>
                                        <TableHead>Uwagi</TableHead>
                                        <TableHead>Policzono</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredItems.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-24 text-center">
                                                <div className="flex flex-col items-center gap-2">
                                                    {showOnlyDiscrepancies ? (
                                                        <>
                                                            <p className="text-sm text-muted-foreground">
                                                                Brak rozbieżności - wszystkie stany się zgadzają! 🎉
                                                            </p>
                                                            <Button 
                                                                variant="outline" 
                                                                size="sm"
                                                                onClick={() => setShowOnlyDiscrepancies(false)}
                                                            >
                                                                Pokaż wszystkie pozycje
                                                            </Button>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Scan className="h-8 w-8 text-muted-foreground" />
                                                            <p className="text-sm text-muted-foreground">
                                                                {inventoryCount.status === 'draft' 
                                                                    ? 'Rozpocznij inwentaryzację, aby załadować produkty'
                                                                    : 'Brak produktów do policzenia'
                                                                }
                                                            </p>
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredItems.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{item.product.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            SKU: {item.product.sku}
                                                            {item.product.ean && (
                                                                <span className="ml-2">EAN: {item.product.ean}</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center font-mono">
                                                    {formatQuantity(item.system_quantity)}
                                                </TableCell>
                                                <TableCell className="text-center font-mono">
                                                    {formatQuantity(item.counted_quantity)}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <div className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium ${getDiscrepancyColor(item.discrepancy_type)}`}>
                                                        {getDiscrepancyIcon(item.discrepancy_type)}
                                                        {item.quantity_difference > 0 && '+'}
                                                        {formatQuantity(item.quantity_difference)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center font-mono">
                                                    <span className={item.value_difference > 0 ? 'text-green-600' : item.value_difference < 0 ? 'text-red-600' : 'text-gray-600'}>
                                                        {item.value_difference > 0 && '+'}
                                                        {formatCurrency(item.value_difference)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.notes || '—'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.counted_at || '—'}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InventoryCountShow.layout = (page) => (
    <DashboardLayout title="Szczegóły inwentaryzacji">{page}</DashboardLayout>
);
