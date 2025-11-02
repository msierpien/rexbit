import { useMemo } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table.jsx';
import WarehouseStockDisplay from './stock-display.jsx';
import ProductSelect from './product-select.jsx';

const emptyItem = {
    product_id: '',
    quantity: 1,
    unit_price: '',
    vat_rate: '',
};

const currencyFormatter = new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const quantityFormatter = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
});

function parseNumber(value) {
    const numeric = parseFloat(value);
    return Number.isNaN(numeric) ? 0 : numeric;
}

export default function DocumentItems({ items, onChange, products, warehouseId = null }) {
    const safeItems = items.length ? items : [emptyItem];

    const totals = useMemo(() => {
        return safeItems.reduce(
            (accumulator, item) => {
                const quantity = parseNumber(item.quantity);
                const unitPrice = parseNumber(item.unit_price);
                return {
                    quantity: accumulator.quantity + quantity,
                    net: accumulator.net + quantity * unitPrice,
                };
            },
            { quantity: 0, net: 0 }
        );
    }, [safeItems]);

    const updateItem = (index, field, value) => {
        const source = items.length ? items : safeItems;
        const next = source.map((item, currentIndex) =>
            currentIndex === index ? { ...item, [field]: value } : item
        );
        onChange(next);
    };

    const addItem = () => {
        onChange(items.length ? [...items, { ...emptyItem }] : [{ ...emptyItem }]);
    };

    const removeItem = (index) => {
        const source = items.length ? items : safeItems;
        if (source.length === 1) {
            onChange([{ ...emptyItem }]);
            return;
        }

        onChange(source.filter((_, currentIndex) => currentIndex !== index));
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-lg border border-border">
                <Table>
                    <TableHeader className="bg-muted/30">
                        <TableRow className="text-left">
                            <TableHead className="w-[320px]">Produkt</TableHead>
                            <TableHead className="w-32 text-right">Ilość</TableHead>
                            <TableHead className="w-36 text-right">Cena netto</TableHead>
                            <TableHead className="w-28 text-right">VAT %</TableHead>
                            <TableHead className="w-40 text-right">Wartość netto</TableHead>
                            <TableHead className="w-16 text-right"> </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {safeItems.map((item, index) => {
                            const selectedProduct = products.find((product) => product.id == item.product_id);
                            const quantity = parseNumber(item.quantity);
                            const unitPrice = parseNumber(item.unit_price);
                            const netValue = quantity * unitPrice;

                            return (
                                <TableRow key={index} className="align-top">
                                    <TableCell className="space-y-2">
                                        <div>
                                            <ProductSelect
                                                products={products}
                                                value={item.product_id ?? ''}
                                                onChange={(productId) => updateItem(index, 'product_id', productId)}
                                                placeholder="Wyszukaj po nazwie, SKU lub EAN..."
                                            />
                                        </div>
                                        {item.product_id ? (
                                            <WarehouseStockDisplay
                                                stocks={selectedProduct?.warehouse_stocks}
                                                warehouseId={warehouseId}
                                                compact
                                            />
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Wybierz produkt, aby zobaczyć stany magazynowe.
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.quantity ?? ''}
                                            onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                                            className="text-right"
                                        />
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.unit_price ?? ''}
                                            onChange={(event) => updateItem(index, 'unit_price', event.target.value)}
                                            className="text-right"
                                        />
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <Input
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={item.vat_rate ?? ''}
                                            onChange={(event) => updateItem(index, 'vat_rate', event.target.value)}
                                            className="text-right"
                                        />
                                    </TableCell>
                                    <TableCell className="text-right font-medium align-top">
                                        {currencyFormatter.format(netValue)}
                                    </TableCell>
                                    <TableCell className="text-right align-top">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            type="button"
                                            onClick={() => removeItem(index)}
                                            title="Usuń pozycję"
                                        >
                                            <Trash2 className="size-4" />
                                            <span className="sr-only">Usuń pozycję</span>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/20 px-4 py-3 text-sm">
                <div className="text-muted-foreground">
                    Łącznie pozycji: <span className="font-medium text-foreground">{safeItems.length}</span>
                </div>
                <div className="flex flex-wrap items-center gap-4 text-sm">
                    <span className="text-muted-foreground">
                        Ilość:{' '}
                        <span className="font-semibold text-foreground">
                            {quantityFormatter.format(totals.quantity)} szt.
                        </span>
                    </span>
                    <span className="text-muted-foreground">
                        Wartość netto:{' '}
                        <span className="font-semibold text-foreground">
                            {currencyFormatter.format(totals.net)}
                        </span>
                    </span>
                </div>
            </div>

            <Button type="button" variant="outline" onClick={addItem} className="gap-2">
                <Plus className="size-4" />
                Dodaj pozycję
            </Button>
        </div>
    );
}
