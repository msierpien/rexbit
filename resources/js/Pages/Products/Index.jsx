import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import CreateProductModal from '@/components/CreateProductModal.jsx';
import EditProductModal from '@/components/EditProductModal.jsx';

const viewModes = [
    { value: 'table', label: 'Tabela' },
    { value: 'grid', label: 'Kafelki' },
];

const perPageOptions = [10, 15, 30, 50];

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

function Pagination({ meta }) {
    if (!meta?.links) {
        return null;
    }

    return (
        <nav className="mt-6 flex flex-wrap gap-2 text-sm">
            {meta.links.map((link, index) => (
                <button
                    key={index}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })}
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
}

export default function ProductsIndex() {
    const { props } = usePage();
    const { products, filters, options, can, flash, errors } = props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [viewMode, setViewMode] = useState(filters.view ?? 'table');
    const [selected, setSelected] = useState([]);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);

    const filterCategories = useMemo(() => {
        if (!filters.catalog) {
            return options.categories;
        }

        return options.categories.filter((category) => category.catalog_id === Number(filters.catalog));
    }, [filters.catalog, options.categories]);

    useEffect(() => {
        setSelected([]);
    }, [products.data]);

    useEffect(() => {
        const handler = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                updateFilters({ search });
            }
        }, 350);

        return () => clearTimeout(handler);
    }, [search]);

    const updateFilters = (next = {}) => {
        router.get(
            '/products',
            {
                ...filters,
                search,
                ...next,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const toggleSelected = (productId) => {
        setSelected((previous) =>
            previous.includes(productId)
                ? previous.filter((id) => id !== productId)
                : [...previous, productId],
        );
    };

    const toggleSelectAll = () => {
        const ids = products.data.map((product) => product.id);
        const allSelected = selected.length === ids.length;
        setSelected(allSelected ? [] : ids);
    };

    const massActionDisabled = selected.length === 0;

    const openCreateModal = () => {
        setIsCreateOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateOpen(false);
    };

    const openEditModal = (product) => {
        setEditingProduct(product);
        setIsEditOpen(true);
    };

    const closeEditModal = () => {
        setIsEditOpen(false);
        setEditingProduct(null);
    };

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
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div className="flex flex-1 flex-col gap-4 md:flex-row">
                            <label className="flex flex-col gap-1 text-sm text-gray-700 md:max-w-xs">
                                <span>Wyszukaj</span>
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Nazwa, SKU..."
                                    className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </label>
                            <FilterSelect
                                label="Katalog"
                                value={filters.catalog}
                                onChange={(catalog) => updateFilters({ catalog, category: null })}
                                options={options.catalogs}
                                placeholder="Wszystkie"
                            />
                            <FilterSelect
                                label="Kategoria"
                                value={filters.category}
                                onChange={(category) => updateFilters({ category })}
                                options={filterCategories}
                                placeholder={filterCategories.length ? 'Wszystkie' : 'Brak kategorii'}
                            />
                            <FilterSelect
                                label="Status"
                                value={filters.status}
                                onChange={(status) => updateFilters({ status })}
                                options={options.statuses}
                                placeholder="Wszystkie"
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <label className="flex items-center gap-2 text-sm text-gray-600">
                                <span>Na stronie</span>
                                <select
                                    value={filters.per_page ?? 15}
                                    onChange={(event) => updateFilters({ per_page: Number(event.target.value) })}
                                    className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                                >
                                    {perPageOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 p-1 text-sm">
                                {viewModes.map((mode) => (
                                    <button
                                        key={mode.value}
                                        type="button"
                                        className={`rounded px-3 py-1 font-medium ${
                                            viewMode === mode.value ? 'bg-white shadow text-blue-600' : 'text-gray-600'
                                        }`}
                                        onClick={() => {
                                            setViewMode(mode.value);
                                            updateFilters({ view: mode.value });
                                        }}
                                    >
                                        {mode.label}
                                    </button>
                                ))}
                            </div>
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
                            Wybrano <strong>{selected.length}</strong> z {products.meta?.total ?? products.data.length} produktów
                        </span>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                className="rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 disabled:text-gray-400"
                                disabled={massActionDisabled}
                                onClick={() => {}}
                            >
                                Zmień status
                            </button>
                            <button
                                type="button"
                                className="rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 disabled:text-gray-400"
                                disabled={massActionDisabled}
                                onClick={() => {}}
                            >
                                Przypisz kategorię
                            </button>
                        </div>
                    </div>

                    {viewMode === 'table' ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                checked={selected.length > 0 && selected.length === products.data.length}
                                                onChange={toggleSelectAll}
                                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                        </th>
                                        <th className="px-4 py-3">Produkt</th>
                                        <th className="px-4 py-3">Katalog</th>
                                        <th className="px-4 py-3">Kategoria</th>
                                        <th className="px-4 py-3">Cena netto</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {products.data.map((product) => (
                                        <tr key={product.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(product.id)}
                                                    onChange={() => toggleSelected(product.id)}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    {product.images && product.images.length > 0 && (
                                                        <img 
                                                            src={product.images[0]} 
                                                            alt={product.name}
                                                            className="h-10 w-10 rounded object-cover flex-shrink-0"
                                                            onError={(e) => { e.target.style.display = 'none'; }}
                                                        />
                                                    )}
                                                    <div>
                                                        <div className="font-semibold text-gray-900">{product.name}</div>
                                                        <div className="text-xs text-gray-500 space-x-3">
                                                            {product.sku && <span>SKU: {product.sku}</span>}
                                                            {product.ean && <span>EAN: {product.ean}</span>}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-600">{product.catalog?.name ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-600">{product.category?.name ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-600">
                                                {product.sale_price_net ? `${Number(product.sale_price_net).toFixed(2)} zł` : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-600">
                                                    {product.status_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" onClick={() => openEditModal(product)}>
                                                        Edytuj
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => {
                                                            if (confirm('Czy na pewno chcesz usunąć ten produkt?')) {
                                                                router.delete(`/products/${product.id}`, {
                                                                    preserveScroll: true,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        Usuń
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {products.data.map((product) => (
                                <div key={product.id} className="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    {product.images && product.images.length > 0 && (
                                        <div className="mb-3">
                                            <img 
                                                src={product.images[0]} 
                                                alt={product.name}
                                                className="h-32 w-full rounded-lg object-cover"
                                                onError={(e) => { e.target.style.display = 'none'; }}
                                            />
                                        </div>
                                    )}
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h3 className="text-base font-semibold text-gray-900">{product.name}</h3>
                                            <div className="text-xs text-gray-500 space-y-1">
                                                {product.sku && <div>SKU: {product.sku}</div>}
                                                {product.ean && <div>EAN: {product.ean}</div>}
                                            </div>
                                        </div>
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(product.id)}
                                            onChange={() => toggleSelected(product.id)}
                                            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </div>
                                    <dl className="mt-3 space-y-1 text-sm text-gray-600">
                                        <div>
                                            <dt className="font-medium">Katalog</dt>
                                            <dd>{product.catalog?.name ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">Kategoria</dt>
                                            <dd>{product.category?.name ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">Cena netto</dt>
                                            <dd>
                                                {product.sale_price_net
                                                    ? `${Number(product.sale_price_net).toFixed(2)} zł`
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">Status</dt>
                                            <dd>{product.status_label}</dd>
                                        </div>
                                    </dl>
                                    <div className="mt-4 flex gap-2">
                                        <Button variant="outline" className="flex-1" onClick={() => openEditModal(product)}>
                                            Edytuj
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={() => {
                                                if (confirm('Czy na pewno chcesz usunąć ten produkt?')) {
                                                    router.delete(`/products/${product.id}`, {
                                                        preserveScroll: true,
                                                    });
                                                }
                                            }}
                                        >
                                            Usuń
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <Pagination meta={products.meta} />
                </div>



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
