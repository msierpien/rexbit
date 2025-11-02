import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Loader2 } from 'lucide-react';

export default function StockHistoryDialog({
    open,
    loading,
    product,
    summary,
    stocks,
    history,
    error,
    onClose,
    onRefresh,
    formatQuantity,
}) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        Historia stanów magazynowych
                        {product ? ` — ${product.name}` : ''}
                    </DialogTitle>
                    <DialogDescription>
                        Podgląd dostępności produktu w magazynach oraz ostatnich operacji magazynowych.
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12 text-sm text-gray-500">
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Ładowanie danych magazynowych...
                    </div>
                ) : error ? (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                ) : (
                    <div className="space-y-6">
                        <div className="rounded-lg border border-gray-200">
                            <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                Podsumowanie
                            </div>
                            <div className="grid gap-4 px-4 py-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <p className="text-xs text-gray-500">Dostępne</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {formatQuantity(summary?.total_available ?? 0)} szt.
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Na stanie</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {formatQuantity(summary?.total_on_hand ?? 0)} szt.
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Rezerwacje</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {formatQuantity(summary?.total_reserved ?? 0)} szt.
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">W drodze</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {formatQuantity(summary?.total_incoming ?? 0)} szt.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-200">
                            <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                Magazyny
                            </div>
                            {stocks && stocks.length > 0 ? (
                                <div className="grid gap-4 px-4 py-3 sm:grid-cols-2">
                                    {stocks.map((stock) => (
                                        <div key={stock.warehouse_id} className="rounded-lg border border-gray-100 p-3">
                                            <div className="text-sm font-semibold text-gray-900">
                                                {stock.warehouse_name ?? '—'}
                                            </div>
                                            <div className="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                                                <span>
                                                    Dostępne:{' '}
                                                    <span className="font-semibold text-gray-900">
                                                        {formatQuantity(stock.available)} szt.
                                                    </span>
                                                </span>
                                                <span>Na stanie: {formatQuantity(stock.on_hand)} szt.</span>
                                                {Number(stock.reserved) > 0 && (
                                                    <span>Rezerwacje: {formatQuantity(stock.reserved)} szt.</span>
                                                )}
                                                {Number(stock.incoming) > 0 && (
                                                    <span>W drodze: {formatQuantity(stock.incoming)} szt.</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="px-4 py-3 text-sm text-gray-500">Brak danych magazynowych.</div>
                            )}
                        </div>

                        <div className="rounded-lg border border-gray-200">
                            <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
                                Ostatnie operacje
                            </div>
                            {history && history.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th className="px-4 py-2">Data</th>
                                                <th className="px-4 py-2">Dokument</th>
                                                <th className="px-4 py-2">Magazyn</th>
                                                <th className="px-4 py-2">Zmiana</th>
                                                <th className="px-4 py-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {history.map((entry) => (
                                                <tr key={entry.id}>
                                                    <td className="px-4 py-2 text-gray-600">
                                                        {entry.issued_at ?? entry.created_at ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        <div className="font-medium text-gray-900">
                                                            {entry.document_number ?? '—'}
                                                        </div>
                                                        <div className="text-xs text-gray-500">{entry.document_type}</div>
                                                    </td>
                                                    <td className="px-4 py-2 text-gray-600">
                                                        {entry.warehouse_name ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        <span
                                                            className={
                                                                entry.quantity_change >= 0
                                                                    ? 'font-semibold text-emerald-600'
                                                                    : 'font-semibold text-rose-600'
                                                            }
                                                        >
                                                            {entry.quantity_change >= 0 ? '+' : ''}
                                                            {formatQuantity(entry.quantity_change)} szt.
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        {entry.document_status_label ? (
                                                            <Badge variant="outline">{entry.document_status_label}</Badge>
                                                        ) : (
                                                            <span className="text-xs text-gray-400">—</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="px-4 py-3 text-sm text-gray-500">
                                    Brak zarejestrowanych operacji magazynowych.
                                </div>
                            )}
                        </div>
                    </div>
                )}
                <DialogFooter className="mt-4">
                    {!loading && product && (
                        <Button variant="outline" onClick={() => onRefresh(product)}>
                            Odśwież
                        </Button>
                    )}
                    <Button variant="outline" onClick={onClose}>
                        Zamknij
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
