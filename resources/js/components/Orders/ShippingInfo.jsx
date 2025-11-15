// ShippingInfo.jsx - Komponent informacji o wysyłce

import React, { useState } from 'react';
import { Truck, Package, MapPin, Calendar, Edit2, ExternalLink } from 'lucide-react';

const SHIPPING_STATUS_CONFIG = {
    pending: {
        label: 'Oczekuje',
        color: 'bg-gray-100 text-gray-800'
    },
    processing: {
        label: 'W przygotowaniu',
        color: 'bg-blue-100 text-blue-800'
    },
    ready_for_pickup: {
        label: 'Gotowe do odbioru',
        color: 'bg-yellow-100 text-yellow-800'
    },
    picked_up: {
        label: 'Odebrane przez kuriera',
        color: 'bg-purple-100 text-purple-800'
    },
    in_transit: {
        label: 'W transporcie',
        color: 'bg-indigo-100 text-indigo-800'
    },
    out_for_delivery: {
        label: 'W doręczeniu',
        color: 'bg-orange-100 text-orange-800'
    },
    delivered: {
        label: 'Doręczone',
        color: 'bg-green-100 text-green-800'
    },
    delivery_failed: {
        label: 'Nieudane doręczenie',
        color: 'bg-red-100 text-red-800'
    }
};

const CARRIERS = {
    inpost: 'InPost',
    dpd: 'DPD',
    ups: 'UPS',
    fedex: 'FedEx',
    dhl: 'DHL',
    poczta_polska: 'Poczta Polska',
    gls: 'GLS',
    other: 'Inny'
};

export default function ShippingInfo({ shipping, onUpdate }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editForm, setEditForm] = useState(shipping || {});

    const statusConfig = SHIPPING_STATUS_CONFIG[shipping?.status || 'pending'];

    const handleEdit = () => {
        setEditForm({ ...shipping });
        setIsEditing(true);
    };

    const handleSave = () => {
        if (onUpdate) {
            onUpdate(editForm);
        }
        setIsEditing(false);
    };

    const handleCancel = () => {
        setEditForm({ ...shipping });
        setIsEditing(false);
    };

    const getTrackingUrl = (carrier, trackingNumber) => {
        if (!trackingNumber) return null;
        
        const urls = {
            inpost: `https://inpost.pl/sledzenie-przesylek?number=${trackingNumber}`,
            dpd: `https://tracktrace.dpd.com.pl/findPackage?q=${trackingNumber}`,
            ups: `https://www.ups.com/track?loc=pl_PL&tracknum=${trackingNumber}`,
            fedex: `https://www.fedex.com/fedextrack/?trknbr=${trackingNumber}`,
            dhl: `https://www.dhl.com/pl-pl/home/tracking.html?tracking-id=${trackingNumber}`,
            poczta_polska: `https://emonitoring.poczta-polska.pl/?numer=${trackingNumber}`,
            gls: `https://gls-group.eu/PL/pl/sledzenie-przesylki?match=${trackingNumber}`
        };

        return urls[carrier];
    };

    const renderEditForm = () => (
        <div className="space-y-4">
            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Status wysyłki
                </label>
                <select
                    value={editForm.status || 'pending'}
                    onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    {Object.entries(SHIPPING_STATUS_CONFIG).map(([status, config]) => (
                        <option key={status} value={status}>
                            {config.label}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Kurier
                </label>
                <select
                    value={editForm.carrier || ''}
                    onChange={(e) => setEditForm({ ...editForm, carrier: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Wybierz kuriera</option>
                    {Object.entries(CARRIERS).map(([key, name]) => (
                        <option key={key} value={key}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Usługa dostawy
                </label>
                <input
                    type="text"
                    value={editForm.service || ''}
                    onChange={(e) => setEditForm({ ...editForm, service: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="np. Paczkomaty 24/7, Kurier DPD Classic"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Numer przesyłki
                </label>
                <input
                    type="text"
                    value={editForm.tracking_number || ''}
                    onChange={(e) => setEditForm({ ...editForm, tracking_number: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Numer do śledzenia przesyłki"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Data wysyłki
                </label>
                <input
                    type="datetime-local"
                    value={editForm.shipped_at || ''}
                    onChange={(e) => setEditForm({ ...editForm, shipped_at: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Koszt wysyłki
                </label>
                <input
                    type="number"
                    step="0.01"
                    value={editForm.cost || ''}
                    onChange={(e) => setEditForm({ ...editForm, cost: parseFloat(e.target.value) })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                    {statusConfig.label}
                </span>
            </div>

            {shipping?.carrier && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Kurier:</span>
                    <span className="text-sm text-gray-900">
                        {CARRIERS[shipping.carrier] || shipping.carrier}
                    </span>
                </div>
            )}

            {shipping?.service && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Usługa:</span>
                    <span className="text-sm text-gray-900">{shipping.service}</span>
                </div>
            )}

            {shipping?.tracking_number && (
                <div className="space-y-1">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">Numer przesyłki:</span>
                        <span className="text-sm font-mono text-gray-900">
                            {shipping.tracking_number}
                        </span>
                    </div>
                    {getTrackingUrl(shipping.carrier, shipping.tracking_number) && (
                        <div className="flex justify-end">
                            <a
                                href={getTrackingUrl(shipping.carrier, shipping.tracking_number)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center text-xs text-blue-600 hover:text-blue-800"
                            >
                                Śledź przesyłkę
                                <ExternalLink className="w-3 h-3 ml-1" />
                            </a>
                        </div>
                    )}
                </div>
            )}

            {shipping?.shipped_at && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Data wysyłki:</span>
                    <span className="text-sm text-gray-900">
                        {new Date(shipping.shipped_at).toLocaleString('pl-PL')}
                    </span>
                </div>
            )}

            {shipping?.cost && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Koszt wysyłki:</span>
                    <span className="text-sm text-gray-900">
                        {new Intl.NumberFormat('pl-PL', {
                            style: 'currency',
                            currency: 'PLN'
                        }).format(shipping.cost)}
                    </span>
                </div>
            )}

            {!shipping?.carrier && !shipping?.tracking_number && (
                <div className="text-sm text-gray-500 italic">
                    Brak informacji o wysyłce
                </div>
            )}
        </div>
    );

    return (
        <div className="bg-white rounded-lg shadow border">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div className="flex items-center">
                    <Truck className="w-5 h-5 text-gray-400 mr-2" />
                    <h3 className="text-sm font-medium text-gray-900">Wysyłka</h3>
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