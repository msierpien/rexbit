import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';

const viewModes = [
    { value: 'table', label: 'Tabela' },
    { value: 'grid', label: 'Kafelki' },
];

const perPageOptions = [10, 15, 30, 50];

const defaultProductForm = {
    name: '',
    sku: '',
    catalog_id: '',
    category_id: '',
    manufacturer_id: '',
    sale_price_net: '',
    sale_vat_rate: '',
    status: 'active',
    description: '',
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

    const {
        data: formData,
        setData,
        post,
        processing,
        reset,
    } = useForm({ ...defaultProductForm });

    const filterCategories = useMemo(() => {
        if (!filters.catalog) {
            return options.categories;
        }

        return options.categories.filter((category) => category.catalog_id === Number(filters.catalog));
    }, [filters.catalog, options.categories]);

    const formCategories = useMemo(() => {
        if (!formData.catalog_id) {
            return options.categories;
        }

        return options.categories.filter((category) => category.catalog_id === Number(formData.catalog_id));
    }, [formData.catalog_id, options.categories]);

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
        reset();
        setIsCreateOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateOpen(false);
    };

    const submitCreate = (event) => {
        event.preventDefault();

        post('/products', {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
                reset();
            },
        });
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
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/products/${product.id}/edit`}>Edytuj</Link>
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
                                        <Button variant="outline" asChild className="flex-1">
                                            <Link href={`/products/${product.id}/edit`}>Edytuj</Link>
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

                {isCreateOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-900">Dodaj produkt</h2>
                                <button
                                    type="button"
                                    className="rounded-full p-2 text-gray-500 hover:bg-gray-100"
                                    onClick={closeCreateModal}
                                    aria-label="Zamknij"
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.5">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <form className="mt-6 space-y-4" onSubmit={submitCreate}>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Nazwa</span>
                                        <input
                                            type="text"
                                            value={formData.name}
                                            onChange={(event) => setData('name', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        />
                                        {errors?.name && <span className="text-xs text-red-600">{errors.name}</span>}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>SKU</span>
                                        <input
                                            type="text"
                                            value={formData.sku}
                                            onChange={(event) => setData('sku', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors?.sku && <span className="text-xs text-red-600">{errors.sku}</span>}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Katalog</span>
                                        <select
                                            value={formData.catalog_id ?? ''}
                                            onChange={(event) => setData('catalog_id', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            <option value="">Wybierz katalog</option>
                                            {options.catalogs.map((catalog) => (
                                                <option key={catalog.id} value={catalog.id}>
                                                    {catalog.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors?.catalog_id && <span className="text-xs text-red-600">{errors.catalog_id}</span>}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Kategoria</span>
                                        <select
                                            value={formData.category_id ?? ''}
                                            onChange={(event) => setData('category_id', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            <option value="">Wybierz kategorię</option>
                                            {formCategories.map((category) => (
                                                <option key={category.id} value={category.id}>
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors?.category_id && <span className="text-xs text-red-600">{errors.category_id}</span>}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Producent</span>
                                        <select
                                            value={formData.manufacturer_id ?? ''}
                                            onChange={(event) => setData('manufacturer_id', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            <option value="">Wybierz producenta</option>
                                            {options.manufacturers?.map((manufacturer) => (
                                                <option key={manufacturer.id} value={manufacturer.id}>
                                                    {manufacturer.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors?.manufacturer_id && (
                                            <span className="text-xs text-red-600">{errors.manufacturer_id}</span>
                                        )}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Cena netto</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={formData.sale_price_net ?? ''}
                                            onChange={(event) => setData('sale_price_net', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors?.sale_price_net && <span className="text-xs text-red-600">{errors.sale_price_net}</span>}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>VAT (%)</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={formData.sale_vat_rate ?? ''}
                                            onChange={(event) => setData('sale_vat_rate', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors?.sale_vat_rate && (
                                            <span className="text-xs text-red-600">{errors.sale_vat_rate}</span>
                                        )}
                                    </label>

                                    <label className="flex flex-col gap-1 text-sm text-gray-700">
                                        <span>Status</span>
                                        <select
                                            value={formData.status}
                                            onChange={(event) => setData('status', event.target.value)}
                                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            {options.statuses.map((status) => (
                                                <option key={status.value} value={status.value}>
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </div>

                                <label className="flex flex-col gap-1 text-sm text-gray-700">
                                    <span>Opis</span>
                                    <textarea
                                        value={formData.description ?? ''}
                                        onChange={(event) => setData('description', event.target.value)}
                                        className="h-24 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Opcjonalny opis produktu"
                                    />
                                </label>

                                <div className="flex items-center justify-end gap-3">
                                    <Button type="button" variant="ghost" onClick={closeCreateModal}>
                                        Anuluj
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Zapisz produkt
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ProductsIndex.layout = (page) => <DashboardLayout title="Produkty">{page}</DashboardLayout>;
