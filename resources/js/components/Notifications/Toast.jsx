// Toast.jsx - Komponent powiadomień toast

import React, { useState, useEffect } from 'react';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-react';

const TOAST_TYPES = {
    success: {
        icon: CheckCircle,
        bgColor: 'bg-green-50',
        borderColor: 'border-green-200',
        iconColor: 'text-green-400',
        textColor: 'text-green-800',
        buttonColor: 'text-green-500 hover:text-green-600'
    },
    error: {
        icon: XCircle,
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200',
        iconColor: 'text-red-400',
        textColor: 'text-red-800',
        buttonColor: 'text-red-500 hover:text-red-600'
    },
    warning: {
        icon: AlertTriangle,
        bgColor: 'bg-yellow-50',
        borderColor: 'border-yellow-200',
        iconColor: 'text-yellow-400',
        textColor: 'text-yellow-800',
        buttonColor: 'text-yellow-500 hover:text-yellow-600'
    },
    info: {
        icon: Info,
        bgColor: 'bg-blue-50',
        borderColor: 'border-blue-200',
        iconColor: 'text-blue-400',
        textColor: 'text-blue-800',
        buttonColor: 'text-blue-500 hover:text-blue-600'
    }
};

export default function Toast({ 
    message, 
    type = 'info', 
    duration = 5000, 
    onClose,
    show = true 
}) {
    const [visible, setVisible] = useState(show);
    const config = TOAST_TYPES[type] || TOAST_TYPES.info;
    const IconComponent = config.icon;

    useEffect(() => {
        if (show && duration > 0) {
            const timer = setTimeout(() => {
                handleClose();
            }, duration);

            return () => clearTimeout(timer);
        }
    }, [show, duration]);

    const handleClose = () => {
        setVisible(false);
        if (onClose) {
            setTimeout(onClose, 150); // Delay to allow animation
        }
    };

    if (!visible || !message) return null;

    return (
        <div className={`rounded-md p-4 border ${config.bgColor} ${config.borderColor} animate-in slide-in-from-top-2 duration-300`}>
            <div className="flex">
                <div className="flex-shrink-0">
                    <IconComponent className={`h-5 w-5 ${config.iconColor}`} />
                </div>
                <div className="ml-3">
                    <p className={`text-sm font-medium ${config.textColor}`}>
                        {message}
                    </p>
                </div>
                <div className="ml-auto pl-3">
                    <div className="-mx-1.5 -my-1.5">
                        <button
                            onClick={handleClose}
                            className={`inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2 ${config.buttonColor}`}
                        >
                            <span className="sr-only">Zamknij</span>
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}