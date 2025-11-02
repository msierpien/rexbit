import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog.jsx';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu.jsx';
import CreateProductModal from '@/components/CreateProductModal.jsx';
import EditProductModal from '@/components/EditProductModal.jsx';
import { Loader2, Pencil, Trash2, Settings2, SlidersHorizontal } from 'lucide-react';

const perPageOptions = [10, 15, 30, 50];

const stockFilterOptions = [
    { value: 'available', label: 'Dostępne' },
    { value: 'out', label: 'Brak stanów' },
    { value: 'negative', label: 'Ujemne stany' },
];

const COLUMN_STORAGE_KEY = 'products:column-visibility';

const defaultColumnVisibility = {
    catalog: true,
    category: true,
    stock: true,
    warehouses: true,
    price: true,
    status: true,
    actions: true,
};

const columnDefinitions = [
    { key: 'catalog', label: 'Katalog' },
    { key: 'category', label: 'Kategoria' },
    { key: 'stock', label: 'Stan (razem)' },
    { key: 'warehouses', label: 'Magazyny' },
    { key: 'price', label: 'Cena netto' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Akcje' },
];

const quantityFormatter = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
});

const currencyFormatter = new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatQuantity = (value) => quantityFormatter.format(Number(value ?? 0));

const formatCurrency = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    const numeric = Number(value);
    return Number.isNaN(numeric) ? '—' : currencyFormatter.format(numeric);
};

function FilterSelect({ label, value, onChange, options, placeholder }) {
    return (
        <label className="flex flex-col gap-1 text-sm text-gray-700">
            <span>{label}</span>
            <select
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value || null)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="">{placeholder ?? 'Wszystkie'}</option>
                {options.map((option) => (
                    <option key={option.id ?? option.value} value={option.id ?? option.value}>
                        {option.name ?? option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

const Pagination = ({ meta }) => {
    if (!meta?.links || meta.links.length <= 3) {
        return null;
    }

    return (
        <nav className="mt-6 flex flex-wrap gap-2 text-sm">
            {meta.links.map((link, index) => (
                <button
                    key={index}
                    type="button"
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.visit(link.url, {
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                    className={`rounded-md px-3 py-1.5 ${
                        link.active
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-100 disabled:text-gray-400'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
};

const STOCK_MODAL_INITIAL_STATE = {
    open: false,
    loading: false,
    product: null,
    stocks: [],
    history: [],
    summary: null,
    error: null,
};

function getInitialColumnVisibility() {
    if (typeof window === 'undefined') {
        return defaultColumnVisibility;
    }

    const cached = window.localStorage.getItem(COLUMN_STORAGE_KEY);
    if (!cached) {
        return defaultColumnVisibility;
    }

    try {
        const parsed = JSON.parse(cached);
        return {
            ...defaultColumnVisibility,
            ...parsed,
        };
    } catch (error) {
        console.error('Nie udało się odczytać ustawień kolumn', error);
        return defaultColumnVisibility;
    }
}

export default function ProductsIndex() {
    const { products, filters, options, can, flash } = usePage().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [stockFilter, setStockFilter] = useState(filters.stock ?? '');
    const [priceMin, setPriceMin] = useState(filters.price_min ?? '');
    const [priceMax, setPriceMax] = useState(filters.price_max ?? '');
    const [selectedIds, setSelectedIds] = useState([]);
    const [deselectedIds, setDeselectedIds] = useState([]);
    const [isAllSelected, setIsAllSelected] = useState(false);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);
    const [stockModal, setStockModal] = useState(() => ({ ...STOCK_MODAL_INITIAL_STATE }));
    const [columnVisibility, setColumnVisibility] = useState(getInitialColumnVisibility);
    const [isColumnDialogOpen, setIsColumnDialogOpen] = useState(false);

    const filterCategories = useMemo(() => {
        if (!filters.catalog) {
            return options.categories;
        }

        return options.categories.filter((category) => category.catalog_id === Number(filters.catalog));
    }, [filters.catalog, options.categories]);

    useEffect(() => {
        setSearch(filters.search ?? '');
    }, [filters.search]);

    useEffect(() => {
        setStatusFilter(filters.status ?? '');
        setStockFilter(filters.stock ?? '');
        setPriceMin(filters.price_min ?? '');
        setPriceMax(filters.price_max ?? '');
    }, [filters.status, filters.stock, filters.price_min, filters.price_max]);

    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(columnVisibility));
        }
    }, [columnVisibility]);

    const buildFilters = (overrides = {}) => {
        const base = {
            ...filters,
            search,
            status: statusFilter || undefined,
            stock: stockFilter || undefined,
            price_min: priceMin || undefined,
            price_max: priceMax || undefined,
        };

        delete base.view;

        const merged = { ...base, ...overrides };

        return Object.fromEntries(
            Object.entries(merged).filter(
                ([, value]) => value !== undefined && value !== null && value !== ''
            )
        );
    };

    const updateFilters = (overrides = {}) => {
        router.get('/products', buildFilters(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    useEffect(() => {
        const handler = setTimeout(() => {
            if ((filters.search ?? '') !== search) {
                updateFilters({ search: search || undefined });
            }
        }, 350);

        return () => clearTimeout(handler);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const totalProducts = products.meta?.total ?? products.data.length;
    const pageIds = useMemo(() => products.data.map((product) => product.id), [products.data]);
    const pageCheckboxRef = useRef(null);

    const isRowSelected = (id) => (isAllSelected ? !deselectedIds.includes(id) : selectedIds.includes(id));

    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => isRowSelected(id));
    const somePageSelected =
        !allPageSelected && pageIds.some((id) => isRowSelected(id));
    const selectedCount = isAllSelected
        ? Math.max(totalProducts - deselectedIds.length, 0)
        : selectedIds.length;
    const selectionEmpty = selectedCount === 0;

    useEffect(() => {
        if (pageCheckboxRef.current) {
            pageCheckboxRef.current.indeterminate = somePageSelected;
        }
    }, [somePageSelected]);

    const updateCollection = (collection, ids, shouldAdd) => {
        const next = new Set(collection);
        ids.forEach((id) => {
            if (shouldAdd) {
                next.add(id);
            } else {
                next.delete(id);
            }
        });
        return Array.from(next);
    };

    const handleToggleRow = (productId, checked) => {
        if (isAllSelected) {
            setDeselectedIds((current) => updateCollection(current, [productId], !checked));
            return;
        }

        setSelectedIds((current) => updateCollection(current, [productId], checked));
    };

    const handleTogglePage = (checked) => {
        if (!pageIds.length) {
            return;
        }

        if (isAllSelected) {
            setDeselectedIds((current) => updateCollection(current, pageIds, !checked));
            return;
        }

        setSelectedIds((current) => updateCollection(current, pageIds, checked));
    };

    const handleSelectAllAcrossPages = () => {
        setIsAllSelected(true);
        setSelectedIds([]);
        setDeselectedIds([]);
    };

    const handleClearSelection = () => {
        setIsAllSelected(false);
        setSelectedIds([]);
        setDeselectedIds([]);
    };

    const handleStatusChange = (value) => {
        const normalized = value || undefined;
        setStatusFilter(value ?? '');
        updateFilters({ status: normalized });
    };

    const handleStockChange = (value) => {
        const normalized = value || undefined;
        setStockFilter(value ?? '');
        updateFilters({ stock: normalized });
    };

    const handleApplyPrice = () => {
        updateFilters({
            price_min: priceMin || undefined,
            price_max: priceMax || undefined,
        });
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatusFilter('');
        setStockFilter('');
        setPriceMin('');
        setPriceMax('');

        updateFilters({
            search: undefined,
            status: undefined,
            stock: undefined,
            price_min: undefined,
            price_max: undefined,
            catalog: undefined,
            category: undefined,
        });
    };

    const handlePerPageChange = (event) => {
        updateFilters({ per_page: Number(event.target.value) });
    };

    const handleCatalogChange = (catalog) => {
        updateFilters({ catalog, category: null });
    };

    const handleCategoryChange = (category) => {
        updateFilters({ category });
    };

    const handleDeleteProduct = (product) => {
        if (!product) {
            return;
        }

        if (confirm(`Czy na pewno chcesz usunąć produkt "${product.name}"?`)) {
            router.delete(`/products/${product.id}`, {
                preserveScroll: true,
            });
        }
    };

    const openCreateModal = () => setIsCreateOpen(true);
    const closeCreateModal = () => setIsCreateOpen(false);

    const openEditModal = (product) => {
        setEditingProduct(product);
        setIsEditOpen(true);
    };

    const closeEditModal = () => {
        setEditingProduct(null);
        setIsEditOpen(false);
    };

    const openStockHistory = async (product) => {
        setStockModal({
            ...STOCK_MODAL_INITIAL_STATE,
            open: true,
            loading: true,
            product,
        });

        try {
            const response = await fetch(`/products/${product.id}/stock-history`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const data = await response.json();

            setStockModal((current) => ({
                ...current,
                loading: false,
                history: data.history ?? [],
                stocks: data.stocks ?? [],
                summary: data.summary ?? null,
                error: null,
            }));
        } catch (error) {
            console.error(error);
            setStockModal((current) => ({
                ...current,
                loading: false,
                error: 'Nie udało się pobrać historii magazynowej. Spróbuj ponownie.',
            }));
        }
    };

    const closeStockHistory = () => {
        setStockModal({ ...STOCK_MODAL_INITIAL_STATE });
    };

    const isColumnVisible = (key) => columnVisibility[key] !== false;

    const hasActiveFilters =
        Boolean(filters.search ?? filters.status ?? filters.stock ?? filters.price_min ?? filters.price_max ?? filters.catalog ?? filters.category) ||
        Boolean(search || statusFilter || stockFilter || priceMin || priceMax);

    return (
        <>
            <Head title="Produkty" />
            <div className="flex flex-col gap-6">
                {flash?.status && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.status}
                    </div>
                )}

                <div className="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <div className="grid gap-4 lg:grid-cols-4">
                        <label className="flex flex-col gap-1 text-sm text-gray-700">
                            <span>Nazwa lub SKU</span>
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Wyszukaj produkt..."
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </label>
                        <FilterSelect
                            label="Status"
                            value={statusFilter}
                            onChange={handleStatusChange}
                            options={options.statuses}
                            placeholder="Wszystkie"
                        />
                        <FilterSelect
                            label="Stan magazynowy"
                            value={stockFilter}
                            onChange={handleStockChange}
                            options={stockFilterOptions}
                            placeholder="Dowolny"
                        />
                        <div className="flex flex-col gap-1 text-sm text-gray-700">
                            <span>Cena netto</span>
                            <div className="flex items-center gap-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={priceMin}
                                    onChange={(event) => setPriceMin(event.target.value)}
                                    placeholder="Od"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                <span className="text-gray-400">—</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={priceMax}
                                    onChange={(event) => setPriceMax(event.target.value)}
                                    placeholder="Do"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                            <div className="flex gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={handleApplyPrice}
                                >
                                    <SlidersHorizontal className="size-4" />
                                    Zastosuj
                                </Button>
                                {hasActiveFilters && (
                                    <Button type="button" variant="ghost" size="sm" onClick={handleResetFilters}>
                                        Wyczyść
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <FilterSelect
                            label="Katalog"
                            value={filters.catalog}
                            onChange={handleCatalogChange}
                            options={options.catalogs}
                            placeholder="Wszystkie katalogi"
                        />
                        <FilterSelect
                            label="Kategoria"
                            value={filters.category}
                            onChange={handleCategoryChange}
                            options={filterCategories}
                            placeholder={filterCategories.length ? 'Wszystkie kategorie' : 'Brak kategorii'}
                        />
                        <label className="flex flex-col gap-1 text-sm text-gray-700">
                            <span>Na stronie</span>
                            <select
                                value={filters.per_page ?? 15}
                                onChange={handlePerPageChange}
                                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                {perPageOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="flex items-end justify-end gap-2">
                            <Dialog open={isColumnDialogOpen} onOpenChange={setIsColumnDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button type="button" variant="outline" size="icon">
                                        <Settings2 className="size-4" />
                                        <span className="sr-only">Dostosuj kolumny</span>
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>Widoczne kolumny</DialogTitle>
                                        <DialogDescription>
                                            Wybierz, które kolumny mają być widoczne na liście produktów.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-3">
                                        {columnDefinitions.map((column) => (
                                            <label
                                                key={column.key}
                                                className="flex items-center gap-3 rounded-lg border border-border px-3 py-2 hover:border-primary/60"
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary"
                                                    checked={isColumnVisible(column.key)}
                                                    onChange={(event) =>
                                                        setColumnVisibility((current) => ({
                                                            ...current,
                                                            [column.key]: event.target.checked,
                                                        }))
                                                    }
                                                />
                                                <span className="text-sm font-medium text-foreground">{column.label}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setColumnVisibility(defaultColumnVisibility)}
                                        >
                                            Przywróć domyślne
                                        </Button>
                                        <Button type="button" onClick={() => setIsColumnDialogOpen(false)}>
                                            Zamknij
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>

                            {can.create && (
                                <Button type="button" onClick={openCreateModal}>
                                    Dodaj produkt
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <span className="text-sm text-gray-600">
                            Wybrano <strong>{selectedCount}</strong> z {totalProducts} produktów
                        </span>
                        <div className="flex flex-wrap items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="outline" size="sm">
                                        Zarządzaj zaznaczeniem
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="w-52">
                                    <DropdownMenuItem onSelect={() => handleTogglePage(true)}>
                                        Zaznacz bieżącą stronę
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onSelect={() => handleTogglePage(false)}>
                                        Odznacz bieżącą stronę
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onSelect={handleSelectAllAcrossPages}>
                                        Zaznacz wszystkie wyniki
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onSelect={handleClearSelection}>
                                        Wyczyść zaznaczenie
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleClearSelection}
                                disabled={selectionEmpty}
                            >
                                Wyczyść wszystko
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={selectionEmpty}
                                onClick={() => {}}
                            >
                                Zmień status
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={selectionEmpty}
                                onClick={() => {}}
                            >
                                Przypisz kategorię
                            </Button>
                        </div>
                    </div>

                    {!isAllSelected && allPageSelected && totalProducts > pageIds.length ? (
                        <div className="mb-4 rounded-lg border border-dashed border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-700">
                            Zaznaczono wszystkie produkty na tej stronie.{' '}
                            <button
                                type="button"
                                className="font-semibold text-blue-700 underline-offset-2 hover:underline"
                                onClick={handleSelectAllAcrossPages}
                            >
                                Zaznacz wszystkie {totalProducts} produkty.
                            </button>
                        </div>
                    ) : null}

                    {isAllSelected && deselectedIds.length > 0 ? (
                        <div className="mb-4 rounded-lg border border-dashed border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-700">
                            Zaznaczono wszystkie produkty oprócz {deselectedIds.length}. Odznacz konkretne pozycje, aby je
                            wyłączyć z działań zbiorczych.
                        </div>
                    ) : null}

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        <input
                                            ref={pageCheckboxRef}
                                            type="checkbox"
                                            checked={allPageSelected}
                                            onChange={(event) => handleTogglePage(event.target.checked)}
                                            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </th>
                                    <th className="px-4 py-3">Produkt</th>
                                    {isColumnVisible('catalog') && <th className="px-4 py-3">Katalog</th>}
                                    {isColumnVisible('category') && <th className="px-4 py-3">Kategoria</th>}
                                    {isColumnVisible('stock') && <th className="px-4 py-3">Stan (razem)</th>}
                                    {isColumnVisible('warehouses') && <th className="px-4 py-3">Magazyny</th>}
                                    {isColumnVisible('price') && <th className="px-4 py-3 text-right">Cena netto</th>}
                                    {isColumnVisible('status') && <th className="px-4 py-3">Status</th>}
                                    {isColumnVisible('actions') && <th className="px-4 py-3 text-right">Akcje</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {products.data.map((product) => (
                                    <tr key={product.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                checked={isRowSelected(product.id)}
                                                onChange={(event) => handleToggleRow(product.id, event.target.checked)}
                                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {product.images && product.images.length > 0 ? (
                                                    <img
                                                        src={product.images[0]}
                                                        alt={product.name}
                                                        className="h-10 w-10 rounded object-cover"
                                                        onError={(event) => {
                                                            // eslint-disable-next-line no-param-reassign
                                                            event.currentTarget.style.display = 'none';
                                                        }}
                                                    />
                                                ) : (
                                                    <div className="flex h-10 w-10 items-center justify-center rounded border border-dashed border-gray-200 text-xs text-gray-400">
                                                        Brak
                                                    </div>
                                                )}
                                                <div>
                                                    <div className="font-semibold text-gray-900">{product.name}</div>
                                                    <div className="flex flex-wrap gap-3 text-xs text-gray-500">
                                                        {product.sku && <span>SKU: {product.sku}</span>}
                                                        {product.ean && <span>EAN: {product.ean}</span>}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {isColumnVisible('catalog') && (
                                            <td className="px-4 py-3 text-gray-600">{product.catalog?.name ?? '—'}</td>
                                        )}
                                        {isColumnVisible('category') && (
                                            <td className="px-4 py-3 text-gray-600">{product.category?.name ?? '—'}</td>
                                        )}
                                        {isColumnVisible('stock') && (
                                            <td className="px-4 py-3 text-gray-600">
                                                {product.stock_summary ? (
                                                    <div className="space-y-1 text-xs">
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-gray-500">Dostępne</span>
                                                            <span className="font-semibold text-gray-900">
                                                                {formatQuantity(product.stock_summary.total_available)} szt.
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-gray-500">Na stanie</span>
                                                            <span>{formatQuantity(product.stock_summary.total_on_hand)} szt.</span>
                                                        </div>
                                                        {Number(product.stock_summary.total_reserved) > 0 && (
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-gray-500">Rezerwacje</span>
                                                                <span>{formatQuantity(product.stock_summary.total_reserved)} szt.</span>
                                                            </div>
                                                        )}
                                                        {Number(product.stock_summary.total_incoming) > 0 && (
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-gray-500">W drodze</span>
                                                                <span>{formatQuantity(product.stock_summary.total_incoming)} szt.</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-gray-400">Brak danych</span>
                                                )}
                                            </td>
                                        )}
                                        {isColumnVisible('warehouses') && (
                                            <td className="px-4 py-3 text-gray-600">
                                                <div className="space-y-1">
                                                    {product.stocks && product.stocks.length > 0 ? (
                                                        product.stocks.map((stock) => (
                                                            <div
                                                                key={`${product.id}-${stock.warehouse_id ?? 'none'}`}
                                                                className="flex items-center justify-between text-xs"
                                                            >
                                                                <span className="truncate pr-2">{stock.warehouse_name}</span>
                                                                <span className="font-semibold text-gray-900">
                                                                    {formatQuantity(stock.available)} szt.
                                                                </span>
                                                            </div>
                                                        ))
                                                    ) : (
                                                        <span className="text-xs text-gray-400">Brak danych</span>
                                                    )}
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="mt-2"
                                                    onClick={() => openStockHistory(product)}
                                                >
                                                    Historia
                                                </Button>
                                            </td>
                                        )}
                                        {isColumnVisible('price') && (
                                            <td className="px-4 py-3 text-right text-gray-600">
                                                {formatCurrency(product.sale_price_net)}
                                            </td>
                                        )}
                                        {isColumnVisible('status') && (
                                            <td className="px-4 py-3">
                                                <Badge variant="outline" className="bg-blue-50 text-blue-600">
                                                    {product.status_label}
                                                </Badge>
                                            </td>
                                        )}
                                        {isColumnVisible('actions') && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => openEditModal(product)}
                                                        title="Edytuj produkt"
                                                    >
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edytuj</span>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => handleDeleteProduct(product)}
                                                        title="Usuń produkt"
                                                    >
                                                        <Trash2 className="size-4" />
                                                        <span className="sr-only">Usuń</span>
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Pagination meta={products.meta} />
                </div>

                <Dialog open={stockModal.open} onOpenChange={(open) => !open && closeStockHistory()}>
                    <DialogContent className="max-w-4xl">
                        <DialogHeader>
                            <DialogTitle>
                                Historia stanów magazynowych
                                {stockModal.product ? ` — ${stockModal.product.name}` : ''}
                            </DialogTitle>
                            <DialogDescription>
                                Podgląd dostępności produktu w magazynach oraz ostatnich operacji magazynowych.
                            </DialogDescription>
                        </DialogHeader>

                        {stockModal.loading ? (
                            <div className="flex items-center justify-center py-12 text-sm text-gray-500">
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Ładowanie danych magazynowych...
                            </div>
                        ) : stockModal.error ? (
                            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {stockModal.error}
                            </div>
                        ) : (
                            <div className="space-y-6">
                                <div className="rounded-lg border border-gray-200">
                                    <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                        Podsumowanie
                                    </div>
                                    <div className="grid gap-4 px-4 py-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <p className="text-xs text-gray-500">Dostępne</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatQuantity(stockModal.summary?.total_available ?? 0)} szt.
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">Na stanie</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatQuantity(stockModal.summary?.total_on_hand ?? 0)} szt.
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">Rezerwacje</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatQuantity(stockModal.summary?.total_reserved ?? 0)} szt.
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">W drodze</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatQuantity(stockModal.summary?.total_incoming ?? 0)} szt.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-gray-200">
                                    <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                        Magazyny
                                    </div>
                                    {stockModal.stocks && stockModal.stocks.length > 0 ? (
                                        <div className="grid gap-4 px-4 py-3 sm:grid-cols-2">
                                            {stockModal.stocks.map((stock) => (
                                                <div key={stock.warehouse_id} className="rounded-lg border border-gray-100 p-3">
                                                    <div className="text-sm font-semibold text-gray-900">
                                                        {stock.warehouse_name ?? '—'}
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                                                        <span>
                                                            Dostępne:{' '}
                                                            <span className="font-semibold text-gray-900">
                                                                {formatQuantity(stock.available)} szt.
                                                            </span>
                                                        </span>
                                                        <span>Na stanie: {formatQuantity(stock.on_hand)} szt.</span>
                                                        {Number(stock.reserved) > 0 && (
                                                            <span>Rezerwacje: {formatQuantity(stock.reserved)} szt.</span>
                                                        )}
                                                        {Number(stock.incoming) > 0 && (
                                                            <span>W drodze: {formatQuantity(stock.incoming)} szt.</span>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="px-4 py-3 text-sm text-gray-500">Brak danych magazynowych.</div>
                                    )}
                                </div>

                                <div className="rounded-lg border border-gray-200">
                                    <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                        Ostatnie operacje
                                    </div>
                                    {stockModal.history && stockModal.history.length > 0 ? (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                    <tr>
                                                        <th className="px-4 py-2">Data</th>
                                                        <th className="px-4 py-2">Dokument</th>
                                                        <th className="px-4 py-2">Magazyn</th>
                                                        <th className="px-4 py-2">Zmiana</th>
                                                        <th className="px-4 py-2">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-200">
                                                    {stockModal.history.map((entry) => (
                                                        <tr key={entry.id}>
                                                            <td className="px-4 py-2 text-gray-600">
                                                                {entry.issued_at ?? entry.created_at ?? '—'}
                                                            </td>
                                                            <td className="px-4 py-2">
                                                                <div className="font-medium text-gray-900">
                                                                    {entry.document_number ?? '—'}
                                                                </div>
                                                                <div className="text-xs text-gray-500">{entry.document_type}</div>
                                                            </td>
                                                            <td className="px-4 py-2 text-gray-600">
                                                                {entry.warehouse_name ?? '—'}
                                                            </td>
                                                            <td className="px-4 py-2">
                                                                <span
                                                                    className={
                                                                        entry.quantity_change >= 0
                                                                            ? 'font-semibold text-emerald-600'
                                                                            : 'font-semibold text-rose-600'
                                                                    }
                                                                >
                                                                    {entry.quantity_change >= 0 ? '+' : ''}
                                                                    {formatQuantity(entry.quantity_change)} szt.
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-2">
                                                                {entry.document_status_label ? (
                                                                    <Badge variant="outline">{entry.document_status_label}</Badge>
                                                                ) : (
                                                                    <span className="text-xs text-gray-400">—</span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="px-4 py-3 text-sm text-gray-500">
                                            Brak zarejestrowanych operacji magazynowych.
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                        <DialogFooter className="mt-4">
                            {!stockModal.loading && stockModal.product && (
                                <Button variant="outline" onClick={() => openStockHistory(stockModal.product)}>
                                    Odśwież
                                </Button>
                            )}
                            <Button variant="outline" onClick={closeStockHistory}>
                                Zamknij
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <CreateProductModal
                    open={isCreateOpen}
                    onClose={closeCreateModal}
                    catalogs={options.catalogs}
                    categories={options.categories}
                    manufacturers={options.manufacturers}
                />

                <EditProductModal
                    open={isEditOpen}
                    onClose={closeEditModal}
                    product={editingProduct}
                    catalogs={options.catalogs}
                    categories={options.categories}
                    manufacturers={options.manufacturers}
                />
            </div>
        </>
    );
}

ProductsIndex.layout = (page) => <DashboardLayout title="Produkty">{page}</DashboardLayout>;
