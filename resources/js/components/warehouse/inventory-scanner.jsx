import { useState, useRef, useCallback, useMemo, useEffect } from 'react';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Label } from '@/components/ui/label.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.jsx';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.jsx';
import { Scan, Plus, Minus, Check, X } from 'lucide-react';
import { toast } from 'sonner';
import { useBarcodeScan } from '@/hooks/useBarcodeScan.js';
import { getScannerSounds } from '@/lib/scanner-sounds.js';

/**
 * Floating scanner panel tailored for inventory counting.
 * Reuses the global barcode scanning hook and scanner sounds
 * to provide the same UX as warehouse documents.
 */
export default function InventoryScanner({
    products = [],
    onQuantityUpdate,
    enabled = true,
    currentItems = [],
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [manualEan, setManualEan] = useState('');
    const [quantityInput, setQuantityInput] = useState(1);
    const [manualAddAmount, setManualAddAmount] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [lastScanned, setLastScanned] = useState(null);
    const inputRef = useRef(null);
    const sounds = useMemo(() => getScannerSounds(), []);
    const quickPackages = useMemo(() => [5, 10, 12, 24], []);

    useEffect(() => {
        if (isOpen && inputRef.current) {
            inputRef.current.focus();
        }
    }, [isOpen]);

    const productByEan = useMemo(() => {
        const map = new Map();
        products.forEach((product) => {
            if (product?.ean) {
                map.set(product.ean.trim(), product);
            }
        });
        return map;
    }, [products]);

    const currentQuantities = useMemo(() => {
        const map = new Map();
        currentItems.forEach((item) => {
            map.set(item.product.id, item.counted_quantity);
        });
        return map;
    }, [currentItems]);

    const getCurrentQuantity = useCallback(
        (productId) => {
            if (lastScanned?.product?.id === productId && lastScanned.countedQuantity !== undefined) {
                return lastScanned.countedQuantity;
            }
            return currentQuantities.get(productId) ?? 0;
        },
        [currentQuantities, lastScanned]
    );

    const findProductByEan = useCallback(async (ean) => {
        try {
            const response = await fetch('/inventory-counts/find-product-by-ean', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ ean }),
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();
            return data.success ? data.product : null;
        } catch (error) {
            console.error('Error finding product by EAN:', error);
            return null;
        }
    }, []);

    const confirmZeroing = useCallback((targetQuantity) => {
        if (targetQuantity !== 0) {
            return true;
        }
        return window.confirm('Czy na pewno chcesz wyzerować policzoną ilość dla tego produktu?');
    }, []);

    const runQuantityUpdate = useCallback(
        async (product, targetQuantity, meta = {}) => {
            if (!enabled) {
                return null;
            }

            setIsProcessing(true);

            try {
                const updatedItem = await onQuantityUpdate(
                    product,
                    targetQuantity,
                    meta.ean ?? product.ean
                );

                setLastScanned({
                    product: updatedItem.product,
                    countedQuantity: updatedItem.counted_quantity,
                    addedQuantity: meta.addedQuantity ?? null,
                    timestamp: new Date(),
                    ean: meta.ean ?? updatedItem.product.ean,
                    actionType: meta.actionType ?? 'update',
                });

                setQuantityInput(updatedItem.counted_quantity);
                sounds.playSuccess();
                toast.success(`Zaktualizowano: ${updatedItem.product.name}`, {
                    description: `Policzono ${updatedItem.counted_quantity} szt`,
                });
                setIsOpen(true);

                return updatedItem;
            } catch (error) {
                console.error('Error during quantity update:', error);
                sounds.playError();
                toast.error(error.message || 'Błąd podczas aktualizacji ilości');
                throw error;
            } finally {
                setIsProcessing(false);
            }
        },
        [enabled, onQuantityUpdate, sounds]
    );

    const handleScan = useCallback(
        async (ean, { quantityToAdd = 1 } = {}) => {
            if (!enabled || isProcessing) {
                return;
            }

            const trimmedEan = ean.trim();
            if (!trimmedEan) {
                return;
            }

            let product = productByEan.get(trimmedEan);
            if (!product) {
                product = await findProductByEan(trimmedEan);
            }

            if (!product) {
                sounds.playError();
                toast.error(`Produkt o kodzie EAN "${trimmedEan}" nie został znaleziony w systemie`);
                setIsOpen(true);
                return;
            }

            const currentQty = getCurrentQuantity(product.id);
            const targetQuantity = currentQty + quantityToAdd;

            try {
                await runQuantityUpdate(product, targetQuantity, {
                    ean: trimmedEan,
                    addedQuantity: quantityToAdd,
                    actionType: 'scan',
                });
            } catch {
                // runQuantityUpdate already handles error feedback
            }
        },
        [
            enabled,
            isProcessing,
            productByEan,
            findProductByEan,
            getCurrentQuantity,
            runQuantityUpdate,
            sounds,
        ]
    );

    const { buffer, isScanning } = useBarcodeScan({
        onScan: handleScan,
        enabled,
        minLength: 3,
    });

    const handleManualScan = useCallback(async () => {
        if (!manualEan.trim()) {
            return;
        }

        try {
            await handleScan(manualEan.trim());
        } finally {
            setManualEan('');
        }
    }, [manualEan, handleScan]);

    const adjustLastScannedQuantity = useCallback(
        async (adjustment) => {
            if (!lastScanned || isProcessing) {
                return;
            }

            const product = lastScanned.product;
            const currentQty = getCurrentQuantity(product.id);
            const targetQuantity = Math.max(0, currentQty + adjustment);

            if (!confirmZeroing(targetQuantity)) {
                return;
            }

            try {
                await runQuantityUpdate(product, targetQuantity, {
                    addedQuantity: adjustment,
                    ean: lastScanned.ean ?? product.ean,
                    actionType: 'adjust',
                });
            } catch {
                // Feedback handled in runQuantityUpdate
            }
        },
        [lastScanned, isProcessing, getCurrentQuantity, runQuantityUpdate, confirmZeroing]
    );

    const setLastScannedQuantity = useCallback(
        async (targetQuantity) => {
            if (!lastScanned || isProcessing || targetQuantity < 0) {
                return;
            }

            if (!confirmZeroing(targetQuantity)) {
                return;
            }

            try {
                await runQuantityUpdate(lastScanned.product, targetQuantity, {
                    ean: lastScanned.ean ?? lastScanned.product.ean,
                    actionType: 'set',
                });
            } catch {
                // Feedback handled in runQuantityUpdate
            }
        },
        [lastScanned, isProcessing, runQuantityUpdate, confirmZeroing]
    );

    const handleQuickAdd = useCallback(
        async (packSize) => {
            if (!lastScanned || isProcessing) {
                return;
            }

            const product = lastScanned.product;
            const currentQty = getCurrentQuantity(product.id);
            const baselineQuantity =
                lastScanned.actionType === 'scan'
                    ? Math.max(0, currentQty - (lastScanned.addedQuantity ?? 0))
                    : currentQty;
            const targetQuantity = baselineQuantity + packSize;

            if (!confirmZeroing(targetQuantity)) {
                return;
            }

            try {
                await runQuantityUpdate(product, targetQuantity, {
                    addedQuantity: packSize,
                    ean: lastScanned.ean ?? product.ean,
                    actionType: 'quickAdd',
                });
            } catch {
                // Feedback handled in runQuantityUpdate
            }
        },
        [lastScanned, isProcessing, getCurrentQuantity, runQuantityUpdate, confirmZeroing]
    );

    const handleManualAdd = useCallback(async () => {
        if (!lastScanned || isProcessing) {
            return;
        }

        const amount = parseInt(manualAddAmount, 10);
        if (Number.isNaN(amount) || amount <= 0) {
            toast.error('Podaj dodatnią liczbę sztuk do dodania');
            return;
        }

        const product = lastScanned.product;
        const currentQty = getCurrentQuantity(product.id);
        const baselineQuantity =
            lastScanned.actionType === 'scan'
                ? Math.max(0, currentQty - (lastScanned.addedQuantity ?? 0))
                : currentQty;
        const targetQuantity = baselineQuantity + amount;

        if (!confirmZeroing(targetQuantity)) {
            return;
        }

        try {
            await runQuantityUpdate(product, targetQuantity, {
                addedQuantity: amount,
                ean: lastScanned.ean ?? product.ean,
                actionType: 'manualAdd',
            });
            setManualAddAmount('');
        } catch {
            // Feedback handled in runQuantityUpdate
        }
    }, [lastScanned, isProcessing, manualAddAmount, getCurrentQuantity, runQuantityUpdate, confirmZeroing]);

    if (!enabled) {
        return null;
    }

    return (
        <>
            {/* Floating Scanner Button */}
            <div className="fixed bottom-4 right-4 z-50">
                <Button
                    onClick={() => setIsOpen(true)}
                    className="h-14 w-14 rounded-full shadow-lg"
                    size="lg"
                >
                    <Scan className="h-6 w-6" />
                </Button>
            </div>

            {/* Scanner Panel */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Scan className="h-5 w-5 text-blue-600" />
                            Skaner inwentaryzacyjny
                            {isScanning && (
                                <Badge variant="outline" className="ml-2 animate-pulse">
                                    Skanowanie...
                                </Badge>
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            Skanuj kody EAN lub wprowadź je ręcznie. Każde skanowanie zwiększa policzoną ilość.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Scanning buffer preview */}
                        {isScanning && (
                            <div className="flex items-center gap-2 rounded-lg bg-blue-50 p-3 dark:bg-blue-950">
                                <div className="h-2 w-2 animate-pulse rounded-full bg-blue-600" />
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    Skanowanie... {buffer && <span className="font-mono">({buffer})</span>}
                                </p>
                            </div>
                        )}

                        {/* Manual EAN Input */}
                        <div className="space-y-2">
                            <Label htmlFor="manual-ean">Kod EAN</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="manual-ean"
                                    ref={inputRef}
                                    value={manualEan}
                                    onChange={(event) => setManualEan(event.target.value)}
                                    placeholder="Wprowadź kod EAN..."
                                    data-scanner-input="true"
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            handleManualScan();
                                        }
                                    }}
                                />
                                <Button onClick={handleManualScan} disabled={!manualEan.trim() || isProcessing}>
                                    <Scan className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        {/* Last Scanned Product */}
                        {lastScanned && (
                            <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm">Ostatnio zeskanowany</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <div className="font-medium">{lastScanned.product.name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            SKU: {lastScanned.product.sku} | EAN: {lastScanned.product.ean}
                                        </div>
                                    </div>

                                    {/* Current Quantity Display */}
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Policzono razem:</span>
                                        <Badge variant="outline" className="font-mono">
                                            {lastScanned.countedQuantity} szt
                                        </Badge>
                                    </div>

                                    {/* Quick Adjustment Buttons */}
                                    <div className="grid grid-cols-5 gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => adjustLastScannedQuantity(-1)}
                                            disabled={isProcessing || lastScanned.countedQuantity <= 0}
                                        >
                                            <Minus className="h-3 w-3" />
                                        </Button>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setLastScannedQuantity(0)}
                                            disabled={isProcessing}
                                        >
                                            0
                                        </Button>

                                        <Input
                                            className="h-8 text-center font-mono"
                                            value={quantityInput}
                                            onChange={(event) =>
                                                setQuantityInput(Math.max(0, parseInt(event.target.value, 10) || 0))
                                            }
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    setLastScannedQuantity(quantityInput);
                                                }
                                            }}
                                            type="number"
                                            min="0"
                                        />

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setLastScannedQuantity(quantityInput)}
                                            disabled={isProcessing}
                                        >
                                            <Check className="h-3 w-3" />
                                        </Button>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => adjustLastScannedQuantity(1)}
                                            disabled={isProcessing}
                                        >
                                            <Plus className="h-3 w-3" />
                                        </Button>
                                    </div>

                                    {/* Package Additions */}
                                    <div className="space-y-2">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            Szybkie dodawanie paczek
                                        </span>
                                        <p className="text-xs text-muted-foreground">
                                            Przy pierwszym kliknięciu odejmujemy 1 szt. ze skanu i dodajemy wskazaną paczkę.
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {quickPackages.map((packSize) => (
                                                <Button
                                                    key={packSize}
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleQuickAdd(packSize)}
                                                    disabled={isProcessing}
                                                >
                                                    +{packSize}
                                                </Button>
                                            ))}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                className="h-8 w-24 text-center font-mono"
                                                value={manualAddAmount}
                                                onChange={(event) => setManualAddAmount(event.target.value)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        handleManualAdd();
                                                    }
                                                }}
                                                type="number"
                                                min="1"
                                                placeholder="+x"
                                                disabled={isProcessing}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleManualAdd}
                                                disabled={isProcessing || !manualAddAmount.trim()}
                                            >
                                                Dodaj
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="text-xs text-muted-foreground text-center">
                                        Ostatnia aktualizacja: {lastScanned.timestamp.toLocaleTimeString()}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Instructions */}
                        <div className="space-y-1 text-xs text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-green-500" />
                                <span>Przyłóż skaner do kodu EAN – każdorazowo doda +1 szt do licznika.</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-blue-500" />
                                <span>Skaner działa w tle – nie musisz klikać w okno.</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-amber-500" />
                                <span>Przyciski +5/+10/+12/+24 i +X pozwalają szybko dorzucić całe paczki (pierwsze kliknięcie odejmuje sztukę z ostatniego skanu).</span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsOpen(false)}>
                            <X className="mr-2 h-4 w-4" />
                            Zamknij
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
