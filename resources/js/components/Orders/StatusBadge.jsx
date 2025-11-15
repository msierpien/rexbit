// StatusBadge.jsx - Komponent statusu zamówienia z możliwością zmiany

import React, { useState } from 'react';
import { ChevronDown, Check, Clock, Package, Truck, X, AlertTriangle } from 'lucide-react';

const STATUS_CONFIG = {
    draft: {
        label: 'Szkic',
        color: 'bg-gray-100 text-gray-800',
        icon: <Clock className="w-3 h-3" />
    },
    awaiting_payment: {
        label: 'Oczekuje płatność',
        color: 'bg-yellow-100 text-yellow-800',
        icon: <AlertTriangle className="w-3 h-3" />
    },
    paid: {
        label: 'Zapłacone',
        color: 'bg-green-100 text-green-800',
        icon: <Check className="w-3 h-3" />
    },
    awaiting_fulfillment: {
        label: 'Do realizacji',
        color: 'bg-blue-100 text-blue-800',
        icon: <Package className="w-3 h-3" />
    },
    picking: {
        label: 'Kompletacja',
        color: 'bg-purple-100 text-purple-800',
        icon: <Package className="w-3 h-3" />
    },
    ready_for_shipment: {
        label: 'Gotowe do wysyłki',
        color: 'bg-indigo-100 text-indigo-800',
        icon: <Truck className="w-3 h-3" />
    },
    shipped: {
        label: 'Wysłane',
        color: 'bg-blue-100 text-blue-800',
        icon: <Truck className="w-3 h-3" />
    },
    completed: {
        label: 'Zrealizowane',
        color: 'bg-green-100 text-green-800',
        icon: <Check className="w-3 h-3" />
    },
    cancelled: {
        label: 'Anulowane',
        color: 'bg-red-100 text-red-800',
        icon: <X className="w-3 h-3" />
    },
    returned: {
        label: 'Zwrócone',
        color: 'bg-orange-100 text-orange-800',
        icon: <AlertTriangle className="w-3 h-3" />
    }
};

export default function StatusBadge({ status, onChange, editable = false, size = 'default' }) {
    const [isOpen, setIsOpen] = useState(false);
    const config = STATUS_CONFIG[status] || STATUS_CONFIG.draft;

    const sizeClasses = {
        sm: 'px-2 py-1 text-xs',
        default: 'px-3 py-1 text-sm',
        lg: 'px-4 py-2 text-base'
    };

    const handleStatusChange = (newStatus) => {
        if (onChange) {
            onChange(newStatus);
        }
        setIsOpen(false);
    };

    if (!editable) {
        return (
            <span className={`inline-flex items-center rounded-full font-medium ${config.color} ${sizeClasses[size]}`}>
                {config.icon}
                <span className="ml-1">{config.label}</span>
            </span>
        );
    }

    return (
        <div className="relative">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className={`inline-flex items-center rounded-full font-medium transition-colors hover:opacity-80 ${config.color} ${sizeClasses[size]}`}
            >
                {config.icon}
                <span className="ml-1">{config.label}</span>
                <ChevronDown className="w-3 h-3 ml-1" />
            </button>

            {isOpen && (
                <div className="absolute top-full left-0 mt-1 w-56 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                    <div className="py-1">
                        {Object.entries(STATUS_CONFIG).map(([statusKey, statusConfig]) => (
                            <button
                                key={statusKey}
                                onClick={() => handleStatusChange(statusKey)}
                                className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center ${
                                    statusKey === status ? 'bg-blue-50 text-blue-700' : 'text-gray-700'
                                }`}
                            >
                                {statusConfig.icon}
                                <span className="ml-2">{statusConfig.label}</span>
                                {statusKey === status && <Check className="w-4 h-4 ml-auto" />}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {isOpen && (
                <div 
                    className="fixed inset-0 z-40" 
                    onClick={() => setIsOpen(false)}
                />
            )}
        </div>
    );
}