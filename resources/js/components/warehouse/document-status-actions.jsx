import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button.jsx';

function CancelModal({ isOpen, onClose, onConfirm }) {
    const [reason, setReason] = useState('');

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h3 className="mb-4 text-lg font-semibold text-gray-900">Anulowanie dokumentu</h3>
                
                <div className="mb-4">
                    <label className="mb-2 block text-sm font-medium text-gray-700">
                        Powód anulowania (opcjonalnie)
                    </label>
                    <textarea
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        rows="3"
                        placeholder="Podaj powód anulowania dokumentu..."
                        maxLength="500"
                    />
                    <p className="mt-1 text-xs text-gray-500">{reason.length}/500 znaków</p>
                </div>

                <div className="flex gap-2">
                    <Button 
                        variant="destructive" 
                        onClick={() => onConfirm(reason)}
                        className="flex-1"
                    >
                        Anuluj dokument
                    </Button>
                    <Button 
                        variant="outline" 
                        onClick={onClose}
                        className="flex-1"
                    >
                        Zamknij
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function DocumentStatusActions({ document }) {
    const [showCancelModal, setShowCancelModal] = useState(false);

    const handleStatusChange = (action, data = {}) => {
        const routes = {
            post: `/warehouse/documents/${document.id}/post`,
            cancel: `/warehouse/documents/${document.id}/cancel`, 
            archive: `/warehouse/documents/${document.id}/archive`,
        };

        router.post(routes[action], data, {
            preserveScroll: true,
        });
    };

    const handleCancel = (reason) => {
        handleStatusChange('cancel', { reason });
        setShowCancelModal(false);
    };

    const getActionButton = (action, label) => {
        const configs = {
            post: {
                variant: 'default',
                onClick: () => {
                    if (confirm('Czy na pewno chcesz zatwierdzić ten dokument?')) {
                        handleStatusChange('post');
                    }
                }
            },
            cancel: {
                variant: 'destructive',
                onClick: () => setShowCancelModal(true)
            },
            archive: {
                variant: 'outline',
                onClick: () => {
                    if (confirm('Czy na pewno chcesz zarchiwizować ten dokument?')) {
                        handleStatusChange('archive');
                    }
                }
            }
        };

        const config = configs[action];
        if (!config) return null;

        return (
            <Button
                key={action}
                variant={config.variant}
                size="sm"
                onClick={config.onClick}
            >
                {label}
            </Button>
        );
    };

    return (
        <>
            <div className="flex gap-2">
                {Object.entries(document.available_transitions || {}).map(([action, label]) => 
                    getActionButton(action, label)
                )}
            </div>

            <CancelModal
                isOpen={showCancelModal}
                onClose={() => setShowCancelModal(false)}
                onConfirm={handleCancel}
            />
        </>
    );
}