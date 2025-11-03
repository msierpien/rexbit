import { useState, useMemo } from 'react';
import { Scan, X, Plus, Minus } from 'lucide-react';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { toast } from 'sonner';
import { useBarcodeScan } from '@/hooks/useBarcodeScan.js';
import { getScannerSounds } from '@/lib/scanner-sounds.js';

/**
 * Floating Barcode Scanner Panel
 * Sticky panel in bottom-right corner for scanning products
 * 
 * Features:
 * - Auto-scan with USB barcode scanner (działa globalnie)
 * - Quick quantity adjustment for last scanned item
 * - Sound feedback
 * - Auto-opens after scan to show last scanned product
 */
export default function BarcodeScanner({ products = [], onProductScanned, enabled = true }) {
    console.log('🎬 BarcodeScanner MOUNTED', { productsCount: products.length, enabled });
    
    const [isOpen, setIsOpen] = useState(false);
    const [lastScanned, setLastScanned] = useState(null);
    const [quickQuantity, setQuickQuantity] = useState('');
    const sounds = useMemo(() => getScannerSounds(), []);

    // Find product by EAN code
    const findProductByEan = (ean) => {
        return products.find((p) => p.ean === ean.trim());
    };

    // Handle barcode scan from USB scanner
    const handleScan = (ean) => {
        console.log('🔍 Skanowanie EAN:', ean);
        console.log('📦 Dostępne produkty:', products.length);
        console.log('🎯 Callback onProductScanned:', typeof onProductScanned);
        
        if (!enabled) return;

        const product = findProductByEan(ean);
        console.log('✅ Znaleziony produkt:', product);
        
        if (!product) {
            sounds.playError();
            toast.error(`Produkt o kodzie EAN "${ean}" nie został znaleziony`, {
                description: 'Sprawdź czy produkt istnieje w bazie danych',
            });
            // Auto-open panel on error to show what was scanned
            setIsOpen(true);
            return;
        }

        // Add product with quantity 1
        console.log('➕ Dodawanie produktu:', product.id, 'ilość: 1');
        onProductScanned(product, 1);
        
        // Update last scanned IMMEDIATELY after adding
        setLastScanned({ product, ean, timestamp: Date.now() });
        
        sounds.playSuccess();
        
        toast.success(`Zeskanowano: ${product.name}`, {
            description: `SKU: ${product.sku} | +1 szt`,
        });

        // Auto-open panel after scan to show "Ostatnio zeskanowane"
        setIsOpen(true);
    };

    const { buffer, isScanning } = useBarcodeScan({
        onScan: handleScan,
        enabled: enabled,
        minLength: 3,
    });

    // Handle quick quantity adjustment
    const handleQuickQuantitySubmit = () => {
        if (!lastScanned || !quickQuantity) return;

        const qty = parseInt(quickQuantity, 10);
        if (isNaN(qty) || qty <= 0) {
            toast.error('Nieprawidłowa ilość');
            return;
        }

        onProductScanned(lastScanned.product, qty);
        sounds.playSuccess();
        toast.success(`Dodano ${qty} szt: ${lastScanned.product.name}`);
        setQuickQuantity('');
    };

    if (!enabled) return null;

    return (
        <>
            {/* Floating Toggle Button */}
            {!isOpen && (
                <button
                    onClick={() => setIsOpen(true)}
                    className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg transition-all hover:bg-blue-700 hover:shadow-xl"
                    aria-label="Otwórz skaner"
                >
                    <Scan className="h-6 w-6" />
                </button>
            )}

            {/* Floating Scanner Panel */}
            {isOpen && (
                <Card className="fixed bottom-6 right-6 z-50 w-96 shadow-2xl">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Scan className="h-5 w-5 text-blue-600" />
                            Skaner kodów EAN
                            {isScanning && (
                                <Badge variant="outline" className="ml-2 animate-pulse">
                                    Skanowanie...
                                </Badge>
                            )}
                        </CardTitle>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setIsOpen(false)}
                            className="h-8 w-8 p-0"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {/* Scanning Status */}
                        {isScanning && (
                            <div className="flex items-center gap-2 rounded-lg bg-blue-50 p-3 dark:bg-blue-950">
                                <div className="h-2 w-2 animate-pulse rounded-full bg-blue-600"></div>
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    Skanowanie... {buffer && <span className="font-mono">({buffer})</span>}
                                </p>
                            </div>
                        )}

                        {/* Last Scanned Product */}
                        {lastScanned && (
                            <div className="space-y-3 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950">
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                            Ostatnio zeskanowane
                                        </p>
                                        <p className="text-xs text-green-700 dark:text-green-300">
                                            {lastScanned.product.name}
                                        </p>
                                        <p className="font-mono text-xs text-green-600 dark:text-green-400">
                                            SKU: {lastScanned.product.sku} | EAN: {lastScanned.ean}
                                        </p>
                                    </div>
                                </div>

                                {/* Quick Quantity Add */}
                                <div className="space-y-2">
                                    <label className="text-xs font-medium text-green-800 dark:text-green-200">
                                        Dodaj więcej sztuk
                                    </label>
                                    <div className="flex gap-2">
                                        <div className="flex flex-1 items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setQuickQuantity(String(Math.max(0, (parseInt(quickQuantity) || 0) - 1)))}
                                                className="h-8 w-8 p-0"
                                            >
                                                <Minus className="h-3 w-3" />
                                            </Button>
                                            <Input
                                                type="number"
                                                min="1"
                                                step="1"
                                                value={quickQuantity}
                                                onChange={(e) => setQuickQuantity(e.target.value)}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        handleQuickQuantitySubmit();
                                                    }
                                                }}
                                                placeholder="Ilość..."
                                                className="h-8 text-center"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setQuickQuantity(String((parseInt(quickQuantity) || 0) + 1))}
                                                className="h-8 w-8 p-0"
                                            >
                                                <Plus className="h-3 w-3" />
                                            </Button>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={!quickQuantity || parseInt(quickQuantity) <= 0}
                                            onClick={handleQuickQuantitySubmit}
                                            className="h-8"
                                        >
                                            Dodaj
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Instructions */}
                        <div className="rounded-lg bg-muted p-3">
                            <p className="text-xs text-muted-foreground">
                                <strong>Jak używać:</strong>
                            </p>
                            <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                                <li>• Zeskanuj kod kreskowy - automatycznie doda 1 szt</li>
                                <li>• Ponowne skanowanie tego samego produktu doda kolejną sztukę</li>
                                <li>• Użyj pola "Dodaj więcej sztuk" aby szybko zwiększyć ilość</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
