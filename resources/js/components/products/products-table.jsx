import { ArrowUpDown, ChevronDown, ChevronUp, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';

function renderSortIcon(activeColumn, sortColumn, sortDirection) {
    if (sortColumn !== activeColumn) {
        return <ArrowUpDown className="h-3.5 w-3.5 text-gray-400" />;
    }

    return sortDirection === 'asc' ? (
        <ChevronUp className="h-3.5 w-3.5 text-blue-600" />
    ) : (
        <ChevronDown className="h-3.5 w-3.5 text-blue-600" />
    );
}

export default function ProductsTable({
    products,
    columnVisibility,
    onSort,
    sortColumn,
    sortDirection,
    allPageSelected,
    somePageSelected,
    pageCheckboxRef,
    onTogglePage,
    isRowSelected,
    onToggleRow,
    formatQuantity,
    formatCurrency,
    onOpenStockHistory,
    onEdit,
    onDelete,
}) {
    const isColumnVisible = (key) => columnVisibility[key] !== false;

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th className="px-4 py-3">
                            <input
                                ref={pageCheckboxRef}
                                type="checkbox"
                                checked={allPageSelected}
                                onChange={(event) => onTogglePage(event.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                aria-checked={somePageSelected ? 'mixed' : allPageSelected}
                            />
                        </th>
                        {isColumnVisible('id') && (
                            <th className="px-4 py-3">
                                <button
                                    type="button"
                                    onClick={() => onSort('id')}
                                    className="flex items-center gap-1 text-gray-600"
                                >
                                    <span>ID</span>
                                    {renderSortIcon('id', sortColumn, sortDirection)}
                                </button>
                            </th>
                        )}
                        <th className="px-4 py-3">
                            <button
                                type="button"
                                onClick={() => onSort('name')}
                                className="flex items-center gap-1 text-gray-600"
                            >
                                <span>Produkt</span>
                                {renderSortIcon('name', sortColumn, sortDirection)}
                            </button>
                        </th>
                        {isColumnVisible('sku') && (
                            <th className="px-4 py-3">
                                <button
                                    type="button"
                                    onClick={() => onSort('sku')}
                                    className="flex items-center gap-1 text-gray-600"
                                >
                                    <span>SKU</span>
                                    {renderSortIcon('sku', sortColumn, sortDirection)}
                                </button>
                            </th>
                        )}
                        {isColumnVisible('catalog') && <th className="px-4 py-3">Katalog</th>}
                        {isColumnVisible('category') && <th className="px-4 py-3">Kategoria</th>}
                        {isColumnVisible('stock') && (
                            <th className="px-4 py-3">
                                <button
                                    type="button"
                                    onClick={() => onSort('quantity')}
                                    className="flex items-center gap-1 text-gray-600"
                                >
                                    <span>Stan (razem)</span>
                                    {renderSortIcon('quantity', sortColumn, sortDirection)}
                                </button>
                            </th>
                        )}
                        {isColumnVisible('warehouses') && <th className="px-4 py-3">Magazyny</th>}
                        {isColumnVisible('price') && (
                            <th className="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    onClick={() => onSort('price')}
                                    className="ml-auto flex items-center gap-1 text-gray-600"
                                >
                                    <span>Cena netto</span>
                                    {renderSortIcon('price', sortColumn, sortDirection)}
                                </button>
                            </th>
                        )}
                        {isColumnVisible('status') && <th className="px-4 py-3">Status</th>}
                        {isColumnVisible('actions') && <th className="px-4 py-3 text-right">Akcje</th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {products.map((product) => (
                        <tr key={product.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={isRowSelected(product.id)}
                                    onChange={(event) => onToggleRow(product.id, event.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                            </td>
                            {isColumnVisible('id') && <td className="px-4 py-3 text-gray-600">{product.id}</td>}
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
                            {isColumnVisible('sku') && (
                                <td className="px-4 py-3 text-gray-600">{product.sku ?? '—'}</td>
                            )}
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
                                        onClick={() => onOpenStockHistory(product)}
                                    >
                                        Historia
                                    </Button>
                                </td>
                            )}
                            {isColumnVisible('price') && (
                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(product.sale_price_net)}</td>
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
                                            onClick={() => onEdit(product)}
                                            title="Edytuj produkt"
                                        >
                                            <Pencil className="h-4 w-4" />
                                            <span className="sr-only">Edytuj</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => onDelete(product)}
                                            title="Usuń produkt"
                                        >
                                            <Trash2 className="h-4 w-4" />
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
    );
}
