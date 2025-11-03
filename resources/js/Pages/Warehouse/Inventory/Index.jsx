import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert.jsx';
import { Eye, Edit, Trash2, Play, CheckCircle, XCircle, Clock } from 'lucide-react';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: 'PLN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
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

export default function InventoryCountIndex() {
    const { inventoryCounts, warehouses, statusOptions, filters, flash } = usePage().props;
    const [localFilters, setLocalFilters] = useState({
        status: filters.status || '',
        warehouse_id: filters.warehouse_id || '',
    });

    const handleFilterChange = (key, value) => {
        const newFilters = { ...localFilters, [key]: value === 'all' ? '' : value };
        setLocalFilters(newFilters);
        
        router.get('/inventory-counts', newFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (inventoryCount) => {
        if (confirm(`Czy na pewno chcesz usunąć inwentaryzację "${inventoryCount.name}"?`)) {
            router.delete(`/inventory-counts/${inventoryCount.id}`);
        }
    };

    const canDelete = (inventoryCount) => {
        return ['draft', 'cancelled'].includes(inventoryCount.status);
    };

    return (
        <>
            <Head title="Inwentaryzacje magazynowe" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Inwentaryzacje magazynowe</h2>
                        <p className="text-sm text-muted-foreground">
                            Zarządzaj inwentaryzacjami i kontroluj stany magazynowe za pomocą skanera EAN.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/inventory-counts/create">Nowa inwentaryzacja</Link>
                    </Button>
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

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filtry</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Status</label>
                                <Select
                                    value={localFilters.status || 'all'}
                                    onValueChange={(value) => handleFilterChange('status', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Wszystkie statusy" />
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
                            
                            <div>
                                <label className="text-sm font-medium">Magazyn</label>
                                <Select
                                    value={localFilters.warehouse_id || 'all'}
                                    onValueChange={(value) => handleFilterChange('warehouse_id', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Wszystkie magazyny" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Wszystkie magazyny</SelectItem>
                                        {warehouses.map((warehouse) => (
                                            <SelectItem key={warehouse.value} value={warehouse.value}>
                                                {warehouse.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Inventory Counts Table */}
                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nazwa</TableHead>
                                        <TableHead>Magazyn</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Produkty</TableHead>
                                        <TableHead>Rozbieżności</TableHead>
                                        <TableHead>Utworzona</TableHead>
                                        <TableHead>Akcje</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {inventoryCounts.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-24 text-center">
                                                <div className="flex flex-col items-center gap-2">
                                                    <p className="text-sm text-muted-foreground">
                                                        Brak inwentaryzacji spełniających kryteria
                                                    </p>
                                                    <Button asChild size="sm">
                                                        <Link href="/inventory-counts/create">
                                                            Utwórz pierwszą inwentaryzację
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        inventoryCounts.data.map((inventoryCount) => (
                                            <TableRow key={inventoryCount.id}>
                                                <TableCell className="font-medium">
                                                    <div>
                                                        <div className="font-semibold">{inventoryCount.name}</div>
                                                        {inventoryCount.description && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {inventoryCount.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>{inventoryCount.warehouse_name}</TableCell>
                                                <TableCell>
                                                    <Badge className={getStatusColor(inventoryCount.status)}>
                                                        {getStatusIcon(inventoryCount.status)}
                                                        {inventoryCount.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-center">
                                                        <div className="font-semibold">
                                                            {inventoryCount.total_products || 0}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            policzonych
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-center">
                                                        <div className={`font-semibold ${
                                                            inventoryCount.total_discrepancies > 0 
                                                                ? 'text-amber-600' 
                                                                : 'text-emerald-600'
                                                        }`}>
                                                            {inventoryCount.total_discrepancies || 0}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            rozbieżności
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {inventoryCount.created_at}
                                                    </div>
                                                    {inventoryCount.counted_by && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {inventoryCount.counted_by}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link href={`/inventory-counts/${inventoryCount.id}`}>
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        
                                                        {['draft', 'in_progress'].includes(inventoryCount.status) && (
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link href={`/inventory-counts/${inventoryCount.id}/edit`}>
                                                                    <Edit className="h-4 w-4" />
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        
                                                        {canDelete(inventoryCount) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleDelete(inventoryCount)}
                                                                className="text-red-600 hover:text-red-700"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
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

                {/* Pagination */}
                {inventoryCounts.last_page > 1 && (
                    <div className="flex items-center justify-center space-x-2">
                        {inventoryCounts.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

InventoryCountIndex.layout = (page) => (
    <DashboardLayout title="Inwentaryzacje magazynowe">{page}</DashboardLayout>
);