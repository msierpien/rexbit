import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import MappingModal from '@/components/MappingModal.jsx';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Textarea } from '@/components/ui/textarea.jsx';
import { Checkbox } from '@/components/ui/checkbox.jsx';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';
import {
    RefreshCcw,
    Trash2,
    Plug,
    Upload,
    Play,
    Download,
    MapPin,
    CalendarClock,
    PackageCheck,
} from 'lucide-react';

function IntegrationSummaryCard({ integration, onTest, onDelete, onSyncInventory, testing, deleting, syncing }) {
    return (
        <Card className="border border-border shadow-sm">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-3">
                            <Plug className="size-5 text-blue-600" />
                            <CardTitle className="text-lg font-semibold text-foreground">
                                {integration.name}
                            </CardTitle>
                        </div>
                        <CardDescription className="mt-2">
                            Typ integracji: <span className="font-medium text-foreground">{integration.type_label}</span>
                        </CardDescription>
                    </div>
                    <Badge variant={integration.status_variant}>{integration.status_label}</Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <p className="text-muted-foreground">Ostatnia synchronizacja</p>
                    <p className="mt-1 font-medium text-foreground">
                        {integration.last_synced_human ?? 'Brak danych'}
                    </p>
                </div>
                <div>
                    <p className="text-muted-foreground">Utworzono</p>
                    <p className="mt-1 font-medium text-foreground">
                        {integration.timestamps?.created_at_human ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-muted-foreground">Ostatnia aktualizacja</p>
                    <p className="mt-1 font-medium text-foreground">
                        {integration.timestamps?.updated_at_human ?? '—'}
                    </p>
                </div>
            </CardContent>
            <CardFooter className="flex flex-wrap items-center justify-end gap-3">
                {integration.type === 'prestashop' && onSyncInventory && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onSyncInventory}
                        disabled={syncing}
                    >
                        <PackageCheck className={`mr-2 size-4 ${syncing ? 'animate-pulse' : ''}`} />
                        {syncing ? 'Synchronizuję...' : 'Synchronizuj stany'}
                    </Button>
                )}
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={onTest}
                    disabled={testing}
                >
                    <RefreshCcw className={`mr-2 size-4 ${testing ? 'animate-spin' : ''}`} />
                    {testing ? 'Testuję...' : 'Testuj połączenie'}
                </Button>
                {onDelete && (
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={onDelete}
                        disabled={deleting}
                    >
                        <Trash2 className="mr-2 size-4" />
                        {deleting ? 'Usuwanie...' : 'Usuń integrację'}
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

function ConfigForm({ integration, fields, errors }) {
    const initialConfig = fields.reduce((carry, field) => {
        const defaultValue =
            field.default ?? (field.type === 'checkbox' ? false : '');
        const currentValue = integration.config?.[field.name];

        return {
            ...carry,
            [field.name]:
                currentValue !== undefined ? currentValue : defaultValue,
        };
    }, {});

    const configForm = useForm({
        name: integration.name ?? '',
        description: integration.description ?? '',
        ...initialConfig,
    });

    useEffect(() => {
        if (
            configForm.data.inventory_sync_mode !== 'local_to_presta' &&
            configForm.data.primary_warehouse_id
        ) {
            configForm.setData('primary_warehouse_id', '');
        }
    }, [configForm.data.inventory_sync_mode]);

    const submit = (event) => {
        event.preventDefault();
        configForm.put(`/integrations/${integration.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <Card className="border border-border shadow-sm">
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>Konfiguracja</CardTitle>
                    <CardDescription>Zaktualizuj parametry połączenia z zewnętrznym systemem.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="space-y-2">
                        <label htmlFor="integration-name" className="text-sm font-medium text-foreground">
                            Nazwa integracji
                        </label>
                        <Input
                            id="integration-name"
                            value={configForm.data.name}
                            onChange={(event) => configForm.setData('name', event.target.value)}
                            required
                        />
                        {configForm.errors.name && (
                            <p className="text-xs text-red-600">{configForm.errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label htmlFor="integration-description" className="text-sm font-medium text-foreground">
                            Opis (opcjonalnie)
                        </label>
                        <Textarea
                            id="integration-description"
                            rows={3}
                            value={configForm.data.description ?? ''}
                            onChange={(event) => configForm.setData('description', event.target.value)}
                        />
                        {configForm.errors.description && (
                            <p className="text-xs text-red-600">{configForm.errors.description}</p>
                        )}
                    </div>

                    {fields.length > 0 && (
                        <div className="space-y-4">
                            {fields.map((field) => {
                                if (
                                    field.name === 'primary_warehouse_id' &&
                                    configForm.data.inventory_sync_mode !== 'local_to_presta'
                                ) {
                                    return null;
                                }

                                if (field.type === 'checkbox') {
                                    const checked = Boolean(configForm.data[field.name]);

                                    return (
                                        <div key={field.name} className="space-y-2">
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    id={`field-${field.name}`}
                                                    checked={checked}
                                                    onChange={(event) =>
                                                        configForm.setData(field.name, event.target.checked)
                                                    }
                                                />
                                                <label
                                                    htmlFor={`field-${field.name}`}
                                                    className="text-sm font-medium text-foreground"
                                                >
                                                    {field.label}
                                                </label>
                                            </div>
                                            {field.helper && (
                                                <p className="pl-7 text-xs text-muted-foreground">{field.helper}</p>
                                            )}
                                            {(configForm.errors[field.name] || errors?.[field.name]) && (
                                                <p className="pl-7 text-xs text-red-600">
                                                    {configForm.errors[field.name] ?? errors[field.name]}
                                                </p>
                                            )}
                                        </div>
                                    );
                                }

                                if (field.component === 'select' || field.type === 'select') {
                                    const options = field.options ?? [];

                                    return (
                                        <div key={field.name} className="space-y-2">
                                            <label
                                                htmlFor={`field-${field.name}`}
                                                className="text-sm font-medium text-foreground"
                                            >
                                                {field.label}
                                            </label>
                                            <Select
                                                value={configForm.data[field.name] ?? ''}
                                                onValueChange={(value) => configForm.setData(field.name, value)}
                                                disabled={field.disabled}
                                            >
                                                <SelectTrigger id={`field-${field.name}`}>
                                                    <SelectValue placeholder={field.placeholder ?? 'Wybierz...'} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {field.helper && (
                                                <p className="text-xs text-muted-foreground">{field.helper}</p>
                                            )}
                                            {(configForm.errors[field.name] || errors?.[field.name]) && (
                                                <p className="text-xs text-red-600">
                                                    {configForm.errors[field.name] ?? errors[field.name]}
                                                </p>
                                            )}
                                        </div>
                                    );
                                }

                                if (field.component === 'select' || field.type === 'select') {
                                    const options = field.options ?? [];
                                    const normalizedValue = configForm.data[field.name]
                                        ? String(configForm.data[field.name])
                                        : undefined;

                                    return (
                                        <div key={field.name} className="space-y-2">
                                            <label
                                                htmlFor={`field-${field.name}`}
                                                className="text-sm font-medium text-foreground"
                                            >
                                                {field.label}
                                            </label>
                                            <Select
                                                value={normalizedValue}
                                                onValueChange={(value) => configForm.setData(field.name, value)}
                                                disabled={field.disabled || options.length === 0}
                                            >
                                                <SelectTrigger id={`field-${field.name}`}>
                                                    <SelectValue placeholder={field.placeholder ?? 'Wybierz...'} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                    {options.length === 0 && (
                                                        <SelectItem value="__placeholder__" disabled>
                                                            Brak dostępnych opcji
                                                        </SelectItem>
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {field.helper && (
                                                <p className="text-xs text-muted-foreground">{field.helper}</p>
                                            )}
                                            {(configForm.errors[field.name] || errors?.[field.name]) && (
                                                <p className="text-xs text-red-600">
                                                    {configForm.errors[field.name] ?? errors[field.name]}
                                                </p>
                                            )}
                                        </div>
                                    );
                                }

                                return (
                                    <div key={field.name} className="space-y-2">
                                        <label
                                            htmlFor={`field-${field.name}`}
                                            className="text-sm font-medium text-foreground"
                                        >
                                            {field.label}
                                        </label>
                                        <Input
                                            id={`field-${field.name}`}
                                            type={field.type ?? 'text'}
                                            value={configForm.data[field.name] ?? ''}
                                            min={field.min}
                                            onChange={(event) => configForm.setData(field.name, event.target.value)}
                                            placeholder={field.placeholder}
                                            required={field.required}
                                        />
                                        {field.helper && (
                                            <p className="text-xs text-muted-foreground">{field.helper}</p>
                                        )}
                                        {(configForm.errors[field.name] || errors?.[field.name]) && (
                                            <p className="text-xs text-red-600">
                                                {configForm.errors[field.name] ?? errors[field.name]}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
                <CardFooter className="flex items-center justify-end gap-3">
                    <Button type="submit" disabled={configForm.processing}>
                        Zapisz konfigurację
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/integrations">Powrót do listy</Link>
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function ProfileCreateForm({ integrationId, catalogs, suppliers }) {
    const catalogOptions = catalogs ?? [];
    const supplierOptions = suppliers ?? [];

    const profileForm = useForm({
        name: '',
        resource_type: 'products',
        format: 'csv',
        source_type: 'file',
        catalog_id: catalogOptions[0]?.id ? String(catalogOptions[0].id) : '',
        new_catalog_name: '',
        source_file: null,
        source_url: '',
        delimiter: ';',
        has_header: true,
        is_active: true,
        fetch_mode: 'manual',
        fetch_interval_minutes: '',
        fetch_daily_at: '',
        fetch_cron_expression: '',
        options: {
            record_path: '',
            supplier_availability: {
                contractor_id: '',
                match_by: 'sku_or_ean',
                missing_behavior: 'skip',
                default_delivery_days: '3',
                sync_purchase_price: false,
            },
        },
    });

    profileForm.transform((data) => {
        const supplierSettings = {
            ...(data.options?.supplier_availability ?? {}),
        };

        if (supplierSettings.contractor_id === '') {
            supplierSettings.contractor_id = null;
        }

        supplierSettings.default_delivery_days = supplierSettings.default_delivery_days
            ? parseInt(supplierSettings.default_delivery_days, 10) || 0
            : 0;
        supplierSettings.sync_purchase_price = supplierSettings.sync_purchase_price ? 1 : 0;

        return {
            ...data,
            source_location: data.source_type === 'url' ? data.source_url : '',
            has_header: data.has_header ? 1 : 0,
            is_active: data.is_active ? 1 : 0,
            options: {
                ...data.options,
                supplier_availability: supplierSettings,
            },
        };
    });

    const isSupplierTask = profileForm.data.resource_type === 'supplier-availability';

    const submit = (event) => {
        event.preventDefault();
        profileForm.post(`/integrations/${integrationId}/import-profiles`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => profileForm.reset(),
        });
    };

    return (
        <Card className="border border-blue-200/80 bg-blue-50/60 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/30">
            <form onSubmit={submit} className="space-y-4">
                <CardHeader>
                    <CardTitle>Nowy profil importu</CardTitle>
                    <CardDescription>
                        Zdefiniuj źródło danych oraz docelowy katalog produktów. Mapowanie pól uzupełnisz po zapisie.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">Nazwa profilu</label>
                        <Input
                            value={profileForm.data.name}
                            onChange={(event) => profileForm.setData('name', event.target.value)}
                            required
                        />
                        {profileForm.errors.name && (
                            <p className="text-xs text-red-600">{profileForm.errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">Rodzaj importu</label>
                        <Select
                            value={profileForm.data.resource_type}
                            onValueChange={(value) => profileForm.setData('resource_type', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="products">Produkty i kategorie</SelectItem>
                                <SelectItem value="supplier-availability">Dostępność u dostawcy</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Format pliku</label>
                        <Select
                            value={profileForm.data.format}
                            onValueChange={(value) => profileForm.setData('format', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="csv">CSV</SelectItem>
                                <SelectItem value="xml">XML</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Źródło danych</label>
                        <Select
                            value={profileForm.data.source_type}
                            onValueChange={(value) => profileForm.setData('source_type', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="file">Plik (upload)</SelectItem>
                                <SelectItem value="url">Adres URL</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {!isSupplierTask && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Docelowy katalog</label>
                                <Select
                                    value={profileForm.data.catalog_id ? String(profileForm.data.catalog_id) : undefined}
                                    onValueChange={(value) => profileForm.setData('catalog_id', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Wybierz katalog" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {catalogOptions.map((catalog) => (
                                            <SelectItem key={catalog.id} value={String(catalog.id)}>
                                                {catalog.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {profileForm.errors.catalog_id && (
                                    <p className="text-xs text-red-600">{profileForm.errors.catalog_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Nowy katalog (opcjonalnie)</label>
                                <Input
                                    value={profileForm.data.new_catalog_name ?? ''}
                                    onChange={(event) => profileForm.setData('new_catalog_name', event.target.value)}
                                    placeholder="np. Import hurtownia"
                                />
                            </div>
                        </>
                    )}

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Plik źródłowy</label>
                        <Input
                            type="file"
                            accept=".csv,.xml,text/csv,application/xml"
                            onChange={(event) => profileForm.setData('source_file', event.target.files?.[0] ?? null)}
                        />
                        {profileForm.errors.source_file && (
                            <p className="text-xs text-red-600">{profileForm.errors.source_file}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Adres URL</label>
                        <Input
                            value={profileForm.data.source_url ?? ''}
                            onChange={(event) => profileForm.setData('source_url', event.target.value)}
                            placeholder="https://example.com/export.csv"
                        />
                        {profileForm.errors.source_url && (
                            <p className="text-xs text-red-600">{profileForm.errors.source_url}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Separator kolumn</label>
                        <Input
                            value={profileForm.data.delimiter ?? ''}
                            onChange={(event) => profileForm.setData('delimiter', event.target.value)}
                            placeholder="np. ;"
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            checked={profileForm.data.has_header}
                            onChange={(event) => profileForm.setData('has_header', event.target.checked)}
                        />
                        <span className="text-sm text-foreground">Plik zawiera nagłówek</span>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            checked={profileForm.data.is_active}
                            onChange={(event) => profileForm.setData('is_active', event.target.checked)}
                        />
                        <span className="text-sm text-foreground">Profil aktywny</span>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Harmonogram</label>
                        <Select
                            value={profileForm.data.fetch_mode ?? 'manual'}
                            onValueChange={(value) => profileForm.setData('fetch_mode', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="manual">Ręcznie</SelectItem>
                                <SelectItem value="interval">Co X minut</SelectItem>
                                <SelectItem value="daily">Codziennie o godzinie</SelectItem>
                                <SelectItem value="cron">Wyrażenie CRON</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Co ile minut</label>
                        <Input
                            type="number"
                            min={5}
                            value={profileForm.data.fetch_interval_minutes ?? ''}
                            onChange={(event) => profileForm.setData('fetch_interval_minutes', event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Godzina (HH:MM)</label>
                        <Input
                            type="time"
                            value={profileForm.data.fetch_daily_at ?? ''}
                            onChange={(event) => profileForm.setData('fetch_daily_at', event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Wyrażenie CRON</label>
                        <Input
                            value={profileForm.data.fetch_cron_expression ?? ''}
                            onChange={(event) => profileForm.setData('fetch_cron_expression', event.target.value)}
                            placeholder="np. 0 * * * *"
                        />
                    </div>

                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">XPath rekordu (XML)</label>
                        <Input
                            value={profileForm.data.options?.record_path ?? ''}
                            onChange={(event) =>
                                profileForm.setData('options', {
                                    ...profileForm.data.options,
                                    record_path: event.target.value,
                                })
                            }
                            placeholder="/products/product"
                        />
                    </div>
                </CardContent>
                <CardFooter className="flex items-center justify-between">
                    <div className="text-xs text-muted-foreground">
                        Mapa pól będzie dostępna po zapisaniu profilu importu.
                    </div>
                    <Button type="submit" disabled={profileForm.processing}>
                        <Upload className="mr-2 size-4" />
                        Dodaj profil importu
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

const NONE_OPTION = '__none__';

function ProfileCard({ integrationId, profile, catalogs, suppliers, mappingMeta }) {
    const [busyAction, setBusyAction] = useState(null);
    const [showMappingModal, setShowMappingModal] = useState(false);

    const updateForm = useForm({
        name: profile.name ?? '',
        resource_type: profile.resource_type ?? 'products',
        format: profile.format ?? 'csv',
        source_type: profile.source_type ?? 'file',
        catalog_id: profile.catalog_id ? String(profile.catalog_id) : '',
        new_catalog_name: '',
        source_file: null,
        source_url: profile.source_type === 'url' ? profile.source_location ?? '' : '',
        delimiter: profile.delimiter ?? ';',
        has_header: Boolean(profile.has_header),
        is_active: Boolean(profile.is_active),
        fetch_mode: profile.fetch_mode ?? 'manual',
        fetch_interval_minutes: profile.fetch_interval_minutes ?? '',
        fetch_daily_at: profile.fetch_daily_at ?? '',
        fetch_cron_expression: profile.fetch_cron_expression ?? '',
        options: {
            record_path: profile.options?.record_path ?? '',
            supplier_availability: {
                contractor_id: profile.options?.supplier_availability?.contractor_id
                    ? String(profile.options.supplier_availability.contractor_id)
                    : '',
                match_by: profile.options?.supplier_availability?.match_by ?? 'sku_or_ean',
                missing_behavior: profile.options?.supplier_availability?.missing_behavior ?? 'skip',
                default_delivery_days:
                    profile.options?.supplier_availability?.default_delivery_days ?? '',
                sync_purchase_price: Boolean(profile.options?.supplier_availability?.sync_purchase_price),
            },
        },
    });



    const submitUpdate = (event) => {
        event.preventDefault();

        updateForm.transform((data) => {
            const supplierSettings = {
                ...(data.options?.supplier_availability ?? {}),
            };

            if (supplierSettings.contractor_id === '' || supplierSettings.contractor_id === null) {
                supplierSettings.contractor_id = null;
            }

            supplierSettings.default_delivery_days = supplierSettings.default_delivery_days
                ? parseInt(supplierSettings.default_delivery_days, 10) || 0
                : 0;
            supplierSettings.sync_purchase_price = supplierSettings.sync_purchase_price ? 1 : 0;

            return {
                ...data,
                _method: 'PUT',
                source_location: data.source_type === 'url' ? data.source_url : '',
                has_header: data.has_header ? 1 : 0,
                is_active: data.is_active ? 1 : 0,
                options: {
                    ...data.options,
                    supplier_availability: supplierSettings,
                },
            };
        });

        updateForm.post(`/integrations/${integrationId}/import-profiles/${profile.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                updateForm.transform((data) => data);
            },
        });
    };

    const handleMappingSaved = () => {
        // Refresh page to show updated mappings
        router.visit(window.location.pathname, {
            preserveScroll: true,
        });
    };

    const triggerJobAction = (action) => {
        setBusyAction(action);
        router.post(`/integrations/${integrationId}/import-profiles/${profile.id}/${action}`, {}, {
            preserveScroll: true,
            onFinish: () => setBusyAction(null),
        });
    };

    const handleDelete = () => {
        if (!confirm('Czy na pewno chcesz usunąć ten profil importu?')) {
            return;
        }

        setBusyAction('delete');
        router.delete(`/integrations/${integrationId}/import-profiles/${profile.id}`, {
            preserveScroll: true,
            onFinish: () => setBusyAction(null),
        });
    };

    const runs = profile.runs ?? [];
    const supplierOptions = suppliers ?? [];
    const isSupplierTask = updateForm.data.resource_type === 'supplier-availability';
    const supplierSettings = updateForm.data.options?.supplier_availability ?? {};

    const updateSupplierOption = (key, value) => {
        updateForm.setData('options', {
            ...(updateForm.data.options ?? {}),
            supplier_availability: {
                ...supplierSettings,
                [key]: value,
            },
        });
    };

    return (
        <Card className="border border-border/80 shadow-sm">
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-3">
                    Profil importu: {profile.name}
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            {profile.resource_type === 'supplier-availability' ? 'Dostępność' : 'Produkty'}
                        </Badge>
                        <Badge variant="secondary">{profile.format?.toUpperCase()}</Badge>
                    </div>
                </CardTitle>
                <CardDescription>
                    Źródło: {profile.source_type === 'file' ? 'Plik' : 'Adres URL'} •{' '}
                    {profile.is_active ? 'Aktywne' : 'Wstrzymane'}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-8">
                <form onSubmit={submitUpdate} className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">Nazwa profilu</label>
                        <Input
                            value={updateForm.data.name}
                            onChange={(event) => updateForm.setData('name', event.target.value)}
                            required
                        />
                        {updateForm.errors.name && (
                            <p className="text-xs text-red-600">{updateForm.errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">Rodzaj importu</label>
                        <Select
                            value={updateForm.data.resource_type}
                            onValueChange={(value) => updateForm.setData('resource_type', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="products">Produkty i kategorie</SelectItem>
                                <SelectItem value="supplier-availability">Dostępność u dostawcy</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Format</label>
                        <Select
                            value={updateForm.data.format}
                            onValueChange={(value) => updateForm.setData('format', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="csv">CSV</SelectItem>
                                <SelectItem value="xml">XML</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Źródło</label>
                        <Select
                            value={updateForm.data.source_type}
                            onValueChange={(value) => updateForm.setData('source_type', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="file">Plik</SelectItem>
                                <SelectItem value="url">Adres URL</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {!isSupplierTask && (
                        <>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Katalog docelowy</label>
                                <Select
                                    value={updateForm.data.catalog_id ? String(updateForm.data.catalog_id) : undefined}
                                    onValueChange={(value) => updateForm.setData('catalog_id', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Wybierz katalog" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {catalogs.map((catalog) => (
                                            <SelectItem key={catalog.id} value={String(catalog.id)}>
                                                {catalog.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {updateForm.errors.catalog_id && (
                                    <p className="text-xs text-red-600">{updateForm.errors.catalog_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Nowy katalog (opcjonalnie)</label>
                                <Input
                                    value={updateForm.data.new_catalog_name ?? ''}
                                    onChange={(event) => updateForm.setData('new_catalog_name', event.target.value)}
                                />
                            </div>
                        </>
                    )}

                    {isSupplierTask && (
                        <div className="md:col-span-2 space-y-4 rounded-lg border border-dashed border-border/60 p-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Dostawca (opcjonalnie)</label>
                                    <Select
                                        value={
                                            supplierSettings.contractor_id
                                                ? String(supplierSettings.contractor_id)
                                                : '__any__'
                                        }
                                        onValueChange={(value) =>
                                            updateSupplierOption(
                                                'contractor_id',
                                                value === '__any__' ? '' : value
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Wybierz dostawcę" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__any__">Dowolny</SelectItem>
                                            {supplierOptions.map((supplier) => (
                                                <SelectItem key={supplier.id} value={String(supplier.id)}>
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Dopasowanie produktów</label>
                                    <Select
                                        value={supplierSettings.match_by ?? 'sku_or_ean'}
                                        onValueChange={(value) => updateSupplierOption('match_by', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sku_or_ean">SKU lub EAN</SelectItem>
                                            <SelectItem value="sku">Tylko SKU</SelectItem>
                                            <SelectItem value="ean">Tylko EAN</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Brak produktu w bazie</label>
                                    <Select
                                        value={supplierSettings.missing_behavior ?? 'skip'}
                                        onValueChange={(value) => updateSupplierOption('missing_behavior', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="skip">Pomiń wiersz i odnotuj</SelectItem>
                                            <SelectItem value="error">Zatrzymaj z błędem</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Domyślna liczba dni dostawy</label>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={supplierSettings.default_delivery_days ?? ''}
                                        onChange={(event) =>
                                            updateSupplierOption('default_delivery_days', event.target.value)
                                        }
                                    />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id={`sync-price-${profile.id}`}
                                        checked={Boolean(supplierSettings.sync_purchase_price)}
                                        onCheckedChange={(value) =>
                                            updateSupplierOption('sync_purchase_price', Boolean(value))
                                        }
                                    />
                                    <label
                                        htmlFor={`sync-price-${profile.id}`}
                                        className="text-sm font-medium text-foreground"
                                    >
                                        Aktualizuj cenę zakupu w produktach
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Nowy plik (opcjonalnie)</label>
                        <Input
                            type="file"
                            accept=".csv,.xml,text/csv,application/xml"
                            onChange={(event) => updateForm.setData('source_file', event.target.files?.[0] ?? null)}
                        />
                        {updateForm.errors.source_file && (
                            <p className="text-xs text-red-600">{updateForm.errors.source_file}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Adres URL</label>
                        <Input
                            value={updateForm.data.source_url ?? ''}
                            onChange={(event) => updateForm.setData('source_url', event.target.value)}
                        />
                        {updateForm.errors.source_url && (
                            <p className="text-xs text-red-600">{updateForm.errors.source_url}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Separator kolumn</label>
                        <Input
                            value={updateForm.data.delimiter ?? ''}
                            onChange={(event) => updateForm.setData('delimiter', event.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            checked={updateForm.data.has_header}
                            onChange={(event) => updateForm.setData('has_header', event.target.checked)}
                        />
                        <span className="text-sm text-foreground">Plik zawiera nagłówek</span>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            checked={updateForm.data.is_active}
                            onChange={(event) => updateForm.setData('is_active', event.target.checked)}
                        />
                        <span className="text-sm text-foreground">Profil aktywny</span>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Harmonogram</label>
                        <Select
                            value={updateForm.data.fetch_mode ?? 'manual'}
                            onValueChange={(value) => updateForm.setData('fetch_mode', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="manual">Ręcznie</SelectItem>
                                <SelectItem value="interval">Co X minut</SelectItem>
                                <SelectItem value="daily">Codziennie o godzinie</SelectItem>
                                <SelectItem value="cron">Wyrażenie CRON</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Co ile minut</label>
                        <Input
                            type="number"
                            min={5}
                            value={updateForm.data.fetch_interval_minutes ?? ''}
                            onChange={(event) => updateForm.setData('fetch_interval_minutes', event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Godzina (HH:MM)</label>
                        <Input
                            type="time"
                            value={updateForm.data.fetch_daily_at ?? ''}
                            onChange={(event) => updateForm.setData('fetch_daily_at', event.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-foreground">Wyrażenie CRON</label>
                        <Input
                            value={updateForm.data.fetch_cron_expression ?? ''}
                            onChange={(event) => updateForm.setData('fetch_cron_expression', event.target.value)}
                        />
                    </div>

                    <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-medium text-foreground">XPath rekordu (XML)</label>
                        <Input
                            value={updateForm.data.options?.record_path ?? ''}
                            onChange={(event) =>
                                updateForm.setData('options', {
                                    ...updateForm.data.options,
                                    record_path: event.target.value,
                                })
                            }
                        />
                    </div>

                    <div className="md:col-span-2 flex items-center justify-end gap-3">
                        <Button type="submit" disabled={updateForm.processing}>
                            <Download className="mr-2 size-4" />
                            Zapisz profil
                        </Button>
                    </div>
                </form>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <MapPin className="size-4 text-primary" />
                            <h3 className="text-sm font-semibold text-foreground">Mapowanie pól</h3>
                        </div>
                        <Button 
                            onClick={() => setShowMappingModal(true)}
                            variant="outline"
                            size="sm"
                            className="gap-2"
                        >
                            <MapPin className="size-4" />
                            {!profile.last_headers || profile.last_headers.length === 0 ? 
                                'Skonfiguruj mapowanie' : 
                                `Edytuj mapowanie (${(() => {
                                    const productCount = Object.values(profile.mappings?.product || {}).filter(v => v).length;
                                    const categoryCount = Object.values(profile.mappings?.category || {}).filter(v => v).length;
                                    const supplierCount = Object.values(profile.mappings?.supplier_availability || {}).filter(v => v).length;
                                    return productCount + categoryCount + supplierCount;
                                })()} pól)`
                            }
                        </Button>
                    </div>
                    
                    {!profile.last_headers || profile.last_headers.length === 0 ? (
                        <Alert>
                            <AlertTitle>Brak nagłówków</AlertTitle>
                            <AlertDescription>
                                Najpierw odśwież nagłówki, aby móc skonfigurować mapowanie pól.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="text-sm text-muted-foreground">
                            Dostępnych kolumn: {profile.last_headers.length} | 
                            Zmapowanych pól: {(() => {
                                const productCount = Object.values(profile.mappings?.product || {}).filter(v => v).length;
                                const categoryCount = Object.values(profile.mappings?.category || {}).filter(v => v).length;
                                const supplierCount = Object.values(profile.mappings?.supplier_availability || {}).filter(v => v).length;
                                return productCount + categoryCount + supplierCount;
                            })()}
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <CalendarClock className="size-4 text-primary" />
                        <h3 className="text-sm font-semibold text-foreground">Harmonogram i historia</h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => triggerJobAction('run')}
                            disabled={busyAction === 'run'}
                        >
                            <Play className="mr-2 size-4" />
                            {busyAction === 'run' ? 'Planowanie...' : 'Uruchom teraz'}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => triggerJobAction('refresh-headers')}
                            disabled={busyAction === 'refresh-headers'}
                        >
                            <RefreshCcw className={`mr-2 size-4 ${busyAction === 'refresh-headers' ? 'animate-spin' : ''}`} />
                            Odśwież nagłówki
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={busyAction === 'delete'}
                        >
                            <Trash2 className="mr-2 size-4" />
                            {busyAction === 'delete' ? 'Usuwanie...' : 'Usuń profil'}
                        </Button>
                    </div>

                    <div className="rounded-lg border border-dashed border-border/70 p-4">
                        {runs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Brak uruchomień profilu importu.</p>
                        ) : (
                            <div className="space-y-2 text-sm">
                                {runs.map((run) => (
                                    <div
                                        key={run.id}
                                        className="flex flex-col gap-1 rounded-md border border-border/60 bg-card/60 px-3 py-2 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Badge variant={run.status_variant}>{run.status}</Badge>
                                            <span>{run.created_at_human}</span>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {run.success_count}/{run.processed_count} • {run.message ?? '—'}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
            
            <MappingModal
                isOpen={showMappingModal}
                onClose={() => setShowMappingModal(false)}
                profile={profile}
                integrationId={integrationId}
                mappingMeta={mappingMeta}
                onSaved={handleMappingSaved}
            />
        </Card>
    );
}

function IntegrationsEdit() {
    const {
        integration,
        driver_fields: driverFields,
        supports_import_profiles: supportsImportProfiles,
        profiles,
        profile_meta: profileMeta,
        catalogs,
        suppliers,
        can,
        errors,
    } = usePage().props;

    const [testing, setTesting] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [syncing, setSyncing] = useState(false);

    const handleTest = () => {
        setTesting(true);
        router.post(
            `/integrations/${integration.id}/test`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            },
        );
    };

    const handleSyncInventory = () => {
        setSyncing(true);
        router.post(
            `/integrations/${integration.id}/sync-inventory`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    };

    const handleDelete = () => {
        if (!confirm('Czy na pewno chcesz trwale usunąć tę integrację?')) {
            return;
        }

        setDeleting(true);
        router.delete(`/integrations/${integration.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    };

    const mappingMeta = useMemo(
        () => ({
            product_fields: profileMeta?.product_fields ?? {},
            category_fields: profileMeta?.category_fields ?? {},
            supplier_fields: profileMeta?.supplier_fields ?? {},
        }),
        [profileMeta],
    );

    return (
        <>
            <Head title={`Integracja: ${integration.name}`} />

            <div className="space-y-6">
                <IntegrationSummaryCard
                    integration={integration}
                    onTest={handleTest}
                    onSyncInventory={integration.type === 'prestashop' ? handleSyncInventory : undefined}
                    onDelete={can?.delete ? handleDelete : undefined}
                    testing={testing}
                    syncing={syncing}
                    deleting={deleting}
                />

                <ConfigForm integration={integration} fields={driverFields ?? []} errors={errors ?? {}} />

                {supportsImportProfiles && (
                    <div className="space-y-6">
                        <ProfileCreateForm
                            integrationId={integration.id}
                            catalogs={catalogs ?? []}
                            suppliers={suppliers ?? []}
                        />

                        {profiles.length === 0 ? (
                            <Card className="border border-dashed border-border/80">
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    Nie utworzono jeszcze żadnych profili importu.
                                </CardContent>
                            </Card>
                        ) : (
                            profiles.map((profile) => (
                            <ProfileCard
                                key={profile.id}
                                integrationId={integration.id}
                                profile={profile}
                                catalogs={catalogs ?? []}
                                suppliers={suppliers ?? []}
                                mappingMeta={mappingMeta}
                            />
                            ))
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

IntegrationsEdit.layout = (page) => <DashboardLayout title="Edycja integracji">{page}</DashboardLayout>;

export default IntegrationsEdit;
