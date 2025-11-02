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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';

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
    const { products, filters, options, can, flash, integrationOptions = [] } = usePage().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [stockFilter, setStockFilter] = useState(filters.stock ?? '');
    const [priceMin, setPriceMin] = useState(filters.price_min ?? '');
    const [priceMax, setPriceMax] = useState(filters.price_max ?? '');
    const [catalogFilter, setCatalogFilter] = useState(filters.catalog ?? null);
    const [categoryFilter, setCategoryFilter] = useState(filters.category ?? null);
    const [sortColumn, setSortColumn] = useState(filters.sort ?? null);
    const [sortDirection, setSortDirection] = useState(filters.direction ?? 'asc');
    const [selectionState, dispatchSelection] = useReducer(selectionReducer, selectionInitialState);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);
    const [stockModal, setStockModal] = useState(() => ({ ...STOCK_MODAL_INITIAL_STATE }));
    const [columnVisibility, setColumnVisibility] = useState(getInitialColumnVisibility);
    const [isColumnDialogOpen, setIsColumnDialogOpen] = useState(false);
    const [activeIntegrationId, setActiveIntegrationId] = useState(null);
    const [integrationState, setIntegrationState] = useState({
        loading: false,
        data: [],
        meta: {
            current_page: 1,
            per_page: filters.per_page ?? 15,
            total: null,
            total_pages: null,
            has_more: false,
        },
        error: null,
    });
    const integrationRequestRef = useRef(0);
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [linkIntegrationId, setLinkIntegrationId] = useState(
        integrationOptions.length ? String(integrationOptions[0].id) : ''
    );
    const [linking, setLinking] = useState(false);
    const [linkResult, setLinkResult] = useState(null);
    const [linkError, setLinkError] = useState(null);

    const sourceOptions = useMemo(() => {
        const integrations = integrationOptions.map((integration) => ({
            value: String(integration.id),
            label: integration.name,
        }));

        return [
            { value: 'local', label: 'Lokalna baza produktów' },
            ...integrations,
        ];
    }, [integrationOptions]);

    const filterCategories = useMemo(() => {
        if (!catalogFilter) {
            return options.categories;
        }

        return options.categories.filter((category) => category.catalog_id === Number(catalogFilter));
    }, [catalogFilter, options.categories]);

    const isIntegrationSource = activeIntegrationId !== null;
    const sourceValue = isIntegrationSource ? String(activeIntegrationId) : 'local';
    const currentProducts = isIntegrationSource ? integrationState.data : products.data;
    const currentMeta = isIntegrationSource ? integrationState.meta : products.meta;
    const integrationCurrentPage = integrationState.meta?.current_page ?? 1;
    const integrationTotalPages = integrationState.meta?.total_pages ?? null;
    const integrationHasMore = integrationState.meta?.has_more ?? false;
    const integrationRowOffset = isIntegrationSource
        ? (integrationCurrentPage - 1) * (integrationState.meta?.per_page ?? currentProducts.length)
        : 0;

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
        setCatalogFilter(filters.catalog ?? null);
        setCategoryFilter(filters.category ?? null);
    }, [filters.catalog, filters.category]);

    useEffect(() => {
        if (!integrationOptions.length) {
            setLinkIntegrationId('');
            return;
        }

        setLinkIntegrationId((current) =>
            current && integrationOptions.some((option) => String(option.id) === current)
                ? current
                : String(integrationOptions[0].id)
        );
    }, [integrationOptions]);

    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(columnVisibility));
        }
    }, [columnVisibility]);

    const baseFilterState = () => ({
        search,
        status: statusFilter || undefined,
        stock: stockFilter || undefined,
        price_min: priceMin || undefined,
        price_max: priceMax || undefined,
        catalog: catalogFilter || undefined,
        category: categoryFilter || undefined,
    });

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

        base.catalog = catalogFilter || undefined;
        base.category = categoryFilter || undefined;
        base.per_page = isIntegrationSource
            ? integrationState.meta?.per_page ?? filters.per_page ?? 15
            : filters.per_page ?? 15;

        delete base.view;

        const merged = { ...base, ...overrides };

        return Object.fromEntries(
            Object.entries(merged).filter(([_, value]) => value !== undefined && value !== null && value !== '')
        );
    };

    const buildLinkingFilters = () => {
        const base = baseFilterState();

        return Object.fromEntries(
            Object.entries(base).filter(([_, value]) => value !== undefined && value !== null && value !== '')
        );
    };

    const fetchIntegrationProducts = async (integrationId, overrides = {}) => {
        if (!integrationId) {
            return;
        }

        const params = buildFilters(overrides);
        if (!params.per_page) {
            params.per_page =
                overrides.per_page ??
                integrationState.meta?.per_page ??
                filters.per_page ??
                15;
        }
        if (!params.page) {
            params.page = overrides.page ?? integrationState.meta?.current_page ?? 1;
        }

        const queryString = new URLSearchParams(
            Object.entries(params).reduce((carry, [key, value]) => {
                if (value === undefined || value === null || value === '') {
                    return carry;
                }
                carry[key] = String(value);
                return carry;
            }, {}),
        ).toString();

        const requestId = ++integrationRequestRef.current;

        setIntegrationState((current) => ({
            ...current,
            loading: true,
            error: null,
        }));

        try {
            const response = await fetch(`/products/integrations/${integrationId}?${queryString}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Nie udało się pobrać danych (status ${response.status}).`);
            }

            const payload = await response.json();

            if (integrationRequestRef.current !== requestId) {
                return;
            }

            const meta = payload.meta ?? {};
            const data = payload.products ?? [];
            const perPage = Number(meta.per_page ?? params.per_page);
            const currentPage = Number(meta.current_page ?? params.page ?? 1);
            const total = meta.total !== undefined && meta.total !== null ? Number(meta.total) : null;

            const totalPages =
                total !== null && perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : null;

            const hasMore =
                totalPages !== null
                    ? currentPage < totalPages
                    : data.length === perPage;

            setIntegrationState({
                loading: false,
                data,
                meta: {
                    current_page: currentPage,
                    per_page: perPage,
                    total,
                    total_pages: totalPages,
                    has_more: hasMore,
                },
                error: null,
            });
        } catch (error) {
            if (integrationRequestRef.current !== requestId) {
                return;
            }

            setIntegrationState((current) => ({
                ...current,
                loading: false,
                error:
                    error instanceof Error
                        ? error.message
                        : 'Wystąpił nieoczekiwany błąd podczas pobierania danych z integracji.',
            }));
        }
    };

    const updateFilters = (overrides = {}) => {
        if (isIntegrationSource) {
            const nextOverrides = { ...overrides };
            if (!('page' in nextOverrides)) {
                nextOverrides.page = 1;
            }

            fetchIntegrationProducts(activeIntegrationId, nextOverrides);
            return;
        }

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

    const totalProducts = currentMeta?.total ?? currentProducts.length;
    const pageIds = useMemo(
        () => currentProducts.map((product) => product.id),
        [currentProducts]
    );
    const pageCheckboxRef = useRef(null);

    const isRowSelected = (id) =>
        selectionState.isAllSelected
            ? !selectionState.deselectedIds.includes(id)
            : selectionState.selectedIds.includes(id);

    const allPageSelected =
        !isIntegrationSource && pageIds.length > 0 && pageIds.every((id) => isRowSelected(id));
    const somePageSelected =
        !isIntegrationSource && !allPageSelected && pageIds.some((id) => isRowSelected(id));
    const selectedCount = isIntegrationSource
        ? 0
        : selectionState.isAllSelected
            ? Math.max(totalProducts - selectionState.deselectedIds.length, 0)
            : selectionState.selectedIds.length;
    const selectionEmpty = selectedCount === 0;
    const selectedProductIds = useMemo(() => {
        if (selectionState.isAllSelected) {
            return pageIds.filter((id) => !selectionState.deselectedIds.includes(id));
        }

        return selectionState.selectedIds;
    }, [selectionState.isAllSelected, selectionState.deselectedIds, selectionState.selectedIds, pageIds]);

    const displayedSelectionCount = selectionState.isAllSelected ? totalProducts : selectedProductIds.length;

    useEffect(() => {
        if (pageCheckboxRef.current) {
            pageCheckboxRef.current.indeterminate = somePageSelected;
        }
    }, [somePageSelected]);

    useEffect(() => {
        dispatchSelection({ type: 'CLEAR' });
    }, [activeIntegrationId, products.data, integrationState.data]);

    const sourceInitializedRef = useRef(false);

    useEffect(() => {
        if (!sourceInitializedRef.current) {
            sourceInitializedRef.current = true;
            return;
        }

        if (activeIntegrationId === null) {
            updateFilters();
        } else {
            fetchIntegrationProducts(activeIntegrationId, { page: 1 });
        }
    }, [activeIntegrationId]);

    useEffect(() => {
        if (!linkDialogOpen) {
            setLinkResult(null);
            setLinkError(null);
            setLinking(false);
        }
    }, [linkDialogOpen]);

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
        setCatalogFilter(null);
        setCategoryFilter(null);

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
        updateFilters({ per_page: perPage, page: 1 });
    };

    const handleCatalogChange = (catalog) => {
        setCatalogFilter(catalog);
        setCategoryFilter(null);
        updateFilters({ catalog, category: null });
    };

    const handleCategoryChange = (category) => {
        setCategoryFilter(category);
        updateFilters({ category });
    };

    const handleSourceChange = (value) => {
        if (!value || value === 'local') {
            setActiveIntegrationId(null);
            return;
        }

        const integrationId = Number(value);
        if (Number.isNaN(integrationId)) {
            return;
        }

        setActiveIntegrationId(integrationId);
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

    const handleLinkSubmit = async () => {
        if (!linkIntegrationId) {
            setLinkError('Wybierz integrację, z którą chcesz powiązać produkty.');
            return;
        }

        if (!selectionState.isAllSelected && !selectedProductIds.length) {
            setLinkError('Wybierz co najmniej jeden produkt lokalny.');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        setLinking(true);
        setLinkError(null);

        try {
            const payload = selectionState.isAllSelected
                ? {
                      select_all: true,
                      filters: buildLinkingFilters(),
                  }
                : {
                      product_ids: selectedProductIds,
                  };

            const response = await fetch(`/products/integrations/${linkIntegrationId}/links`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (response.status === 202) {
                const data = await response.json();
                setLinkResult({
                    status: 'queued',
                    queued_product_ids: data.queued_product_ids ?? [],
                    queued_count: data.queued_count ?? (selectionState.isAllSelected ? totalProducts : selectedProductIds.length),
                });
                setLinkError(null);
                return;
            }

            if (!response.ok) {
                const errorMessage = response.status === 422
                    ? 'Nie udało się powiązać produktów. Sprawdź dane i spróbuj ponownie.'
                    : `Błąd podczas próby powiązania produktów (status ${response.status}).`;
                throw new Error(errorMessage);
            }

            const data = await response.json();
            setLinkResult(data);
            router.reload({ only: ['products'] });
        } catch (error) {
            setLinkError(error instanceof Error ? error.message : 'Wystąpił nieoczekiwany błąd.');
        } finally {
            setLinking(false);
        }
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
                    catalog={catalogFilter}
                    onCatalogChange={handleCatalogChange}
                    catalogOptions={options.catalogs}
                    category={categoryFilter}
                    onCategoryChange={handleCategoryChange}
                    categoryOptions={filterCategories}
                    perPage={isIntegrationSource ? integrationState.meta?.per_page ?? filters.per_page ?? 15 : filters.per_page ?? 15}
                    perPageOptions={perPageOptions}
                    onPerPageChange={handlePerPageChange}
                    sourceOptions={sourceOptions}
                    sourceValue={sourceValue}
                    onSourceChange={handleSourceChange}
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
                            {!isIntegrationSource && can.create && (
                                <Button type="button" onClick={openCreateModal}>
                                    Dodaj produkt
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    {!isIntegrationSource ? (
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
                            additionalActions={
                                integrationOptions.length ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => setLinkDialogOpen(true)}
                                        disabled={selectionEmpty}
                                    >
                                        Powiąż z integracją
                                    </Button>
                                ) : null
                            }
                        />
                    ) : (
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                            <span>
                                Źródło: 
                                <span className="font-medium text-foreground">
                                    {sourceOptions.find((option) => option.value === sourceValue)?.label ?? 'Integracja'}
                                </span>
                            </span>
                            {integrationState.loading && <span>Ładowanie danych z integracji...</span>}
                        </div>
                    )}

                    {!isIntegrationSource && allPageSelected && totalProducts > pageIds.length ? (
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

                    {!isIntegrationSource && selectionState.isAllSelected && selectionState.deselectedIds.length > 0 ? (
                        <div className="mb-4 rounded-lg border border-dashed border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-700">
                            Zaznaczono wszystkie produkty oprócz {selectionState.deselectedIds.length}. Odznacz konkretne pozycje,
                            aby je wyłączyć z działań zbiorczych.
                        </div>
                    ) : null}

                    {isIntegrationSource && integrationState.error && (
                        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                            {integrationState.error}
                        </div>
                    )}

                    <ProductsTable
                        products={currentProducts}
                        columnVisibility={columnVisibility}
                        onSort={handleSort}
                        sortColumn={sortColumn}
                        sortDirection={sortDirection}
                        allPageSelected={allPageSelected}
                        somePageSelected={somePageSelected}
                        pageCheckboxRef={isIntegrationSource ? null : pageCheckboxRef}
                        onTogglePage={handleTogglePage}
                        isRowSelected={isRowSelected}
                        onToggleRow={handleToggleRow}
                        formatQuantity={formatQuantity}
                        formatCurrency={formatCurrency}
                        onOpenStockHistory={isIntegrationSource ? null : openStockHistory}
                        onEdit={isIntegrationSource ? null : openEditModal}
                        onDelete={isIntegrationSource ? null : handleDeleteProduct}
                        enableSelection={!isIntegrationSource}
                        rowOffset={isIntegrationSource ? integrationRowOffset : 0}
                    />

                    {isIntegrationSource ? (
                        <div className="mt-6 flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={integrationState.loading || integrationCurrentPage <= 1}
                                onClick={() =>
                                    fetchIntegrationProducts(activeIntegrationId, {
                                        page: Math.max(1, integrationCurrentPage - 1),
                                    })
                                }
                            >
                                Poprzednia
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                Strona {integrationCurrentPage}
                                {integrationTotalPages ? ` z ${integrationTotalPages}` : ''}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={integrationState.loading || !integrationHasMore}
                                onClick={() =>
                                    fetchIntegrationProducts(activeIntegrationId, {
                                        page: integrationCurrentPage + 1,
                                    })
                                }
                            >
                                Następna
                            </Button>
                        </div>
                    ) : (
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
                    )}
                </div>

                <Dialog open={linkDialogOpen} onOpenChange={setLinkDialogOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Powiąż produkty z integracją</DialogTitle>
                            <DialogDescription>
                                System spróbuje dopasować produkty po SKU lub EAN. W razie potrzeby możesz później edytować
                                powiązania ręcznie.
                            </DialogDescription>
                        </DialogHeader>

                        {integrationOptions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Brak aktywnych integracji z włączoną obsługą listy produktów.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Zostaną przetworzone <strong>{displayedSelectionCount}</strong> produkty lokalne.
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    <label htmlFor="link-integration" className="text-sm font-medium text-foreground">
                                        Integracja
                                    </label>
                                    <Select
                                        value={linkIntegrationId}
                                        onValueChange={setLinkIntegrationId}
                                        disabled={linking}
                                    >
                                        <SelectTrigger id="link-integration">
                                            <SelectValue placeholder="Wybierz integrację" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {integrationOptions.map((integration) => (
                                                <SelectItem key={integration.id} value={String(integration.id)}>
                                                    {integration.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {linkError && (
                                    <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {linkError}
                                    </div>
                                )}
                                {linkResult && (
                            <div className="rounded-lg border border-muted px-3 py-2 text-sm">
                                {linkResult.status === 'queued' ? (
                                    <div className="space-y-1">
                                        <p className="font-semibold text-foreground">Zadanie zostało ustawione w kolejce</p>
                                        <p className="text-muted-foreground">
                                            Produkty ({linkResult.queued_count ?? linkResult.queued_product_ids?.length ?? displayedSelectionCount}) zostaną powiązane w tle. Możesz kontynuować pracę.
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <p className="font-semibold text-foreground">Podsumowanie</p>
                                        <ul className="mt-2 space-y-1 text-muted-foreground">
                                            <li>
                                                Powiązane nowe: <strong>{linkResult.created?.length ?? 0}</strong>
                                            </li>
                                            <li>
                                                Zaktualizowane powiązania: <strong>{linkResult.updated?.length ?? 0}</strong>
                                            </li>
                                            <li>
                                                Brak dopasowania: <strong>{linkResult.unmatched?.length ?? 0}</strong>
                                            </li>
                                            {linkResult.errors && Object.keys(linkResult.errors).length > 0 && (
                                                <li className="text-red-600">
                                                    Błędy: {Object.keys(linkResult.errors).length}
                                                </li>
                                            )}
                                        </ul>
                                    </>
                                )}
                            </div>
                        )}
                            </div>
                        )}
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setLinkDialogOpen(false)}>
                                Zamknij
                            </Button>
                            {integrationOptions.length > 0 && (
                                <Button onClick={handleLinkSubmit} disabled={linking || !selectedProductIds.length}>
                                    {linking ? 'Łączenie…' : 'Powiąż produkty'}
                                </Button>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

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
