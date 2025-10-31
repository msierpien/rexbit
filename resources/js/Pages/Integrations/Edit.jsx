import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
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
} from 'lucide-react';

function IntegrationSummaryCard({ integration, onTest, onDelete, testing, deleting }) {
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
    const initialConfig = fields.reduce(
        (carry, field) => ({
            ...carry,
            [field.name]: integration.config?.[field.name] ?? '',
        }),
        {},
    );

    const configForm = useForm({
        name: integration.name ?? '',
        description: integration.description ?? '',
        ...initialConfig,
    });

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
                            {fields.map((field) => (
                                <div key={field.name} className="space-y-2">
                                    <label htmlFor={`field-${field.name}`} className="text-sm font-medium text-foreground">
                                        {field.label}
                                    </label>
                                    <Input
                                        id={`field-${field.name}`}
                                        type={field.type ?? 'text'}
                                        value={configForm.data[field.name] ?? ''}
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
                            ))}
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

function ProfileCreateForm({ integrationId, catalogs }) {
    const catalogOptions = catalogs ?? [];

    const profileForm = useForm({
        name: '',
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
        },
    });

    profileForm.transform((data) => ({
        ...data,
        has_header: data.has_header ? 1 : 0,
        is_active: data.is_active ? 1 : 0,
    }));

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

function ProfileCard({ integrationId, profile, catalogs, mappingMeta }) {
    const [busyAction, setBusyAction] = useState(null);

    const updateForm = useForm({
        name: profile.name ?? '',
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
        },
    });

    updateForm.transform((data) => ({
        ...data,
        has_header: data.has_header ? 1 : 0,
        is_active: data.is_active ? 1 : 0,
    }));

    const mappingForm = useForm({
        product: mappingMeta.product_fields
            ? Object.fromEntries(
                  Object.keys(mappingMeta.product_fields).map((key) => [
                      key,
                      profile.mappings?.product?.[key] ?? '',
                  ]),
              )
            : {},
        category: mappingMeta.category_fields
            ? Object.fromEntries(
                  Object.keys(mappingMeta.category_fields).map((key) => [
                      key,
                      profile.mappings?.category?.[key] ?? '',
                  ]),
              )
            : {},
    });

    const submitUpdate = (event) => {
        event.preventDefault();
        updateForm.post(`/integrations/${integrationId}/import-profiles/${profile.id}`, {
            method: 'put',
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const submitMapping = (event) => {
        event.preventDefault();
        mappingForm.post(`/integrations/${integrationId}/import-profiles/${profile.id}/mappings`, {
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

    return (
        <Card className="border border-border/80 shadow-sm">
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-3">
                    Profil importu: {profile.name}
                    <Badge variant="secondary">{profile.format?.toUpperCase()}</Badge>
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
                    <div className="flex items-center gap-2">
                        <MapPin className="size-4 text-primary" />
                        <h3 className="text-sm font-semibold text-foreground">Mapowanie pól</h3>
                    </div>
                    {profile.last_headers?.length === 0 ? (
                        <Alert>
                            <AlertTitle>Brak nagłówków</AlertTitle>
                            <AlertDescription>
                                Odśwież nagłówki, aby przypisać kolumny pliku do pól produktów i kategorii.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <form onSubmit={submitMapping} className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-3">
                                <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Produkty
                                </h4>
                                {Object.entries(mappingMeta.product_fields).map(([target, label]) => (
                                    <div key={`product-${target}`} className="space-y-2">
                                        <label className="text-sm font-medium text-foreground">{label}</label>
                                        <Select
                                            value={mappingForm.data.product?.[target] || NONE_OPTION}
                                            onValueChange={(value) =>
                                                mappingForm.setData('product', {
                                                    ...mappingForm.data.product,
                                                    [target]: value === NONE_OPTION ? '' : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="— Pomiń —" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE_OPTION}>— Pomiń —</SelectItem>
                                                {profile.last_headers.map((header) => (
                                                    <SelectItem key={header} value={header}>
                                                        {header}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ))}
                            </div>
                            <div className="space-y-3">
                                <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Kategorie
                                </h4>
                                {Object.entries(mappingMeta.category_fields).map(([target, label]) => (
                                    <div key={`category-${target}`} className="space-y-2">
                                        <label className="text-sm font-medium text-foreground">{label}</label>
                                        <Select
                                            value={mappingForm.data.category?.[target] || NONE_OPTION}
                                            onValueChange={(value) =>
                                                mappingForm.setData('category', {
                                                    ...mappingForm.data.category,
                                                    [target]: value === NONE_OPTION ? '' : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="— Pomiń —" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE_OPTION}>— Pomiń —</SelectItem>
                                                {profile.last_headers.map((header) => (
                                                    <SelectItem key={header} value={header}>
                                                        {header}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ))}
                            </div>
                            <div className="md:col-span-2 flex items-center justify-end">
                                <Button type="submit" disabled={mappingForm.processing}>
                                    <MapPin className="mr-2 size-4" />
                                    Zapisz mapowanie
                                </Button>
                            </div>
                        </form>
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
        can,
        errors,
    } = usePage().props;

    const [testing, setTesting] = useState(false);
    const [deleting, setDeleting] = useState(false);

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
                    onDelete={can?.delete ? handleDelete : undefined}
                    testing={testing}
                    deleting={deleting}
                />

                <ConfigForm integration={integration} fields={driverFields ?? []} errors={errors ?? {}} />

                {supportsImportProfiles && (
                    <div className="space-y-6">
                        <ProfileCreateForm
                            integrationId={integration.id}
                            catalogs={catalogs ?? []}
                            productFields={mappingMeta.product_fields}
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
