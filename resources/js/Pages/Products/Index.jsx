import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useReducer, useRef, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import ProductsFilters from '@/components/products/products-filters.jsx';
import ColumnVisibilityDialog from '@/components/products/column-visibility-dialog.jsx';
import SelectionToolbar from '@/components/products/selection-toolbar.jsx';
import ProductsTable from '@/components/products/products-table.jsx';
import StockHistoryDialog from '@/components/products/stock-history-dialog.jsx';
import CreateProductModal from '@/components/CreateProductModal.jsx';
import EditProductModal from '@/components/EditProductModal.jsx';
import { Settings2 } from 'lucide-react';

const perPageOptions = [10, 15, 30, 50];

const stockFilterOptions = [
    { value: 'available', label: 'Dostępne' },
    { value: 'out', label: 'Brak stanów' },
    { value: 'negative', label: 'Ujemne stany' },
];

const COLUMN_STORAGE_KEY = 'products:column-visibility';

const defaultColumnVisibility = {
    id: true,
    sku: true,
    catalog: true,
    category: true,
    stock: true,
    warehouses: true,
    price: true,
    status: true,
    actions: true,
};

const columnDefinitions = [
    { key: 'id', label: 'ID produktu' },
    { key: 'sku', label: 'SKU' },
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

const STOCK_MODAL_INITIAL_STATE = {
    open: false,
    loading: false,
    product: null,
    stocks: [],
    history: [],
    summary: null,
    error: null,
};

const selectionInitialState = {
    selectedIds: [],
    deselectedIds: [],
    isAllSelected: false,
};

function selectionReducer(state, action) {
    switch (action.type) {
        case 'RESET':
            return selectionInitialState;
        case 'TOGGLE_ROW': {
            const { id, checked } = action;
            if (state.isAllSelected) {
                const deselected = new Set(state.deselectedIds);
                if (checked) {
                    deselected.delete(id);
                } else {
                    deselected.add(id);
                }
                return { ...state, deselectedIds: Array.from(deselected) };
            }

            const selected = new Set(state.selectedIds);
            if (checked) {
                selected.add(id);
            } else {
                selected.delete(id);
            }

            return { ...state, selectedIds: Array.from(selected) };
        }
        case 'TOGGLE_PAGE': {
            const { ids = [], checked } = action;
            if (!ids.length) {
                return state;
            }

            if (state.isAllSelected) {
                const deselected = new Set(state.deselectedIds);
                ids.forEach((id) => {
                    if (checked) {
                        deselected.delete(id);
                    } else {
                        deselected.add(id);
                    }
                });
                return { ...state, deselectedIds: Array.from(deselected) };
            }

            const selected = new Set(state.selectedIds);
            ids.forEach((id) => {
                if (checked) {
                    selected.add(id);
                } else {
                    selected.delete(id);
                }
            });

            return { ...state, selectedIds: Array.from(selected) };
        }
        case 'SELECT_ALL':
            return { selectedIds: [], deselectedIds: [], isAllSelected: true };
        case 'CLEAR':
            return selectionInitialState;
        default:
            return state;
    }
}

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
    const [sortColumn, setSortColumn] = useState(filters.sort ?? null);
    const [sortDirection, setSortDirection] = useState(filters.direction ?? 'asc');
    const [selectionState, dispatchSelection] = useReducer(selectionReducer, selectionInitialState);
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
        setSortColumn(filters.sort ?? null);
        setSortDirection(filters.direction ?? 'asc');
    }, [filters.status, filters.stock, filters.price_min, filters.price_max, filters.sort, filters.direction]);

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
            sort: sortColumn || undefined,
            direction: sortColumn ? sortDirection : undefined,
        };

        delete base.view;

        const merged = { ...base, ...overrides };

        return Object.fromEntries(
            Object.entries(merged).filter(([_, value]) => value !== undefined && value !== null && value !== '')
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

    const isRowSelected = (id) =>
        selectionState.isAllSelected
            ? !selectionState.deselectedIds.includes(id)
            : selectionState.selectedIds.includes(id);

    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => isRowSelected(id));
    const somePageSelected = !allPageSelected && pageIds.some((id) => isRowSelected(id));
    const selectedCount = selectionState.isAllSelected
        ? Math.max(totalProducts - selectionState.deselectedIds.length, 0)
        : selectionState.selectedIds.length;
    const selectionEmpty = selectedCount === 0;

    useEffect(() => {
        if (pageCheckboxRef.current) {
            pageCheckboxRef.current.indeterminate = somePageSelected;
        }
    }, [somePageSelected]);

    useEffect(() => {
        dispatchSelection({ type: 'CLEAR' });
    }, [products.data]);

    const handleToggleRow = (productId, checked) => {
        dispatchSelection({ type: 'TOGGLE_ROW', id: productId, checked });
    };

    const handleTogglePage = (checked) => {
        dispatchSelection({ type: 'TOGGLE_PAGE', ids: pageIds, checked });
    };

    const handleSelectAllAcrossPages = () => {
        dispatchSelection({ type: 'SELECT_ALL' });
    };

    const handleClearSelection = () => {
        dispatchSelection({ type: 'CLEAR' });
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
        setSortColumn(null);
        setSortDirection('asc');

        updateFilters({
            search: undefined,
            status: undefined,
            stock: undefined,
            price_min: undefined,
            price_max: undefined,
            catalog: undefined,
            category: undefined,
            sort: undefined,
            direction: undefined,
        });
    };

    const handlePerPageChange = (perPage) => {
        updateFilters({ per_page: perPage });
    };

    const handleCatalogChange = (catalog) => {
        updateFilters({ catalog, category: null });
    };

    const handleCategoryChange = (category) => {
        updateFilters({ category });
    };

    const handleSort = (column) => {
        let nextSort = column;
        let nextDirection = 'asc';

        if (sortColumn === column) {
            if (sortDirection === 'asc') {
                nextDirection = 'desc';
            } else {
                nextSort = null;
                nextDirection = 'asc';
            }
        }

        setSortColumn(nextSort);
        setSortDirection(nextDirection);

        updateFilters({
            sort: nextSort || undefined,
            direction: nextSort ? nextDirection : undefined,
        });
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

    const hasActiveFilters =
        Boolean(
            filters.search ||
                filters.status ||
                filters.stock ||
                filters.price_min ||
                filters.price_max ||
                filters.catalog ||
                filters.category ||
                filters.sort
        ) ||
        Boolean(search || statusFilter || stockFilter || priceMin || priceMax || sortColumn);

    return (
        <>
            <Head title="Produkty" />
            <div className="flex flex-col gap-6">
                {flash?.status && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.status}
                    </div>
                )}

                <ProductsFilters
                    search={search}
                    onSearchChange={setSearch}
                    statusFilter={statusFilter}
                    onStatusChange={handleStatusChange}
                    statusOptions={options.statuses}
                    stockFilter={stockFilter}
                    onStockChange={handleStockChange}
                    stockOptions={stockFilterOptions}
                    priceMin={priceMin}
                    priceMax={priceMax}
                    onPriceMinChange={setPriceMin}
                    onPriceMaxChange={setPriceMax}
                    onApplyPrice={handleApplyPrice}
                    onResetFilters={handleResetFilters}
                    hasActiveFilters={hasActiveFilters}
                    catalog={filters.catalog}
                    onCatalogChange={handleCatalogChange}
                    catalogOptions={options.catalogs}
                    category={filters.category}
                    onCategoryChange={handleCategoryChange}
                    categoryOptions={filterCategories}
                    perPage={filters.per_page ?? 15}
                    perPageOptions={perPageOptions}
                    onPerPageChange={handlePerPageChange}
                    actions={
                        <>
                            <ColumnVisibilityDialog
                                open={isColumnDialogOpen}
                                onOpenChange={setIsColumnDialogOpen}
                                definitions={columnDefinitions}
                                visibility={columnVisibility}
                                onToggle={(key, value) =>
                                    setColumnVisibility((current) => ({
                                        ...current,
                                        [key]: value,
                                    }))
                                }
                                onReset={() => setColumnVisibility(defaultColumnVisibility)}
                                trigger={
                                    <Button type="button" variant="outline" size="icon">
                                        <Settings2 className="h-4 w-4" />
                                        <span className="sr-only">Dostosuj kolumny</span>
                                    </Button>
                                }
                            />
                            {can.create && (
                                <Button type="button" onClick={openCreateModal}>
                                    Dodaj produkt
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <SelectionToolbar
                        selectedCount={selectedCount}
                        totalProducts={totalProducts}
                        selectionEmpty={selectionEmpty}
                        onSelectPage={() => handleTogglePage(true)}
                        onDeselectPage={() => handleTogglePage(false)}
                        onSelectAllAcrossPages={handleSelectAllAcrossPages}
                        onClearSelection={handleClearSelection}
                        onBulkStatus={() => {}}
                        onBulkCategory={() => {}}
                    />

                    {!selectionState.isAllSelected && allPageSelected && totalProducts > pageIds.length ? (
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

                    {selectionState.isAllSelected && selectionState.deselectedIds.length > 0 ? (
                        <div className="mb-4 rounded-lg border border-dashed border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-700">
                            Zaznaczono wszystkie produkty oprócz {selectionState.deselectedIds.length}. Odznacz konkretne pozycje,
                            aby je wyłączyć z działań zbiorczych.
                        </div>
                    ) : null}

                    <ProductsTable
                        products={products.data}
                        columnVisibility={columnVisibility}
                        onSort={handleSort}
                        sortColumn={sortColumn}
                        sortDirection={sortDirection}
                        allPageSelected={allPageSelected}
                        somePageSelected={somePageSelected}
                        pageCheckboxRef={pageCheckboxRef}
                        onTogglePage={handleTogglePage}
                        isRowSelected={isRowSelected}
                        onToggleRow={handleToggleRow}
                        formatQuantity={formatQuantity}
                        formatCurrency={formatCurrency}
                        onOpenStockHistory={openStockHistory}
                        onEdit={openEditModal}
                        onDelete={handleDeleteProduct}
                    />

                    <nav className="mt-6 flex flex-wrap gap-2 text-sm">
                        {products.meta?.links?.map((link, index) => (
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
                    </nav>
                </div>

                <StockHistoryDialog
                    open={stockModal.open}
                    loading={stockModal.loading}
                    product={stockModal.product}
                    summary={stockModal.summary}
                    stocks={stockModal.stocks}
                    history={stockModal.history}
                    error={stockModal.error}
                    onClose={closeStockHistory}
                    onRefresh={openStockHistory}
                    formatQuantity={formatQuantity}
                />

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
