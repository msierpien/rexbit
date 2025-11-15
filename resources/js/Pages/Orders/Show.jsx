// OrderDetail.jsx - Komponent główny widoku zamówienia (inspirowany BaseLinker)

import React, { useState } from 'react';
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
    FileText
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import StatusBadge from '@/Components/Orders/StatusBadge';
import OrderItemsTable from '@/Components/Orders/OrderItemsTable';
import AddressCard from '@/Components/Orders/AddressCard';
import OrderTimeline from '@/Components/Orders/OrderTimeline';
import PaymentInfo from '@/Components/Orders/PaymentInfo';
import ShippingInfo from '@/Components/Orders/ShippingInfo';

export default function OrderDetail({ auth, order, breadcrumbs = [] }) {
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

    const handleStatusChange = (newStatus) => {
        router.put(`/orders/${order.id}/status`, {
            status: newStatus
        });
    };

    const formatCurrency = (amount, currency = 'PLN') => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

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
                            Zamówienie #{order.number}
                        </div>
                        <StatusBadge status={order.status} />
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
            <Head title={`Zamówienie #${order.number}`} />

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
                                    <span className="text-gray-800">#{order.number}</span>
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
                                            {order.items?.length || 0}
                                        </span>
                                    </div>
                                    <button className="flex items-center text-sm text-blue-600 hover:text-blue-800">
                                        <Plus className="w-4 h-4 mr-1" />
                                        Dodaj produkt
                                    </button>
                                </div>
                                
                                <OrderItemsTable 
                                    items={order.items || []} 
                                    currency={order.currency}
                                    onItemUpdate={(itemId, updates) => {
                                        router.put(`/orders/${order.id}/items/${itemId}`, updates);
                                    }}
                                />

                                {/* Podsumowanie finansowe */}
                                <div className="px-6 py-4 bg-gray-50 border-t">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-gray-600">Wartość netto:</span>
                                        <span className="font-medium">{formatCurrency(order.total_net, order.currency)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-sm mt-1">
                                        <span className="text-gray-600">VAT:</span>
                                        <span className="font-medium">{formatCurrency(order.total_gross - order.total_net, order.currency)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-base font-semibold mt-2 pt-2 border-t">
                                        <span>Razem brutto:</span>
                                        <span className="text-lg">{formatCurrency(order.total_gross, order.currency)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Timeline / Historia */}
                            <OrderTimeline 
                                history={order.status_history || []}
                                messages={order.messages || []}
                                expanded={expandedSections}
                                onToggle={toggleSection}
                            />
                        </div>

                        {/* Kolumna boczna - informacje */}
                        <div className="space-y-6">
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
                                                    status={order.status} 
                                                    onChange={handleStatusChange}
                                                    editable 
                                                />
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-gray-500">Źródło:</dt>
                                            <dd className="mt-1 font-medium">{order.integration?.name || 'Manualnie'}</dd>
                                        </div>
                                    </div>

                                    {order.customer_email && (
                                        <div className="flex items-center text-sm">
                                            <Mail className="w-4 h-4 text-gray-400 mr-2" />
                                            <a href={`mailto:${order.customer_email}`} className="text-blue-600 hover:text-blue-800">
                                                {order.customer_email}
                                            </a>
                                        </div>
                                    )}

                                    {order.customer_phone && (
                                        <div className="flex items-center text-sm">
                                            <Phone className="w-4 h-4 text-gray-400 mr-2" />
                                            <a href={`tel:${order.customer_phone}`} className="text-blue-600 hover:text-blue-800">
                                                {order.customer_phone}
                                            </a>
                                        </div>
                                    )}

                                    <div className="flex items-center text-sm">
                                        <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                                        <span>Złożone: {new Date(order.order_date).toLocaleString('pl-PL')}</span>
                                    </div>

                                    {order.notes && (
                                        <div className="mt-4 p-3 bg-yellow-50 rounded-md">
                                            <dt className="text-xs text-gray-500 uppercase tracking-wide">Uwagi:</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{order.notes}</dd>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Płatność */}
                            <PaymentInfo 
                                order={order}
                                onUpdate={(updates) => {
                                    router.put(`/orders/${order.id}/payment`, updates);
                                }}
                            />

                            {/* Wysyłka */}
                            <ShippingInfo 
                                order={order}
                                onUpdate={(updates) => {
                                    router.put(`/orders/${order.id}/shipping`, updates);
                                }}
                            />

                            {/* Adresy */}
                            <div className="space-y-4">
                                <AddressCard
                                    title="Adres dostawy"
                                    icon={<MapPin className="w-5 h-5" />}
                                    address={order.shipping_address}
                                    editable
                                />

                                <AddressCard
                                    title={
                                        <div className="flex items-center">
                                            Dane do faktury
                                            {order.is_company && (
                                                <span className="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                    FIRMA
                                                </span>
                                            )}
                                        </div>
                                    }
                                    icon={<FileText className="w-5 h-5" />}
                                    address={order.billing_address}
                                    editable
                                />

                                {order.pickup_point && (
                                    <AddressCard
                                        title="Odbiór w punkcie"
                                        icon={<Truck className="w-5 h-5" />}
                                        address={order.pickup_point}
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