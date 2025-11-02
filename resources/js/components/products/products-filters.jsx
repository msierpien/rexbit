import { Button } from '@/components/ui/button.jsx';

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

export default function ProductsFilters({
    search,
    onSearchChange,
    statusFilter,
    onStatusChange,
    statusOptions,
    stockFilter,
    onStockChange,
    stockOptions,
    priceMin,
    priceMax,
    onPriceMinChange,
    onPriceMaxChange,
    onApplyPrice,
    onResetFilters,
    hasActiveFilters,
    catalog,
    onCatalogChange,
    catalogOptions,
    category,
    onCategoryChange,
    categoryOptions,
    perPage,
    perPageOptions,
    onPerPageChange,
    actions,
}) {
    return (
        <div className="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <div className="grid gap-4 lg:grid-cols-4">
                <label className="flex flex-col gap-1 text-sm text-gray-700">
                    <span>Nazwa lub SKU</span>
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder="Wyszukaj produkt..."
                        className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </label>
                <FilterSelect
                    label="Status"
                    value={statusFilter}
                    onChange={onStatusChange}
                    options={statusOptions}
                    placeholder="Wszystkie"
                />
                <FilterSelect
                    label="Stan magazynowy"
                    value={stockFilter}
                    onChange={onStockChange}
                    options={stockOptions}
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
                            onChange={(event) => onPriceMinChange(event.target.value)}
                            placeholder="Od"
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span className="text-gray-400">—</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={priceMax}
                            onChange={(event) => onPriceMaxChange(event.target.value)}
                            placeholder="Do"
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                    <div className="flex gap-2 pt-1">
                        <Button type="button" variant="outline" size="sm" className="gap-2" onClick={onApplyPrice}>
                            Zastosuj
                        </Button>
                        {hasActiveFilters && (
                            <Button type="button" variant="ghost" size="sm" onClick={onResetFilters}>
                                Wyczyść
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <FilterSelect
                    label="Katalog"
                    value={catalog}
                    onChange={onCatalogChange}
                    options={catalogOptions}
                    placeholder="Wszystkie katalogi"
                />
                <FilterSelect
                    label="Kategoria"
                    value={category}
                    onChange={onCategoryChange}
                    options={categoryOptions}
                    placeholder={categoryOptions.length ? 'Wszystkie kategorie' : 'Brak kategorii'}
                />
                <label className="flex flex-col gap-1 text-sm text-gray-700">
                    <span>Na stronie</span>
                    <select
                        value={perPage}
                        onChange={(event) => onPerPageChange(Number(event.target.value))}
                        className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        {perPageOptions.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                    </select>
                </label>
                <div className="flex items-end justify-end gap-2">{actions}</div>
            </div>
        </div>
    );
}
