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

export default function PaymentInfo({ payment, total, currency = 'PLN', onUpdate }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editForm, setEditForm] = useState(payment || {});

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('pl-PL', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const statusConfig = PAYMENT_STATUS_CONFIG[payment?.status || 'pending'];

    const handleEdit = () => {
        setEditForm({ ...payment });
        setIsEditing(true);
    };

    const handleSave = () => {
        if (onUpdate) {
            onUpdate(editForm);
        }
        setIsEditing(false);
    };

    const handleCancel = () => {
        setEditForm({ ...payment });
        setIsEditing(false);
    };

    const renderEditForm = () => (
        <div className="space-y-4">
            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Status płatności
                </label>
                <select
                    value={editForm.status || 'pending'}
                    onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
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
                    value={editForm.amount || ''}
                    onChange={(e) => setEditForm({ ...editForm, amount: parseFloat(e.target.value) })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Sposób płatności
                </label>
                <select
                    value={editForm.provider || ''}
                    onChange={(e) => setEditForm({ ...editForm, provider: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Wybierz sposób płatności</option>
                    <option value="card">Karta płatnicza</option>
                    <option value="bank_transfer">Przelew bankowy</option>
                    <option value="paypal">PayPal</option>
                    <option value="przelewy24">Przelewy24</option>
                    <option value="payu">PayU</option>
                    <option value="cash">Gotówka</option>
                    <option value="cash_on_delivery">Za pobraniem</option>
                </select>
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Data płatności
                </label>
                <input
                    type="datetime-local"
                    value={editForm.paid_at || ''}
                    onChange={(e) => setEditForm({ ...editForm, paid_at: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    ID transakcji
                </label>
                <input
                    type="text"
                    value={editForm.external_payment_id || ''}
                    onChange={(e) => setEditForm({ ...editForm, external_payment_id: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="ID transakcji w systemie płatności"
                />
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

    const renderReadOnlyInfo = () => (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Status:</span>
                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusConfig.color}`}>
                    {statusConfig.icon}
                    <span className="ml-1">{statusConfig.label}</span>
                </span>
            </div>

            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Do zapłaty:</span>
                <span className="text-sm font-semibold text-gray-900">
                    {formatCurrency(total)}
                </span>
            </div>

            {payment?.amount && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Zapłacone:</span>
                    <span className="text-sm font-medium text-green-700">
                        {formatCurrency(payment.amount)}
                    </span>
                </div>
            )}

            {payment?.amount && payment.amount < total && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Pozostało:</span>
                    <span className="text-sm font-medium text-red-700">
                        {formatCurrency(total - payment.amount)}
                    </span>
                </div>
            )}

            {payment?.provider && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Sposób:</span>
                    <span className="text-sm text-gray-900">
                        {payment.provider === 'card' && 'Karta płatnicza'}
                        {payment.provider === 'bank_transfer' && 'Przelew'}
                        {payment.provider === 'paypal' && 'PayPal'}
                        {payment.provider === 'przelewy24' && 'Przelewy24'}
                        {payment.provider === 'payu' && 'PayU'}
                        {payment.provider === 'cash' && 'Gotówka'}
                        {payment.provider === 'cash_on_delivery' && 'Za pobraniem'}
                    </span>
                </div>
            )}

            {payment?.paid_at && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Data płatności:</span>
                    <span className="text-sm text-gray-900">
                        {new Date(payment.paid_at).toLocaleString('pl-PL')}
                    </span>
                </div>
            )}

            {payment?.external_payment_id && (
                <div className="pt-2 border-t border-gray-100">
                    <div className="text-xs text-gray-500">
                        ID transakcji: {payment.external_payment_id}
                    </div>
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