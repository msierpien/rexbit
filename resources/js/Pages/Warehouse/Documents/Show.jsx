import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.jsx';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table.jsx';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import DocumentStatusActions from '@/components/warehouse/document-status-actions.jsx';
import DocumentItems from '@/components/warehouse/document-items.jsx';
import { cn } from '@/lib/utils.js';

const currencyFormatter = new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const quantityFormatter = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
});

const statusClasses = {
    draft: 'bg-slate-100 text-slate-700 border-slate-200',
    posted: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-100 text-red-700 border-red-200',
    archived: 'bg-blue-100 text-blue-700 border-blue-200',
};

function formatCurrency(value) {
    return currencyFormatter.format(Number(value ?? 0));
}

function formatQuantity(value) {
    return quantityFormatter.format(Number(value ?? 0));
}

function InfoItem({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm text-foreground">{value ?? '—'}</p>
        </div>
    );
}

function MetadataSection({ metadata }) {
    if (!metadata || Object.keys(metadata).length === 0) {
        return null;
    }

    const displayableKeys = [
        { key: 'invoice_number', label: 'Numer faktury' },
        { key: 'order_number', label: 'Numer zamówienia' },
        { key: 'reference', label: 'Referencja' },
        { key: 'cancellation_reason', label: 'Powód anulowania' },
        { key: 'cancelled_at', label: 'Data anulowania' },
    ];

    const items = displayableKeys
        .map(({ key, label }) => ({
            label,
            value: metadata[key],
        }))
        .filter((item) => item.value);

    if (!items.length) {
        return null;
    }

    return (
        <Card className="border-dashed bg-muted/30">
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-semibold">Informacje dodatkowe</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
                {items.map((item) => (
                    <InfoItem key={item.label} label={item.label} value={item.value} />
                ))}
            </CardContent>
        </Card>
    );
}

export default function WarehouseDocumentShow() {
    const { document, flash, adminPostedEdit = null, auth } = usePage().props;

    const isAdmin = auth?.user?.role === 'admin';
    const canAdminEditPosted = Boolean(isAdmin && document.status === 'posted' && adminPostedEdit?.enabled);
    const adminProducts = adminPostedEdit?.products ?? [];

    const adminInitialItems = useMemo(
        () =>
            (document.items || []).map((item) => ({
                product_id: item.product_id ?? item.product?.id ?? '',
                quantity: item.quantity ?? '',
                unit_price: item.unit_price ?? '',
                vat_rate: item.vat_rate ?? '',
            })),
        [document.items]
    );

    const [adminItems, setAdminItems] = useState(adminInitialItems);
    const adminForm = useForm({ items: adminInitialItems });

    useEffect(() => {
        setAdminItems(adminInitialItems);
    }, [adminInitialItems]);

    useEffect(() => {
        adminForm.setData('items', adminItems);
    }, [adminForm, adminItems]);

    const [previewState, setPreviewState] = useState({
        loading: false,
        changes: [],
        error: null,
        fetchedAt: null,
    });

    useEffect(() => {
        setPreviewState((current) => ({
            ...current,
            changes: [],
            error: null,
            fetchedAt: null,
        }));
    }, [adminItems]);

    const productLookup = useMemo(() => {
        return adminProducts.reduce((accumulator, product) => {
            accumulator[product.id] = product;
            return accumulator;
        }, {});
    }, [adminProducts]);

    const csrfToken =
        typeof window !== 'undefined'
            ? window.document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            : '';

    const handleAdminPreview = async () => {
        if (!canAdminEditPosted) {
            return;
        }

        setPreviewState({ loading: true, changes: [], error: null, fetchedAt: null });

        try {
            const response = await fetch(`/warehouse/documents/${document.id}/preview-edit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ items: adminForm.data.items }),
            });

            const payload = await response.json();

            if (!response.ok || payload.success !== true) {
                throw new Error(payload.error || 'Nie udało się pobrać podglądu zmian.');
            }

            setPreviewState({
                loading: false,
                changes: payload.changes ?? [],
                error: null,
                fetchedAt: new Date().toISOString(),
            });
        } catch (error) {
            setPreviewState({
                loading: false,
                changes: [],
                error: error?.message ?? 'Nie udało się pobrać podglądu zmian.',
                fetchedAt: null,
            });
        }
    };

    const handleAdminSubmit = (event) => {
        event.preventDefault();

        if (!canAdminEditPosted) {
            return;
        }

        adminForm.post(`/warehouse/documents/${document.id}/edit-posted`, {
            preserveScroll: true,
            onSuccess: () => {
                setPreviewState({ loading: false, changes: [], error: null, fetchedAt: null });
            },
        });
    };

    const handleAdminReset = () => {
        setAdminItems(adminInitialItems);
        adminForm.setData('items', adminInitialItems);
    };

    const previewHasChanges = previewState.changes.length > 0;
    const previewTimestampLabel = previewState.fetchedAt
        ? new Date(previewState.fetchedAt).toLocaleTimeString('pl-PL', {
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
          })
        : null;

    const handleDelete = () => {
        if (!document.can_be_deleted) {
            alert(document.deletion_block_reason || 'Nie można usunąć tego dokumentu.');
            return;
        }

        if (confirm(`Usunąć dokument ${document.number}?`)) {
            router.delete(`/warehouse/documents/${document.id}`, {
                preserveScroll: true,
            });
        }
    };

    const statusBadgeClass = cn('border', statusClasses[document.status] ?? '');

    return (
        <>
            <Head title={`Dokument ${document.number}`} />

            <div className="space-y-6">
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

                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-xl font-semibold text-foreground">
                                {document.type} Nr {document.number}
                            </h1>
                            <Badge variant="outline" className={statusBadgeClass}>
                                {document.status_label}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Dokument utworzono {document.created_at ?? '—'}, ostatnia aktualizacja{' '}
                            {document.updated_at ?? '—'}.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="ghost" asChild>
                            <Link href="/warehouse/documents">Powrót</Link>
                        </Button>
                        <Button variant="outline" onClick={() => window.print()}>
                            Drukuj
                        </Button>
                        <DocumentStatusActions document={document} />
                        <Button
                            variant="outline"
                            asChild
                            disabled={!document.can_be_edited}
                            title={
                                document.can_be_edited
                                    ? 'Edytuj dokument'
                                    : 'Tylko dokumenty robocze mogą być edytowane'
                            }
                        >
                            <Link href={`/warehouse/documents/${document.id}/edit`}>Edytuj</Link>
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={!document.can_be_deleted}
                            title={document.deletion_block_reason || ''}
                            onClick={handleDelete}
                        >
                            Usuń
                        </Button>
                    </div>
                </div>

                <Card className="border shadow-sm">
                    <CardHeader className="border-b bg-muted/40">
                        <CardTitle className="text-base font-semibold">
                            Podsumowanie dokumentu
                        </CardTitle>
                        <CardDescription>
                            Szczegóły dokumentu magazynowego przygotowane do wydruku.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <InfoItem label="Magazyn" value={document.warehouse?.name} />
                            <InfoItem label="Data wystawienia" value={document.issued_at} />
                            <InfoItem label="Utworzył" value={document.user?.email ?? document.user?.name} />
                            <InfoItem label="Kontrahent" value={document.contractor?.name} />
                            <InfoItem label="Numer faktury" value={document.metadata?.invoice_number} />
                            <InfoItem label="Numer zamówienia" value={document.metadata?.order_number} />
                        </div>

                        <div className="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader className="bg-muted/30">
                                    <TableRow>
                                        <TableHead className="w-10">#</TableHead>
                                        <TableHead>Produkt</TableHead>
                                        <TableHead className="text-right">Ilość</TableHead>
                                        <TableHead className="text-right">Cena netto</TableHead>
                                        <TableHead className="text-right">Wartość netto</TableHead>
                                        <TableHead className="text-right">VAT %</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {document.items.map((item, index) => (
                                        <TableRow key={item.id ?? index}>
                                            <TableCell className="font-medium">{index + 1}.</TableCell>
                                            <TableCell>
                                                <div className="font-medium text-foreground">
                                                    {item.product?.name ?? 'Produkt usunięty'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.product?.sku && <span>SKU: {item.product.sku}</span>}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatQuantity(item.quantity)} szt.
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {item.unit_price !== null ? formatCurrency(item.unit_price) : '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCurrency(item.net_value)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {item.vat_rate !== null ? `${item.vat_rate}%` : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell colSpan={2} className="text-right font-semibold">
                                            Razem
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {formatQuantity(document.summary.total_quantity)} szt.
                                        </TableCell>
                                        <TableCell />
                                        <TableCell className="text-right font-semibold">
                                            {formatCurrency(document.summary.total_net_value)}
                                        </TableCell>
                                        <TableCell />
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Card className="border-dashed bg-muted/20">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-semibold">Podsumowanie</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Pozycji</span>
                                        <span className="font-medium">{document.summary.total_items}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Łączna ilość</span>
                                        <span className="font-medium">
                                            {formatQuantity(document.summary.total_quantity)} szt.
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Wartość netto</span>
                                        <span className="font-semibold">
                                            {formatCurrency(document.summary.total_net_value)}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <MetadataSection metadata={document.metadata} />
                        </div>
                    </CardContent>
                </Card>

                {canAdminEditPosted && (
                    <Card className="border-amber-200 bg-amber-50/70 dark:border-amber-500/30 dark:bg-amber-950/20">
                        <CardHeader>
                            <CardTitle>Edycja zatwierdzonego dokumentu (administrator)</CardTitle>
                            <CardDescription>
                                Po zapisaniu stany magazynowe zostaną przeliczone, a zmiany zapisane w logach audytowych.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Najpierw wykonaj podgląd, aby sprawdzić wpływ zmian na stany magazynowe. Formularz korzysta z
                                bieżących pozycji dokumentu i produktów użytkownika.
                            </p>

                            {!adminProducts.length ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Brak produktów do wyboru</AlertTitle>
                                    <AlertDescription>
                                        Dodaj produkty do swojego katalogu, aby móc zaktualizować zatwierdzony dokument.
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <form onSubmit={handleAdminSubmit} className="space-y-4">
                                    <DocumentItems
                                        items={adminItems}
                                        onChange={setAdminItems}
                                        products={adminProducts}
                                        warehouseId={document.warehouse_location_id ?? document.warehouse?.id ?? null}
                                    />
                                    {adminForm.errors.items && (
                                        <p className="text-xs text-destructive">{adminForm.errors.items}</p>
                                    )}
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleAdminPreview}
                                            disabled={previewState.loading || adminForm.processing}
                                        >
                                            {previewState.loading ? 'Ładowanie podglądu...' : 'Podgląd zmian'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={handleAdminReset}
                                            disabled={adminForm.processing || previewState.loading}
                                        >
                                            Przywróć bieżące wartości
                                        </Button>
                                        <Button type="submit" disabled={adminForm.processing}>
                                            {adminForm.processing ? 'Zapisywanie...' : 'Zapisz zmiany'}
                                        </Button>
                                    </div>
                                </form>
                            )}

                            {previewState.error && (
                                <Alert variant="destructive">
                                    <AlertTitle>Nie udało się przygotować podglądu</AlertTitle>
                                    <AlertDescription>{previewState.error}</AlertDescription>
                                </Alert>
                            )}

                            {previewHasChanges && (
                                <div className="space-y-3 rounded-lg border border-dashed border-amber-200 bg-white/80 p-4 dark:border-amber-500/30 dark:bg-slate-900/40">
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span>Podgląd wpływu na stany magazynowe</span>
                                        {previewTimestampLabel && <span>Ostatnia aktualizacja: {previewTimestampLabel}</span>}
                                    </div>
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader className="bg-amber-100/50 dark:bg-amber-950/30">
                                                <TableRow>
                                                    <TableHead>Produkt</TableHead>
                                                    <TableHead className="text-right">Poprzednia ilość</TableHead>
                                                    <TableHead className="text-right">Nowa ilość</TableHead>
                                                    <TableHead className="text-right">Zmiana stanu</TableHead>
                                                    <TableHead className="text-right">Stan obecny</TableHead>
                                                    <TableHead className="text-right">Stan po zmianie</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {previewState.changes.map((change) => {
                                                    const product = productLookup[change.product_id];
                                                    const netChangeClass = change.net_stock_change > 0
                                                        ? 'text-emerald-600'
                                                        : change.net_stock_change < 0
                                                            ? 'text-rose-600'
                                                            : 'text-foreground';

                                                    return (
                                                        <TableRow key={change.product_id}>
                                                            <TableCell>
                                                                <div className="font-medium text-foreground">
                                                                    {product?.name ?? `Produkt #${change.product_id}`}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">ID: {change.product_id}</div>
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                {formatQuantity(change.old_quantity)} szt.
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                {formatQuantity(change.new_quantity)} szt.
                                                            </TableCell>
                                                            <TableCell className={cn('text-right font-semibold', netChangeClass)}>
                                                                {change.net_stock_change > 0 ? '+' : ''}
                                                                {formatQuantity(change.net_stock_change)} szt.
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                {formatQuantity(change.current_stock)} szt.
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                {formatQuantity(change.new_stock)} szt.
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="border-dashed bg-muted/20">
                        <CardContent className="flex h-full items-center justify-center text-sm text-muted-foreground">
                            Podpis osoby wystawiającej
                        </CardContent>
                    </Card>
                    <Card className="border-dashed bg-muted/20">
                        <CardContent className="flex h-full items-center justify-center text-sm text-muted-foreground">
                            Podpis osoby wydającej
                        </CardContent>
                    </Card>
                    <Card className="border-dashed bg-muted/20">
                        <CardContent className="flex h-full items-center justify-center text-sm text-muted-foreground">
                            Podpis osoby odbierającej
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

WarehouseDocumentShow.layout = (page) => (
    <DashboardLayout title="Szczegóły dokumentu magazynowego">{page}</DashboardLayout>
);
