// OrderItemsTable.jsx - tabela pozycji zamówienia z opcją edycji i pakowania

import React, { useState } from 'react';
import { Edit2, Trash2, Package, Check } from 'lucide-react';

export default function OrderItemsTable({
    items = [],
    currency = 'PLN',
    onItemUpdate,
    onPack,
    packingEnabled = false,
    readOnly = false,
}) {
    const [editingItem, setEditingItem] = useState(null);
    const [packQty, setPackQty] = useState({});

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: currency,
        }).format(amount ?? 0);
    };

    const calculateItemTotal = (item) => {
        const subtotal = (item.quantity ?? 0) * (item.price_gross ?? 0);
        const discount = item.discount_total || 0;
        return subtotal - discount;
    };

    const handleItemEdit = (item) => {
        setEditingItem({ ...item });
    };

    const handleItemSave = () => {
        if (editingItem && onItemUpdate) {
            onItemUpdate(editingItem.id, {
                quantity: editingItem.quantity,
                price_net: editingItem.price_net,
                price_gross: editingItem.price_gross,
                discount_total: editingItem.discount_total,
            });
        }
        setEditingItem(null);
    };

    const handleItemCancel = () => {
        setEditingItem(null);
    };

    const handlePack = (itemId) => {
        if (!onPack) return;
        const qty = parseInt(packQty[itemId] ?? 1, 10);
        const safeQty = isNaN(qty) || qty <= 0 ? 1 : qty;
        onPack(itemId, safeQty);
    };

    if (!items.length) {
        return (
            <div className="flex items-center justify-center py-12 text-gray-500">
                <Package className="w-8 h-8 mr-3" />
                <span>Brak produktów w zamówieniu</span>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Nazwa/SKU
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ilość
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cena netto
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            VAT
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cena brutto
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Wartość
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Spakowane
                        </th>
                        {!readOnly && (
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Akcje
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {items.map((item) => {
                        const isEditing = editingItem && editingItem.id === item.id;
                        const shipped = item.quantity_shipped ?? 0;
                        const qty = item.quantity ?? 0;

                        return (
                            <tr key={item.id} className={isEditing ? 'bg-blue-50' : 'hover:bg-gray-50'}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="text-sm font-medium text-gray-900">{item.name}</div>
                                    <div className="text-sm text-gray-500">SKU: {item.sku}</div>
                                    {item.ean && (
                                        <div className="text-xs text-gray-400">EAN: {item.ean}</div>
                                    )}
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {isEditing ? (
                                        <input
                                            type="number"
                                            value={editingItem.quantity}
                                            onChange={(e) =>
                                                setEditingItem({
                                                    ...editingItem,
                                                    quantity: parseInt(e.target.value, 10) || 0,
                                                })
                                            }
                                            className="w-24 px-2 py-1 text-sm border rounded"
                                            min="1"
                                        />
                                    ) : (
                                        qty
                                    )}
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {isEditing ? (
                                        <input
                                            type="number"
                                            value={editingItem.price_net}
                                            onChange={(e) =>
                                                setEditingItem({
                                                    ...editingItem,
                                                    price_net: parseFloat(e.target.value),
                                                })
                                            }
                                            className="w-24 px-2 py-1 text-sm border rounded"
                                            step="0.01"
                                        />
                                    ) : (
                                        formatCurrency(item.price_net)
                                    )}
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {item.vat_rate}%
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {isEditing ? (
                                        <input
                                            type="number"
                                            value={editingItem.price_gross}
                                            onChange={(e) =>
                                                setEditingItem({
                                                    ...editingItem,
                                                    price_gross: parseFloat(e.target.value),
                                                })
                                            }
                                            className="w-24 px-2 py-1 text-sm border rounded"
                                            step="0.01"
                                        />
                                    ) : (
                                        <span className="font-medium">{formatCurrency(item.price_gross)}</span>
                                    )}
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span className="font-semibold">
                                        {formatCurrency(calculateItemTotal(isEditing ? editingItem : item))}
                                    </span>
                                    {item.discount_total > 0 && (
                                        <div className="text-xs text-red-600">
                                            Rabat: -{formatCurrency(item.discount_total)}
                                        </div>
                                    )}
                                </td>

                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {shipped} / {qty}
                                    {packingEnabled && onPack && (
                                        <div className="mt-2 flex items-center gap-2">
                                            <input
                                                type="number"
                                                min="1"
                                                value={packQty[item.id] ?? 1}
                                                onChange={(e) =>
                                                    setPackQty((prev) => ({
                                                        ...prev,
                                                        [item.id]: e.target.value,
                                                    }))
                                                }
                                                className="w-20 px-2 py-1 text-sm border rounded"
                                            />
                                            <button
                                                onClick={() => handlePack(item.id)}
                                                className="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700"
                                            >
                                                <Check className="w-4 h-4 mr-1" />
                                                Pakuj
                                            </button>
                                        </div>
                                    )}
                                </td>

                                {!readOnly && (
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {isEditing ? (
                                            <div className="flex items-center space-x-2">
                                                <button
                                                    onClick={handleItemSave}
                                                    className="text-green-600 hover:text-green-900"
                                                >
                                                    Zapisz
                                                </button>
                                                <button
                                                    onClick={handleItemCancel}
                                                    className="text-gray-600 hover:text-gray-900"
                                                >
                                                    Anuluj
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="flex items-center space-x-3">
                                                {!packingEnabled && (
                                                    <button
                                                        onClick={() => handleItemEdit(item)}
                                                        className="text-blue-600 hover:text-blue-900 inline-flex items-center gap-1"
                                                    >
                                                        <Edit2 className="w-4 h-4" /> Edytuj
                                                    </button>
                                                )}
                                                <button className="text-red-600 hover:text-red-900 inline-flex items-center gap-1">
                                                    <Trash2 className="w-4 h-4" /> Usuń
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
