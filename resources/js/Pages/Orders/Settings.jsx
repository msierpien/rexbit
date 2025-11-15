import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Settings, Download, Play, RefreshCw, CheckCircle } from 'lucide-react';

export default function OrdersSettings() {
    const { integrations } = usePage().props;
    const [importing, setImporting] = useState({});
    const [importLogs, setImportLogs] = useState({});

    const handleToggleImport = (integrationId, enabled) => {
        router.put(`/integrations/${integrationId}`, {
            config: {
                order_import_enabled: enabled
            }
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Powiadomienie zostanie pokazane przez flash message
            }
        });
    };

    const handleRunImport = async (integrationId) => {
        setImporting(prev => ({ ...prev, [integrationId]: true }));
        
        try {
            const response = await fetch(`/orders/import/${integrationId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    limit: 50,
                    dry_run: false
                })
            });

            const result = await response.json();
            
            setImportLogs(prev => ({
                ...prev,
                [integrationId]: {
                    timestamp: new Date().toLocaleString('pl-PL'),
                    success: result.success,
                    imported: result.imported || 0,
                    errors: result.errors || 0,
                    message: result.message
                }
            }));

            if (result.success) {
                // Odśwież stronę zamówień jeśli jesteśmy tam
                if (window.location.pathname === '/orders') {
                    router.reload({ only: ['orders'] });
                }
            }
        } catch (error) {
            setImportLogs(prev => ({
                ...prev,
                [integrationId]: {
                    timestamp: new Date().toLocaleString('pl-PL'),
                    success: false,
                    message: `Błąd importu: ${error.message}`
                }
            }));
        } finally {
            setImporting(prev => ({ ...prev, [integrationId]: false }));
        }
    };

    const getIntegrationTypeLabel = (type) => {
        const types = {
            'prestashop': 'PrestaShop API',
            'prestashop-db': 'PrestaShop Database',
            'woocommerce': 'WooCommerce',
            'csv-xml-import': 'CSV/XML Import'
        };
        return types[type] || type;
    };

    const getIntegrationIcon = (type) => {
        switch (type) {
            case 'prestashop':
            case 'prestashop-db':
                return '🛍️';
            case 'woocommerce':
                return '🛒';
            case 'csv-xml-import':
                return '📁';
            default:
                return '🔗';
        }
    };

    return (
        <DashboardLayout>
            <Head title="Ustawienia zamówień" />

            <div className="max-w-6xl mx-auto p-6">
                <div className="mb-8">
                    <div className="flex items-center gap-3 mb-2">
                        <Settings className="h-8 w-8 text-blue-600" />
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                            Ustawienia zamówień
                        </h1>
                    </div>
                    <p className="text-gray-600 dark:text-gray-400">
                        Skonfiguruj import zamówień z różnych platform e-commerce
                    </p>
                </div>

                {/* Informacja o synchronizacji */}
                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                    <div className="flex items-start gap-3">
                        <CheckCircle className="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                        <div>
                            <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                                Ważne informacje o synchronizacji zamówień
                            </h3>
                            <ul className="mt-2 text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                <li>• <strong>Import jest jednokierunkowy:</strong> PrestaShop → RexBit</li>
                                <li>• <strong>Lokalne zmiany:</strong> Edycja i usuwanie zamówień wpływa tylko na lokalną bazę</li>
                                <li>• <strong>Źródłowe dane:</strong> Zamówienia w PrestaShop pozostają nietknięte</li>
                                <li>• <strong>Ponowny import:</strong> Może nadpisać lokalne zmiany</li>
                                <li>• <strong>Statusy:</strong> Mapowane z PrestaShop na lokalne statusy użytkownika</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Import Configuration */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 className="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <Download className="h-5 w-5" />
                            Konfiguracja importu zamówień
                        </h2>
                    </div>

                    <div className="p-6">
                        {integrations.length === 0 ? (
                            <div className="text-center py-8">
                                <div className="text-gray-400 mb-2">📭</div>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Brak aktywnych integracji. Dodaj integrację w sekcji "Integracje".
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {integrations.map((integration) => (
                                    <div
                                        key={integration.id}
                                        className="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="text-2xl">
                                                {getIntegrationIcon(integration.type)}
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                                    {integration.name}
                                                </h3>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    {getIntegrationTypeLabel(integration.type)}
                                                </p>
                                                {importLogs[integration.id] && (
                                                    <div className={`text-xs mt-1 ${
                                                        importLogs[integration.id].success 
                                                            ? 'text-green-600 dark:text-green-400' 
                                                            : 'text-red-600 dark:text-red-400'
                                                    }`}>
                                                        <span className="font-medium">
                                                            {importLogs[integration.id].timestamp}:
                                                        </span>{' '}
                                                        {importLogs[integration.id].success ? (
                                                            <>
                                                                ✅ Zaimportowano {importLogs[integration.id].imported} zamówień
                                                                {importLogs[integration.id].errors > 0 && 
                                                                    `, błędy: ${importLogs[integration.id].errors}`}
                                                            </>
                                                        ) : (
                                                            <>❌ {importLogs[integration.id].message}</>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            {/* Toggle Switch */}
                                            <label className="relative inline-flex items-center cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="sr-only peer"
                                                    checked={integration.order_import_enabled}
                                                    onChange={(e) => handleToggleImport(
                                                        integration.id, 
                                                        e.target.checked
                                                    )}
                                                />
                                                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                <span className="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                                                    {integration.order_import_enabled ? 'Włączony' : 'Wyłączony'}
                                                </span>
                                            </label>

                                            {/* Manual Import Button */}
                                            {integration.order_import_enabled && (
                                                <button
                                                    onClick={() => handleRunImport(integration.id)}
                                                    disabled={importing[integration.id]}
                                                    className="flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium transition-colors"
                                                >
                                                    {importing[integration.id] ? (
                                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Play className="h-4 w-4" />
                                                    )}
                                                    {importing[integration.id] ? 'Importuję...' : 'Uruchom import'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Import Info */}
                <div className="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div className="flex items-start gap-3">
                        <CheckCircle className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                        <div>
                            <h3 className="font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                Automatyczny import zamówień
                            </h3>
                            <div className="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                <p>• Import uruchamia się automatycznie co 5 minut dla włączonych integracji</p>
                                <p>• Importowane są tylko nowe lub zmodyfikowane zamówienia</p>
                                <p>• Możesz uruchomić import manualnie używając przycisku "Uruchom import"</p>
                                <p>• Użyj komendy: <code className="bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded">
                                    php artisan orders:import --dry-run
                                </code> do testowania</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}