// OrderDetail.jsx - Komponent główny widoku zamówienia (inspirowany BaseLinker)

import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    Package, 
    User, 
    MapPin, 
    Truck, 
    CreditCard, 
    MessageSquare,
    Edit2,
    Plus,
    Download,
    Phone,
    Mail,
    Calendar,
    DollarSign,
    FileText,
    ShieldCheck
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import StatusBadge from '@/components/Orders/StatusBadge';
import OrderItemsTable from '@/components/Orders/OrderItemsTable';
import AddressCard from '@/components/Orders/AddressCard';
import OrderTimeline from '@/components/Orders/OrderTimeline';
import PaymentInfo from '@/components/Orders/PaymentInfo';
import ShippingInfo from '@/components/Orders/ShippingInfo';
import BarcodeScanner from '@/components/warehouse/barcode-scanner.jsx';

export default function OrderDetail({ auth, order, breadcrumbs = [] }) {
    const [orderState, setOrderState] = useState(order);
    const [packingEnabled, setPackingEnabled] = useState(false);
    const [activeTab, setActiveTab] = useState('details');
    const [expandedSections, setExpandedSections] = useState({
        messages: false,
        history: true,
        documents: false
    });

    const toggleSection = (section) => {
        setExpandedSections(prev => ({
            ...prev,
            [section]: !prev[section]
        }));
    };

    // Produkty do skanera pakowania (filtr na EAN)
    const packingProducts = useMemo(() => {
        return (orderState.items || [])
            .filter((item) => !!item.ean)
            .map((item) => ({
                id: item.id,
                ean: item.ean,
                name: item.name,
                sku: item.sku,
                order_item_id: item.id,
                quantity: item.quantity,
                quantity_shipped: item.quantity_shipped ?? 0,
            }));
    }, [orderState.items]);

    const warehouseDocuments = useMemo(() => {
        const docs = [];
        if (orderState.metadata?.reservation_document) {
            docs.push({
                type: 'RES',
                warehouse_document_id: orderState.metadata.reservation_document.warehouse_document_id,
                created_at: orderState.metadata.reservation_document.created_at,
            });
        }
        if (orderState.metadata?.reservation_wz) {
            docs.push({
                type: 'WZ',
                warehouse_document_id: orderState.metadata.reservation_wz.warehouse_document_id,
                created_at: orderState.metadata.reservation_wz.created_at,
            });
        }
        return docs;
    }, [orderState.metadata]);

    const handlePackItem = async (orderItemId, qty = 1) => {
        try {
            const response = await fetch(`/orders/${orderState.id}/items/${orderItemId}/pack`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ quantity: qty }),
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Nie udało się spakować pozycji.');
            }

            setOrderState((prev) => {
                const updatedItems = (prev.items || []).map((item) => {
                    if (item.id === orderItemId) {
                        return {
                            ...item,
                            quantity_shipped: result.item.quantity_shipped,
                        };
                    }
                    return item;
                });

                return {
                    ...prev,
                    items: updatedItems,
                    fulfillment_status: result.order_fulfillment_status || prev.fulfillment_status,
                };
            });
        } catch (error) {
            console.error(error);
        }
    };

    const handleCreateReservation = async () => {
        const response = await fetch(`/orders/${currentOrder.id}/reservation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const data = await response.json();
        if (data.success) {
            setOrderState(data.order);
        }
    };

    const handleConvertReservationToWz = async () => {
        const response = await fetch(`/orders/${currentOrder.id}/reservation/wz`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const data = await response.json();
        if (data.success) {
            setOrderState(data.order);
        }
    };

    const handleStatusChange = (newStatus) => {
        router.put(`/orders/${currentOrder.id}/status`, {
            status: newStatus
        });
    };

    const formatCurrency = (amount, currency = 'PLN') => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const currentOrder = orderState;

    return (
        <DashboardLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href="/orders"
                            className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                        >
                            <ArrowLeft className="w-4 h-4 mr-1" />
                            Powróć do listy zamówień
                        </Link>
                        <div className="text-lg font-semibold text-gray-800">
                            Zamówienie #{currentOrder.number}
                        </div>
                        <StatusBadge status={currentOrder.status} />
                    </div>
                    <div className="flex items-center space-x-2">
                        <button className="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <Edit2 className="w-4 h-4 mr-1 inline" />
                            Edytuj
                        </button>
                        <button className="px-3 py-2 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            <Download className="w-4 h-4 mr-1 inline" />
                            Dokumenty
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Zamówienie #${currentOrder.number}`} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Breadcrumbs */}
                    <nav className="flex mb-6" aria-label="Breadcrumb">
                        <ol className="inline-flex items-center space-x-1 md:space-x-3">
                            <li className="inline-flex items-center">
                                <Link href="/dashboard" className="text-gray-500 hover:text-gray-700">
                                    Dashboard
                                </Link>
                            </li>
                            <li>
                                <div className="flex items-center">
                                    <span className="mx-2 text-gray-400">/</span>
                                    <Link href="/orders" className="text-gray-500 hover:text-gray-700">
                                        Zamówienia
                                    </Link>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div className="flex items-center">
                                    <span className="mx-2 text-gray-400">/</span>
                                    <span className="text-gray-800">#{currentOrder.number}</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Główna kolumna - produkty */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Produkty w zamówieniu */}
                            <div className="bg-white rounded-lg shadow">
                                <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                    <div className="flex items-center">
                                        <Package className="w-5 h-5 text-gray-400 mr-2" />
                                        <h3 className="text-lg font-medium text-gray-900">Produkty</h3>
                                        <span className="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                                            {currentOrder.items?.length || 0}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button className="flex items-center text-sm text-blue-600 hover:text-blue-800">
                                            <Plus className="w-4 h-4 mr-1" />
                                            Dodaj produkt
                                        </button>
                                        <button
                                            onClick={() => setPackingEnabled((prev) => !prev)}
                                            className={`flex items-center text-sm px-3 py-1 rounded-md ${
                                                packingEnabled
                                                    ? 'bg-green-100 text-green-700 border border-green-300'
                                                    : 'bg-gray-100 text-gray-700 border border-gray-300'
                                            }`}
                                        >
                                            <Package className="w-4 h-4 mr-1" />
                                            Pakuj
                                        </button>
                                    </div>
                                </div>
                                
                                <OrderItemsTable 
                                    items={currentOrder.items || []} 
                                    currency={currentOrder.currency}
                                    packingEnabled={packingEnabled}
                                    onPack={(orderItemId, qty) => handlePackItem(orderItemId, qty)}
                                    onItemUpdate={(itemId, updates) => {
                                        router.put(`/orders/${currentOrder.id}/items/${itemId}`, updates);
                                    }}
                                />

                                {/* Podsumowanie finansowe */}
                                <div className="px-6 py-4 bg-gray-50 border-t">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-gray-600">Wartość netto:</span>
                                        <span className="font-medium">{formatCurrency(currentOrder.total_net, currentOrder.currency)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-sm mt-1">
                                        <span className="text-gray-600">VAT:</span>
                                        <span className="font-medium">{formatCurrency(currentOrder.total_gross - currentOrder.total_net, currentOrder.currency)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-base font-semibold mt-2 pt-2 border-t">
                                        <span>Razem brutto:</span>
                                        <span className="text-lg">{formatCurrency(currentOrder.total_gross, currentOrder.currency)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Timeline / Historia */}
                            <OrderTimeline 
                                history={currentOrder.status_history || []}
                                messages={currentOrder.messages || []}
                                expanded={expandedSections}
                                onToggle={toggleSection}
                            />
                        </div>

                        {/* Kolumna boczna - informacje */}
                        <div className="space-y-6">
                            {/* Pakowanie skanerem */}
                            <div className="bg-white rounded-lg shadow border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Package className="w-5 h-5 text-gray-500" />
                                            <h3 className="text-base font-medium text-gray-900">Pakowanie (skaner)</h3>
                                        </div>
                                    </div>
                                </div>
                                <div className="px-6 py-4">
                                    {packingProducts.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">Brak pozycji z kodem EAN do spakowania.</p>
                                    ) : (
                                        <BarcodeScanner
                                            products={packingProducts}
                                            onProductScanned={(product, quantity) => handlePackItem(product.order_item_id, quantity)}
                                            enabled={packingEnabled}
                                        />
                                    )}
                                </div>
                            </div>

                            {/* Magazyn / dokumenty */}
                            <div className="bg-white rounded-lg shadow border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <ShieldCheck className="w-5 h-5 text-gray-500" />
                                        <h3 className="text-base font-medium text-gray-900">Magazyn</h3>
                                    </div>
                                </div>
                                <div className="px-6 py-4 space-y-4">
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={handleCreateReservation}
                                            className="px-3 py-2 text-sm bg-amber-500 text-white rounded-md hover:bg-amber-600"
                                        >
                                            Utwórz rezerwację
                                        </button>
                                        <button
                                            onClick={handleConvertReservationToWz}
                                            className="px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700"
                                        >
                                            WZ z rezerwacji
                                        </button>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="text-sm font-semibold text-gray-800">Dokumenty</div>
                                        {warehouseDocuments.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">Brak dokumentów dla tego zamówienia.</p>
                                        ) : (
                                            <ul className="space-y-2 text-sm">
                                                {warehouseDocuments.map((doc, idx) => (
                                                    <li key={idx} className="flex items-center justify-between border rounded px-3 py-2">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{doc.type}</span>
                                                            <span className="text-gray-600">ID: {doc.warehouse_document_id}</span>
                                                        </div>
                                                        {doc.created_at && (
                                                            <span className="text-xs text-gray-500">
                                                                {new Date(doc.created_at).toLocaleString('pl-PL')}
                                                            </span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </div>
                            </div>
                            {/* Informacje o zamówieniu */}
                            <div className="bg-white rounded-lg shadow">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                        <FileText className="w-5 h-5 text-gray-400 mr-2" />
                                        Informacje o zamówieniu
                                    </h3>
                                </div>
                                <div className="px-6 py-4 space-y-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <dt className="text-gray-500">Status:</dt>
                                            <dd className="mt-1">
                                                <StatusBadge 
                                                    status={currentOrder.status} 
                                                    onChange={handleStatusChange}
                                                    editable 
                                                />
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-gray-500">Źródło:</dt>
                                            <dd className="mt-1 font-medium">{currentOrder.integration?.name || 'Manualnie'}</dd>
                                        </div>
                                    </div>

                                    {currentOrder.customer_email && (
                                        <div className="flex items-center text-sm">
                                            <Mail className="w-4 h-4 text-gray-400 mr-2" />
                                            <a href={`mailto:${currentOrder.customer_email}`} className="text-blue-600 hover:text-blue-800">
                                                {currentOrder.customer_email}
                                            </a>
                                        </div>
                                    )}

                                    {currentOrder.customer_phone && (
                                        <div className="flex items-center text-sm">
                                            <Phone className="w-4 h-4 text-gray-400 mr-2" />
                                            <a href={`tel:${currentOrder.customer_phone}`} className="text-blue-600 hover:text-blue-800">
                                                {currentOrder.customer_phone}
                                            </a>
                                        </div>
                                    )}

                                    <div className="flex items-center text-sm">
                                        <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                                        <span>Złożone: {new Date(currentOrder.order_date).toLocaleString('pl-PL')}</span>
                                    </div>

                                    {currentOrder.notes && (
                                        <div className="mt-4 p-3 bg-yellow-50 rounded-md">
                                            <dt className="text-xs text-gray-500 uppercase tracking-wide">Uwagi:</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{currentOrder.notes}</dd>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Płatność */}
                            <PaymentInfo 
                                order={currentOrder}
                                onUpdate={(updates) => {
                                    router.put(`/orders/${currentOrder.id}/payment`, updates);
                                }}
                            />

                            {/* Wysyłka */}
                            <ShippingInfo 
                                order={currentOrder}
                                onUpdate={(updates) => {
                                    router.put(`/orders/${currentOrder.id}/shipping`, updates);
                                }}
                            />

                            {/* Adresy */}
                            <div className="space-y-4">
                                <AddressCard
                                    title="Adres dostawy"
                                    icon={<MapPin className="w-5 h-5" />}
                                    address={currentOrder.shipping_address}
                                    editable
                                />

                                <AddressCard
                                    title={
                                        <div className="flex items-center">
                                            Dane do faktury
                                            {currentOrder.is_company && (
                                                <span className="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                    FIRMA
                                                </span>
                                            )}
                                        </div>
                                    }
                                    icon={<FileText className="w-5 h-5" />}
                                    address={currentOrder.billing_address}
                                    editable
                                />

                                {currentOrder.pickup_point && (
                                    <AddressCard
                                        title="Odbiór w punkcie"
                                        icon={<Truck className="w-5 h-5" />}
                                        address={currentOrder.pickup_point}
                                        isPickupPoint
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
