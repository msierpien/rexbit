// ToastContainer.jsx - Kontener na powiadomienia toast

import React, { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import Toast from './Toast';

export default function ToastContainer() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);
    const [toastCounter, setToastCounter] = useState(0);

    useEffect(() => {
        // Handle Inertia flash messages
        if (flash) {
            const newToasts = [];
            
            if (flash.success) {
                newToasts.push({
                    id: toastCounter + 1,
                    message: flash.success,
                    type: 'success'
                });
            }
            
            if (flash.error) {
                newToasts.push({
                    id: toastCounter + 2,
                    message: flash.error,
                    type: 'error'
                });
            }
            
            if (flash.warning) {
                newToasts.push({
                    id: toastCounter + 3,
                    message: flash.warning,
                    type: 'warning'
                });
            }
            
            if (flash.info) {
                newToasts.push({
                    id: toastCounter + 4,
                    message: flash.info,
                    type: 'info'
                });
            }

            if (newToasts.length > 0) {
                setToasts(prev => [...prev, ...newToasts]);
                setToastCounter(prev => prev + newToasts.length);
            }
        }
    }, [flash]);

    const removeToast = (id) => {
        setToasts(prev => prev.filter(toast => toast.id !== id));
    };

    const addToast = (message, type = 'info', duration = 5000) => {
        const newToast = {
            id: toastCounter + 1,
            message,
            type,
            duration
        };
        
        setToasts(prev => [...prev, newToast]);
        setToastCounter(prev => prev + 1);
    };

    // Expose addToast method globally for programmatic usage
    useEffect(() => {
        window.showToast = addToast;
        return () => {
            delete window.showToast;
        };
    }, [toastCounter]);

    if (toasts.length === 0) return null;

    return (
        <div className="fixed top-4 right-4 z-50 space-y-2 max-w-sm">
            {toasts.map(toast => (
                <Toast
                    key={toast.id}
                    message={toast.message}
                    type={toast.type}
                    duration={toast.duration}
                    onClose={() => removeToast(toast.id)}
                />
            ))}
        </div>
    );
}