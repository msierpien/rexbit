// AddressCard.jsx - Komponent wyświetlania adresów (jak w BaseLinker)

import React, { useState } from 'react';
import { Edit2, MapPin, Building, User } from 'lucide-react';

export default function AddressCard({ 
    title, 
    icon, 
    address, 
    editable = false, 
    isPickupPoint = false,
    onUpdate 
}) {
    const [isEditing, setIsEditing] = useState(false);
    const [editForm, setEditForm] = useState(address || {});

    const handleEdit = () => {
        setEditForm({ ...address });
        setIsEditing(true);
    };

    const handleSave = () => {
        if (onUpdate) {
            onUpdate(editForm);
        }
        setIsEditing(false);
    };

    const handleCancel = () => {
        setEditForm({ ...address });
        setIsEditing(false);
    };

    const renderReadOnlyAddress = () => {
        if (!address) {
            return (
                <div className="text-sm text-gray-500 italic">
                    Brak danych adresowych
                </div>
            );
        }

        return (
            <div className="space-y-2 text-sm">
                {address.name && (
                    <div className="font-medium text-gray-900 flex items-center">
                        <User className="w-4 h-4 text-gray-400 mr-2" />
                        {address.name}
                    </div>
                )}
                
                {address.company && (
                    <div className="text-gray-700 flex items-center">
                        <Building className="w-4 h-4 text-gray-400 mr-2" />
                        {address.company}
                        <span className="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                            FIRMA
                        </span>
                    </div>
                )}

                <div className="text-gray-700 flex items-start">
                    <MapPin className="w-4 h-4 text-gray-400 mr-2 mt-0.5 flex-shrink-0" />
                    <div>
                        {address.street && <div>{address.street}</div>}
                        <div>
                            {address.postal_code} {address.city}
                        </div>
                        {address.country && address.country !== 'PL' && (
                            <div>{address.country}</div>
                        )}
                    </div>
                </div>

                {address.phone && (
                    <div className="text-sm text-gray-600">
                        Tel: <a href={`tel:${address.phone}`} className="text-blue-600 hover:text-blue-800">
                            {address.phone}
                        </a>
                    </div>
                )}

                {address.email && (
                    <div className="text-sm text-gray-600">
                        Email: <a href={`mailto:${address.email}`} className="text-blue-600 hover:text-blue-800">
                            {address.email}
                        </a>
                    </div>
                )}

                {address.vat_id && (
                    <div className="text-sm text-gray-600">
                        NIP: {address.vat_id}
                    </div>
                )}

                {isPickupPoint && address.point_id && (
                    <div className="text-sm text-gray-600">
                        Punkt: {address.point_id}
                    </div>
                )}
            </div>
        );
    };

    const renderEditForm = () => (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Imię i nazwisko
                    </label>
                    <input
                        type="text"
                        value={editForm.name || ''}
                        onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Firma
                    </label>
                    <input
                        type="text"
                        value={editForm.company || ''}
                        onChange={(e) => setEditForm({ ...editForm, company: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                    Ulica i numer
                </label>
                <input
                    type="text"
                    value={editForm.street || ''}
                    onChange={(e) => setEditForm({ ...editForm, street: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Kod pocztowy
                    </label>
                    <input
                        type="text"
                        value={editForm.postal_code || ''}
                        onChange={(e) => setEditForm({ ...editForm, postal_code: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Miasto
                    </label>
                    <input
                        type="text"
                        value={editForm.city || ''}
                        onChange={(e) => setEditForm({ ...editForm, city: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Telefon
                    </label>
                    <input
                        type="tel"
                        value={editForm.phone || ''}
                        onChange={(e) => setEditForm({ ...editForm, phone: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input
                        type="email"
                        value={editForm.email || ''}
                        onChange={(e) => setEditForm({ ...editForm, email: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            {!isPickupPoint && (
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        NIP (opcjonalnie)
                    </label>
                    <input
                        type="text"
                        value={editForm.vat_id || ''}
                        onChange={(e) => setEditForm({ ...editForm, vat_id: e.target.value })}
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            )}

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

    return (
        <div className="bg-white rounded-lg shadow border">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div className="flex items-center">
                    <div className="text-gray-400 mr-2">
                        {icon}
                    </div>
                    <h3 className="text-sm font-medium text-gray-900">
                        {title}
                    </h3>
                </div>
                {editable && !isEditing && (
                    <button
                        onClick={handleEdit}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <Edit2 className="w-4 h-4" />
                    </button>
                )}
            </div>
            
            <div className="px-4 py-4">
                {isEditing ? renderEditForm() : renderReadOnlyAddress()}
            </div>
        </div>
    );
}