// PaymentInfo.jsx - Komponent informacji o płatności

import React, { useState } from 'react';
import { CreditCard, DollarSign, Calendar, CheckCircle, XCircle, Clock, Edit2 } from 'lucide-react';

const PAYMENT_STATUS_CONFIG = {
    pending: {
        label: 'Oczekuje',
        color: 'bg-yellow-100 text-yellow-800',
        icon: <Clock className="w-3 h-3" />
    },
    partially_paid: {
        label: 'Częściowo opłacone',
        color: 'bg-orange-100 text-orange-800', 
        icon: <Clock className="w-3 h-3" />
    },
    paid: {
        label: 'Opłacone',
        color: 'bg-green-100 text-green-800',
        icon: <CheckCircle className="w-3 h-3" />
    },
    refunded: {
        label: 'Zwrócone',
        color: 'bg-red-100 text-red-800',
        icon: <XCircle className="w-3 h-3" />
    }
};

export default function PaymentInfo({ order, onUpdate }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editForm, setEditForm] = useState({
        payment_method: order?.payment_method || '',
        is_paid: order?.is_paid || false,
        total_paid: order?.total_paid || 0,
        payment_status: order?.payment_status || 'pending'
    });

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: order?.currency || 'PLN'
        }).format(amount);
    };

    // Determine payment status based on payment amount vs total
    const getPaymentStatus = () => {
        if (order?.is_paid) return 'paid';
        if (order?.total_paid > 0 && order?.total_paid < order?.total_gross) return 'partially_paid';
        return order?.payment_status || 'pending';
    };

    const statusConfig = PAYMENT_STATUS_CONFIG[getPaymentStatus()] || PAYMENT_STATUS_CONFIG.pending;

    const handleEdit = () => {
        setEditForm({
            payment_method: order?.payment_method || '',
            is_paid: order?.is_paid || false,
            total_paid: order?.total_paid || 0,
            payment_status: order?.payment_status || 'pending'
        });
        setIsEditing(true);
    };

    const handleSave = () => {
        if (onUpdate) {
            onUpdate(editForm);
        }
        setIsEditing(false);
    };

    const handleCancel = () => {
        setEditForm({
            payment_method: order?.payment_method || '',
            is_paid: order?.is_paid || false,
            total_paid: order?.total_paid || 0,
            payment_status: order?.payment_status || 'pending'
        });
        setIsEditing(false);
    };

    const renderEditForm = () => (
        <div className="space-y-4">
            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Status płatności
                </label>
                <select
                    value={editForm.payment_status || 'pending'}
                    onChange={(e) => setEditForm({ ...editForm, payment_status: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    {Object.entries(PAYMENT_STATUS_CONFIG).map(([status, config]) => (
                        <option key={status} value={status}>
                            {config.label}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Kwota zapłacona
                </label>
                <input
                    type="number"
                    step="0.01"
                    value={editForm.total_paid || ''}
                    onChange={(e) => setEditForm({ ...editForm, total_paid: parseFloat(e.target.value) })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Sposób płatności
                </label>
                <select
                    value={editForm.payment_method || ''}
                    onChange={(e) => setEditForm({ ...editForm, payment_method: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Wybierz sposób płatności</option>
                    <option value="card">Karta płatnicza</option>
                    <option value="bank_transfer">Przelew bankowy</option>
                    <option value="paypal">PayPal</option>
                    <option value="przelewy24">Przelewy24</option>
                    <option value="payu">PayU</option>
                    <option value="cash_on_delivery">Za pobraniem</option>
                    <option value="other">Inne</option>
                </select>
            </div>

            <div className="flex items-center">
                <input
                    type="checkbox"
                    id="is_paid"
                    checked={editForm.is_paid || false}
                    onChange={(e) => setEditForm({ ...editForm, is_paid: e.target.checked })}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label htmlFor="is_paid" className="ml-2 text-sm text-gray-700">
                    Oznacz jako opłacone
                </label>
            </div>

            <div className="flex justify-end space-x-2 pt-2">
                <button
                    onClick={handleCancel}
                    className="px-3 py-1 text-sm text-gray-600 hover:text-gray-800"
                >
                    Anuluj
                </button>
                <button
                    onClick={handleSave}
                    className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Zapisz
                </button>
            </div>
        </div>
    );

    const getPaymentMethodLabel = (method) => {
        const methods = {
            'card': 'Karta płatnicza',
            'bank_transfer': 'Przelew bankowy',
            'paypal': 'PayPal',
            'przelewy24': 'Przelewy24',
            'payu': 'PayU',
            'cash_on_delivery': 'Za pobraniem',
            'other': 'Inne'
        };
        return methods[method] || method;
    };

    const renderReadOnlyInfo = () => (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Status:</span>
                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusConfig.color}`}>
                    {statusConfig.icon}
                    <span className="ml-1">{statusConfig.label}</span>
                    {order?.is_paid && (
                        <CheckCircle className="w-3 h-3 ml-1" />
                    )}
                </span>
            </div>

            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Do zapłaty:</span>
                <span className="text-sm font-semibold text-gray-900">
                    {formatCurrency(order?.total_gross)}
                </span>
            </div>

            {order?.total_paid > 0 && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Zapłacone:</span>
                    <span className="text-sm font-medium text-green-700">
                        {formatCurrency(order.total_paid)}
                    </span>
                </div>
            )}

            {order?.total_paid > 0 && order.total_paid < order?.total_gross && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Pozostało:</span>
                    <span className="text-sm font-medium text-red-700">
                        {formatCurrency(order.total_gross - order.total_paid)}
                    </span>
                </div>
            )}

            {order?.payment_method && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Sposób:</span>
                    <span className="text-sm text-gray-900">
                        {getPaymentMethodLabel(order.payment_method)}
                    </span>
                </div>
            )}

            {order?.external_reference && (
                <div className="pt-2 border-t border-gray-100">
                    <div className="text-xs text-gray-500">
                        Ref. zewnętrzna: {order.external_reference}
                    </div>
                </div>
            )}

            {!order?.payment_method && !order?.total_paid && (
                <div className="text-sm text-gray-500 italic">
                    Brak informacji o płatności
                </div>
            )}
        </div>
    );

    return (
        <div className="bg-white rounded-lg shadow border">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div className="flex items-center">
                    <CreditCard className="w-5 h-5 text-gray-400 mr-2" />
                    <h3 className="text-sm font-medium text-gray-900">Płatność</h3>
                </div>
                {!isEditing && (
                    <button
                        onClick={handleEdit}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <Edit2 className="w-4 h-4" />
                    </button>
                )}
            </div>
            
            <div className="px-4 py-4">
                {isEditing ? renderEditForm() : renderReadOnlyInfo()}
            </div>
        </div>
    );
}