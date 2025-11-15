// OrderList.jsx - Lista zamówień (jak główna lista w BaseLinker)

import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Search, 
    Filter, 
    MoreVertical, 
    Eye, 
    Edit2, 
    Trash2, 
    Package, 
    User, 
    Calendar,
    DollarSign,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    RefreshCw
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import StatusBadge from '@/Components/Orders/StatusBadge';
import Pagination from '@/components/Pagination';

export default function OrderList({ auth, orders, filters, integrations = [] }) {
    const [selectedOrders, setSelectedOrders] = useState([]);
    const [showFilters, setShowFilters] = useState(false);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const formatCurrency = (amount, currency = 'PLN') => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/orders', { 
            ...filters, 
            search: searchTerm,
            page: 1 
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleFilterChange = (key, value) => {
        router.get('/orders', {
            ...filters,
            [key]: value,
            page: 1
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (column) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/orders', {
            ...filters,
            sort: column,
            direction: direction
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleSelectOrder = (orderId) => {
        setSelectedOrders(prev => 
            prev.includes(orderId) 
                ? prev.filter(id => id !== orderId)
                : [...prev, orderId]
        );
    };

    const handleSelectAll = () => {
        setSelectedOrders(
            selectedOrders.length === orders.data.length 
                ? [] 
                : orders.data.map(order => order.id)
        );
    };

    const handleBulkAction = (action) => {
        if (selectedOrders.length === 0) return;
        
        switch (action) {
            case 'delete':
                if (confirm('Czy na pewno chcesz usunąć zaznaczone zamówienia?\n\nUWAGA: Zamówienia zostaną usunięte tylko z lokalnej bazy danych.\nZamówienia w PrestaShop pozostaną nietknięte.')) {
                    router.delete('/orders/bulk', {
                        data: { order_ids: selectedOrders }
                    });
                }
                break;
            case 'export':
                router.post('/orders/export', {
                    order_ids: selectedOrders
                });
                break;
        }
    };

    const getSortIcon = (column) => {
        if (filters.sort !== column) return <ArrowUpDown className="w-4 h-4 text-gray-400" />;
        return filters.direction === 'asc' 
            ? <ArrowUpDown className="w-4 h-4 text-blue-600 rotate-180" />
            : <ArrowUpDown className="w-4 h-4 text-blue-600" />;
    };

    return (
        <DashboardLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Zamówienia
                    </h2>
                    <div className="flex items-center space-x-2">
                        <button
                            onClick={() => router.reload()}
                            className="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200"
                        >
                            <RefreshCw className="w-4 h-4 mr-1 inline" />
                            Odśwież
                        </button>
                        <Link
                            href="/orders/create"
                            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            Nowe zamówienie
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Zamówienia" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Filtry i wyszukiwanie */}
                    <div className="bg-white rounded-lg shadow mb-6">
                        <div className="px-6 py-4">
                            {/* Główny pasek wyszukiwania */}
                            <div className="flex items-center space-x-4 mb-4">
                                <form onSubmit={handleSearch} className="flex-1">
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                        <input
                                            type="text"
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="Szukaj po numerze, kliencie, email..."
                                            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </form>
                                <button
                                    onClick={() => setShowFilters(!showFilters)}
                                    className={`px-4 py-2 text-sm rounded-md border ${
                                        showFilters ? 'bg-blue-50 border-blue-300 text-blue-700' : 'bg-white border-gray-300 text-gray-700'
                                    }`}
                                >
                                    <Filter className="w-4 h-4 mr-2 inline" />
                                    Filtry
                                </button>
                            </div>

                            {/* Rozwinięte filtry */}
                            {showFilters && (
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t border-gray-200">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Status
                                        </label>
                                        <select
                                            value={filters.status || ''}
                                            onChange={(e) => handleFilterChange('status', e.target.value)}
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">Wszystkie statusy</option>
                                            <option value="draft">Szkic</option>
                                            <option value="awaiting_payment">Oczekuje płatność</option>
                                            <option value="paid">Zapłacone</option>
                                            <option value="awaiting_fulfillment">Do realizacji</option>
                                            <option value="shipped">Wysłane</option>
                                            <option value="completed">Zrealizowane</option>
                                            <option value="cancelled">Anulowane</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Integracja
                                        </label>
                                        <select
                                            value={filters.integration_id || ''}
                                            onChange={(e) => handleFilterChange('integration_id', e.target.value)}
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">Wszystkie źródła</option>
                                            <option value="manual">Manualnie</option>
                                            {integrations.map(integration => (
                                                <option key={integration.id} value={integration.id}>
                                                    {integration.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Data od
                                        </label>
                                        <input
                                            type="date"
                                            value={filters.date_from || ''}
                                            onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Data do
                                        </label>
                                        <input
                                            type="date"
                                            value={filters.date_to || ''}
                                            onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Akcje masowe */}
                    {selectedOrders.length > 0 && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-blue-700">
                                    Zaznaczonych zamówień: {selectedOrders.length}
                                </span>
                                <div className="flex items-center space-x-2">
                                    <button
                                        onClick={() => handleBulkAction('export')}
                                        className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                                    >
                                        Eksportuj
                                    </button>
                                    <button
                                        onClick={() => handleBulkAction('delete')}
                                        className="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700"
                                    >
                                        Usuń
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Tabela zamówień */}
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                checked={selectedOrders.length === orders.data.length && orders.data.length > 0}
                                                onChange={handleSelectAll}
                                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                            />
                                        </th>
                                        <th 
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('number')}
                                        >
                                            <div className="flex items-center">
                                                Zamówienie
                                                {getSortIcon('number')}
                                            </div>
                                        </th>
                                        <th 
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('customer_name')}
                                        >
                                            <div className="flex items-center">
                                                Klient
                                                {getSortIcon('customer_name')}
                                            </div>
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th 
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('total_gross')}
                                        >
                                            <div className="flex items-center">
                                                Wartość
                                                {getSortIcon('total_gross')}
                                            </div>
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Źródło
                                        </th>
                                        <th 
                                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onClick={() => handleSort('order_date')}
                                        >
                                            <div className="flex items-center">
                                                Data
                                                {getSortIcon('order_date')}
                                            </div>
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Akcje
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {orders.data.map((order) => (
                                        <tr 
                                            key={order.id} 
                                            className={`hover:bg-gray-50 ${selectedOrders.includes(order.id) ? 'bg-blue-50' : ''}`}
                                        >
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedOrders.includes(order.id)}
                                                    onChange={() => handleSelectOrder(order.id)}
                                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <Package className="w-5 h-5 text-gray-400 mr-3" />
                                                    <div>
                                                        <Link
                                                            href={`/orders/${order.id}`}
                                                            className="text-sm font-medium text-blue-600 hover:text-blue-900"
                                                        >
                                                            #{order.number}
                                                        </Link>
                                                        {order.external_order_id && (
                                                            <div className="text-xs text-gray-500">
                                                                Ext: {order.external_order_id}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <User className="w-4 h-4 text-gray-400 mr-2" />
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {order.customer_name || 'Brak danych'}
                                                        </div>
                                                        {order.customer_email && (
                                                            <div className="text-xs text-gray-500">
                                                                {order.customer_email}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <StatusBadge 
                                                    status={order.status} 
                                                    size="sm"
                                                />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <DollarSign className="w-4 h-4 text-gray-400 mr-1" />
                                                    <span className="text-sm font-semibold text-gray-900">
                                                        {formatCurrency(order.total_gross, order.currency)}
                                                    </span>
                                                    {order.is_paid && (
                                                        <span className="ml-2 inline-flex items-center px-1 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                            ✓
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-xs text-gray-500 flex items-center">
                                                    {order.items_count || 0} pozycji
                                                    {order.payment_method && (
                                                        <span className="ml-2 text-xs text-gray-400">
                                                            • {order.payment_method === 'cash_on_delivery' ? 'Pobranie' : 
                                                               order.payment_method === 'card' ? 'Karta' :
                                                               order.payment_method === 'bank_transfer' ? 'Przelew' : 
                                                               order.payment_method}
                                                        </span>
                                                    )}
                                                    {order.is_company && (
                                                        <span className="ml-2 px-1 py-0.5 text-xs bg-blue-100 text-blue-800 rounded">
                                                            FIRMA
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">
                                                    {order.integration?.name || 'Manualnie'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <Calendar className="w-4 h-4 text-gray-400 mr-1" />
                                                    <span className="text-sm text-gray-900">
                                                        {new Date(order.order_date).toLocaleDateString('pl-PL')}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {new Date(order.order_date).toLocaleTimeString('pl-PL', {
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    })}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex items-center justify-end space-x-2">
                                                    <Link
                                                        href={`/orders/${order.id}`}
                                                        className="text-blue-600 hover:text-blue-900"
                                                        title="Zobacz szczegóły"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </Link>
                                                    <Link
                                                        href={`/orders/${order.id}/edit`}
                                                        className="text-gray-600 hover:text-gray-900"
                                                        title="Edytuj"
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </Link>
                                                    <button
                                                        onClick={() => {
                                                            if (confirm('Czy na pewno chcesz usunąć to zamówienie?\n\nUWAGA: Zamówienie zostanie usunięte tylko z lokalnej bazy danych.\nZamówienie w PrestaShop pozostanie nietknięte.')) {
                                                                router.delete(`/orders/${order.id}`);
                                                            }
                                                        }}
                                                        className="text-red-600 hover:text-red-900"
                                                        title="Usuń"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Paginacja */}
                        {orders.data.length === 0 ? (
                            <div className="text-center py-12">
                                <Package className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                <h3 className="text-sm font-medium text-gray-900 mb-1">Brak zamówień</h3>
                                <p className="text-sm text-gray-500">
                                    Brak zamówień spełniających wybrane kryteria.
                                </p>
                            </div>
                        ) : (
                            <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                                <Pagination 
                                    data={orders} 
                                    preserveState={true}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}