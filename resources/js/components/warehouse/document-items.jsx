import React from 'react';
import { Trash2, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import WarehouseStockDisplay from './stock-display';
import ProductSelect from './product-select';

const emptyItem = {
    product_id: '',
    quantity: 1,
    unit_price: '',
    vat_rate: '',
};

export default function DocumentItems({ items, onChange, products, warehouseId = null }) {
    const rows = items.length ? items : [emptyItem];

    const updateItem = (index, field, value) => {
        const next = items.map((item, i) => (i === index ? { ...item, [field]: value } : item));
        onChange(next);
    };

    const addItem = () => {
        onChange([...items, emptyItem]);
    };

    const removeItem = (index) => {
        if (items.length === 1) {
            onChange([emptyItem]);
            return;
        }

        onChange(items.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-4">
            {rows.map((item, index) => (
                <div
                    key={index}
                    className="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-5"
                >
                    <div className="flex flex-col text-sm text-gray-600 md:col-span-2">
                        <label className="mb-1">
                            <span className="font-medium text-gray-700">Produkt</span>
                        </label>
                        <ProductSelect
                            products={products}
                            value={item.product_id ?? ''}
                            onChange={(productId) => updateItem(index, 'product_id', productId)}
                            placeholder="Wyszukaj po nazwie, SKU lub EAN..."
                        />
                        
                        {item.product_id && (
                            <div className="mt-2">
                                {(() => {
                                    const selectedProduct = products.find(p => p.id == item.product_id);
                                    if (selectedProduct?.warehouse_stocks) {
                                        return (
                                            <WarehouseStockDisplay 
                                                stocks={selectedProduct.warehouse_stocks}
                                                warehouseId={warehouseId}
                                                compact={true}
                                            />
                                        );
                                    }
                                    return <div className="text-xs text-gray-400">Brak danych o stanie</div>;
                                })()}
                            </div>
                        )}
                    </div>

                    <label className="flex flex-col text-sm text-gray-600">
                        <span className="mb-1 font-medium text-gray-700">Ilość</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={item.quantity ?? ''}
                            onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </label>

                    <label className="flex flex-col text-sm text-gray-600">
                        <span className="mb-1 font-medium text-gray-700">Cena netto</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={item.unit_price ?? ''}
                            onChange={(event) => updateItem(index, 'unit_price', event.target.value)}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </label>

                    <label className="flex flex-col text-sm text-gray-600">
                        <span className="mb-1 font-medium text-gray-700">VAT %</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            value={item.vat_rate ?? ''}
                            onChange={(event) => updateItem(index, 'vat_rate', event.target.value)}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </label>

                    <div className="flex items-end justify-end">
                        <Button variant="ghost" type="button" size="sm" onClick={() => removeItem(index)}>
                            Usuń
                        </Button>
                    </div>
                </div>
            ))}

            <Button type="button" variant="outline" onClick={addItem}>
                Dodaj pozycję
            </Button>
        </div>
    );
}
