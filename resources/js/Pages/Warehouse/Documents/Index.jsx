import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
import { Checkbox } from '@/components/ui/checkbox.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.jsx';
import { Textarea } from '@/components/ui/textarea.jsx';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import DocumentStatusActions from '@/components/warehouse/document-status-actions.jsx';
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

function StatusBadge({ status, label }) {
    return (
        <Badge variant="outline" className={cn('border', statusClasses[status] ?? '')}>
            {label}
        </Badge>
    );
}

function SummaryCard({ title, value, hint }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold text-foreground">{value}</p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}

function Pagination({ documents }) {
    if (!documents.meta?.links?.length) {
        return null;
    }

    return (
        <div className="mt-6 flex flex-wrap gap-2">
            {documents.meta.links.map((link, index) => (
                <Button
                    key={index}
                    type="button"
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.visit(link.url, {
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 p-12 text-center text-sm text-muted-foreground">
            <p className="font-medium text-foreground">Brak dokumentów magazynowych</p>
            <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                Dodaj pierwszy dokument, aby rozpocząć śledzenie przyjęć, wydań i innych operacji magazynowych.
            </p>
            <Button className="mt-6" asChild>
                <Link href="/warehouse/documents/create">Nowy dokument</Link>
            </Button>
        </div>
    );
}

function DocumentTable({
    documents,
    onDelete,
    selectedIds,
    onToggleAll,
    onToggleOne,
    onNavigate,
}) {
    const rows = documents.data ?? [];

    if (!rows.length) {
        return <EmptyState />;
    }

    const allSelected = rows.length > 0 && selectedIds.length === rows.length;

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/30">
                        <TableHead className="w-12">
                            <Checkbox
                                checked={allSelected}
                                onChange={(event) => onToggleAll(event.target.checked)}
                                onClick={(event) => event.stopPropagation()}
                                aria-label="Zaznacz wszystkie dokumenty"
                            />
                        </TableHead>
                        <TableHead>Rodzaj</TableHead>
                        <TableHead>Nr</TableHead>
                        <TableHead>Faktura</TableHead>
                        <TableHead>Nr zam.</TableHead>
                        <TableHead>Magazyn</TableHead>
                        <TableHead>Dostawca / Odbiorca</TableHead>
                        <TableHead>Data wystawienia</TableHead>
                        <TableHead>Ilość</TableHead>
                        <TableHead>Wartość netto</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Utworzono</TableHead>
                        <TableHead className="w-44 text-right">Akcje</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((document) => {
                        const isSelected = selectedIds.includes(document.id);

                        return (
                            <TableRow
                                key={document.id}
                                onClick={() => onNavigate(document.id)}
                                className="cursor-pointer"
                            >
                                <TableCell>
                                    <Checkbox
                                        checked={isSelected}
                                        onChange={(event) => {
                                            event.stopPropagation();
                                            onToggleOne(document.id, event.target.checked);
                                        }}
                                        onClick={(event) => event.stopPropagation()}
                                        aria-label={`Zaznacz dokument ${document.number}`}
                                    />
                                </TableCell>
                                <TableCell className="font-medium">{document.type}</TableCell>
                                <TableCell>
                                    <Link
                                        href={`/warehouse/documents/${document.id}`}
                                        className="font-semibold text-primary hover:underline"
                                        onClick={(event) => event.stopPropagation()}
                                    >
                                        {document.number}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {document.metadata?.invoice_number ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {document.metadata?.order_number ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {document.warehouse?.name ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {document.contractor?.name ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">{document.issued_at ?? '—'}</TableCell>
                                <TableCell className="text-right font-medium">{formatQuantity(document.total_quantity)}</TableCell>
                                <TableCell className="text-right font-medium">{formatCurrency(document.total_net_value)}</TableCell>
                                <TableCell>
                                    <StatusBadge status={document.status} label={document.status_label} />
                                </TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {document.created_at ?? '—'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center justify-end gap-2">
                                        <DocumentStatusActions document={document} />
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            disabled={!document.can_be_edited}
                                            title={
                                                document.can_be_edited
                                                    ? 'Edytuj dokument'
                                                    : 'Tylko dokumenty w statusie roboczym mogą być edytowane'
                                            }
                                            onClick={(event) => event.stopPropagation()}
                                        >
                                            <Link href={`/warehouse/documents/${document.id}/edit`}>Edytuj</Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            disabled={!document.can_be_deleted}
                                            title={document.deletion_block_reason || ''}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onDelete(document);
                                            }}
                                        >
                                            Usuń
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

function WarehouseDocumentsIndex() {
    const { documents, statusOptions = [], totals = {}, filters = {}, flash } = usePage().props;

    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkAction, setBulkAction] = useState('');
    const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
    const [bulkReason, setBulkReason] = useState('');

    const statusFilterValue = filters.status || 'all';

    const summary = useMemo(
        () => ({
            totalDocuments: totals.total_documents ?? documents.total ?? 0,
            totalQuantity: totals.total_quantity ?? 0,
            totalNetValue: totals.total_net_value ?? 0,
        }),
        [totals, documents.total]
    );

    useEffect(() => {
        setSelectedIds([]);
    }, [documents.meta?.current_page, statusFilterValue]);

    const handleDelete = (document) => {
        if (!document.can_be_deleted) {
            alert(document.deletion_block_reason || 'Nie można usunąć tego dokumentu.');
            return;
        }

        if (confirm(`Usunąć dokument ${document.number}?`)) {
            router.delete(`/warehouse/documents/${document.id}`, {
                preserveScroll: true,
                onSuccess: () => setSelectedIds((current) => current.filter((id) => id !== document.id)),
            });
        }
    };

    const handleNavigate = (documentId) => {
        router.visit(`/warehouse/documents/${documentId}`);
    };

    const handleToggleAll = (checked) => {
        setSelectedIds(checked ? documents.data.map((document) => document.id) : []);
    };

    const handleToggleOne = (documentId, checked) => {
        setSelectedIds((current) =>
            checked ? [...current, documentId] : current.filter((id) => id !== documentId)
        );
    };

    const handleStatusFilterChange = (value) => {
        router.get(
            '/warehouse/documents',
            { status: value === 'all' ? undefined : value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const submitBulkStatus = (payload = {}) => {
        router.post(
            '/warehouse/documents/bulk-status',
            {
                action: bulkAction,
                document_ids: selectedIds,
                ...payload,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setBulkAction('');
                    setBulkReason('');
                },
            }
        );
    };

    const handleBulkAction = () => {
        if (!bulkAction || selectedIds.length === 0) {
            return;
        }

        if (bulkAction === 'cancel') {
            setBulkDialogOpen(true);
        } else {
            const confirmMessage =
                bulkAction === 'post'
                    ? 'Czy na pewno chcesz zatwierdzić zaznaczone dokumenty?'
                    : 'Czy na pewno chcesz zarchiwizować zaznaczone dokumenty?';

            if (confirm(confirmMessage)) {
                submitBulkStatus();
            }
        }
    };

    const bulkActionDisabled = selectedIds.length === 0 || !bulkAction;

    return (
        <>
            <Head title="Dokumenty magazynowe" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Dokumenty magazynowe</h2>
                        <p className="text-sm text-muted-foreground">
                            Zarządzaj dokumentami magazynowymi PZ, WZ oraz innymi operacjami.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/warehouse/documents/create">Nowy dokument</Link>
                    </Button>
                </div>

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

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        title="Dokumenty w wynikach"
                        value={summary.totalDocuments}
                        hint="Łączna liczba dokumentów spełniających bieżące kryteria."
                    />
                    <SummaryCard
                        title="Łączna ilość (strona)"
                        value={formatQuantity(summary.totalQuantity)}
                        hint="Suma ilości pozycji na wyświetlanej stronie."
                    />
                    <SummaryCard
                        title="Wartość netto (strona)"
                        value={formatCurrency(summary.totalNetValue)}
                        hint="Suma wartości netto pozycji na wyświetlanej stronie."
                    />
                </div>

                <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-wrap items-center gap-3">
                        <div>
                            <p className="text-xs font-medium uppercase text-muted-foreground">Filtr statusu</p>
                            <Select value={statusFilterValue} onValueChange={handleStatusFilterChange}>
                                <SelectTrigger className="mt-1 w-[200px]">
                                    <SelectValue placeholder="Wybierz status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Wszystkie statusy</SelectItem>
                                    {statusOptions.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <p className="text-xs font-medium uppercase text-muted-foreground">Zmień status</p>
                            <Select
                                value={bulkAction || 'none'}
                                onValueChange={(value) => setBulkAction(value === 'none' ? '' : value)}
                            >
                                <SelectTrigger className="mt-1 w-[220px]">
                                    <SelectValue placeholder="Wybierz akcję" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Wybierz akcję</SelectItem>
                                    <SelectItem value="post">Zatwierdź</SelectItem>
                                    <SelectItem value="cancel">Anuluj</SelectItem>
                                    <SelectItem value="archive">Archiwizuj</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="button"
                            onClick={handleBulkAction}
                            disabled={bulkActionDisabled}
                            className="self-start"
                        >
                            Zastosuj do zaznaczonych ({selectedIds.length})
                        </Button>
                    </div>
                </div>

                <DocumentTable
                    documents={documents}
                    selectedIds={selectedIds}
                    onDelete={handleDelete}
                    onToggleAll={handleToggleAll}
                    onToggleOne={handleToggleOne}
                    onNavigate={handleNavigate}
                />

                <Pagination documents={documents} />
            </div>

            <Dialog open={bulkDialogOpen} onOpenChange={setBulkDialogOpen}>
                <DialogContent className="max-w-lg" onInteractOutside={(event) => event.preventDefault()}>
                    <DialogHeader>
                        <DialogTitle>Anuluj dokumenty</DialogTitle>
                        <DialogDescription>
                            Opcjonalnie podaj powód anulowania. Zmiana statusu obejmie {selectedIds.length}{' '}
                            dokumenty.
                        </DialogDescription>
                    </DialogHeader>

                    <Textarea
                        value={bulkReason}
                        onChange={(event) => setBulkReason(event.target.value)}
                        placeholder="Powód anulowania (maksymalnie 500 znaków)"
                        maxLength={500}
                    />
                    <p className="text-xs text-muted-foreground">{bulkReason.length}/500 znaków</p>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => {
                                setBulkDialogOpen(false);
                                setBulkReason('');
                            }}
                        >
                            Zamknij
                        </Button>
                        <Button
                            variant="destructive"
                            type="button"
                            onClick={() => {
                                submitBulkStatus({ reason: bulkReason });
                                setBulkDialogOpen(false);
                            }}
                        >
                            Potwierdź anulowanie
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

WarehouseDocumentsIndex.layout = (page) => (
    <DashboardLayout title="Dokumenty magazynowe">{page}</DashboardLayout>
);

export default WarehouseDocumentsIndex;
