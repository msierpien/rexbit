import React from 'react';

function StockIndicator({ value, label, variant = 'default' }) {
    const variants = {
        default: 'text-gray-600',
        success: 'text-green-600',
        warning: 'text-yellow-600',
        danger: 'text-red-600',
    };

    return (
        <div className="flex flex-col text-center">
            <span className={`text-sm font-semibold ${variants[variant]}`}>
                {value}
            </span>
            <span className="text-xs text-gray-500">{label}</span>
        </div>
    );
}

export default function WarehouseStockDisplay({ stocks, warehouseId = null, compact = false }) {
    if (!stocks || stocks.length === 0) {
        return (
            <div className="text-xs text-gray-400">
                Brak danych magazynowych
            </div>
        );
    }

    // Filter by warehouse if specified
    const relevantStocks = warehouseId 
        ? stocks.filter(stock => stock.warehouse_location_id === warehouseId)
        : stocks;

    if (relevantStocks.length === 0) {
        return (
            <div className="text-xs text-gray-400">
                Brak stanów w tym magazynie
            </div>
        );
    }

    // Calculate totals
    const totals = relevantStocks.reduce((acc, stock) => ({
        on_hand: acc.on_hand + parseFloat(stock.on_hand || 0),
        reserved: acc.reserved + parseFloat(stock.reserved || 0),
        incoming: acc.incoming + parseFloat(stock.incoming || 0),
    }), { on_hand: 0, reserved: 0, incoming: 0 });

    const available = totals.on_hand - totals.reserved;

    // Determine available stock variant
    const getAvailableVariant = () => {
        if (available <= 0) return 'danger';
        if (available <= 10) return 'warning';
        return 'success';
    };

    if (compact) {
        return (
            <div className="flex items-center gap-2 text-xs">
                <span className={`font-semibold ${
                    available <= 0 ? 'text-red-600' :
                    available <= 10 ? 'text-yellow-600' :
                    'text-green-600'
                }`}>
                    {available.toFixed(0)} szt.
                </span>
                {totals.reserved > 0 && (
                    <span className="text-gray-500">
                        ({totals.reserved.toFixed(0)} zarez.)
                    </span>
                )}
            </div>
        );
    }

    return (
        <div className="flex gap-4 p-3 bg-gray-50 rounded-lg">
            <StockIndicator 
                value={available.toFixed(0)} 
                label="Dostępne"
                variant={getAvailableVariant()}
            />
            <StockIndicator 
                value={totals.on_hand.toFixed(0)} 
                label="Na stanie"
            />
            {totals.reserved > 0 && (
                <StockIndicator 
                    value={totals.reserved.toFixed(0)} 
                    label="Zarezerwowane"
                    variant="warning"
                />
            )}
            {totals.incoming > 0 && (
                <StockIndicator 
                    value={totals.incoming.toFixed(0)} 
                    label="Przychodzące"
                    variant="success"
                />
            )}
            
            {warehouseId && relevantStocks.length === 1 && (
                <div className="ml-auto text-xs text-gray-500">
                    {relevantStocks[0].warehouse?.name}
                </div>
            )}
        </div>
    );
}