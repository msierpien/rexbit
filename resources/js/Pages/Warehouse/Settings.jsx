import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card.jsx';
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Checkbox } from '@/components/ui/checkbox.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';

const resetOptions = [
    { value: 'none', label: 'Brak' },
    { value: 'daily', label: 'Codziennie' },
    { value: 'monthly', label: 'Miesięcznie' },
    { value: 'yearly', label: 'Rocznie' },
];

function createDocumentSettings(documentTypes, serverDocumentSettings) {
    return Object.fromEntries(
        documentTypes.map((type) => [
            type,
            {
                prefix: serverDocumentSettings?.[type]?.prefix ?? '',
                suffix: serverDocumentSettings?.[type]?.suffix ?? '',
                next_number: serverDocumentSettings?.[type]?.next_number ?? 1,
                padding: serverDocumentSettings?.[type]?.padding ?? 4,
                reset_period: serverDocumentSettings?.[type]?.reset_period ?? 'none',
            },
        ]),
    );
}

function WarehouseSettings() {
    const { warehouses, document_types: documentTypes, document_settings: serverDocumentSettings, catalogs, flash, errors, errorBags } =
        usePage().props;

    const [isLocationModalOpen, setIsLocationModalOpen] = useState(false);

    const locationForm = useForm({
        location_name: '',
        location_code: '',
        location_is_default: false,
        location_strict_control: false,
        location_catalogs: [],
    });

    const documentForm = useForm({
        document_settings: createDocumentSettings(documentTypes, serverDocumentSettings),
    });

    useEffect(() => {
        documentForm.setData('document_settings', createDocumentSettings(documentTypes, serverDocumentSettings));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(serverDocumentSettings)]);

    const locationErrors = errorBags?.location ?? {};

    const openLocationModal = () => {
        locationForm.reset();
        locationForm.clearErrors();
        setIsLocationModalOpen(true);
    };

    const closeLocationModal = () => {
        setIsLocationModalOpen(false);
    };

    locationForm.transform((data) => ({
        ...data,
        location_is_default: data.location_is_default ? 1 : 0,
        location_strict_control: data.location_strict_control ? 1 : 0,
        location_catalogs: (data.location_catalogs ?? []).map((value) => Number(value)),
    }));

    const submitLocation = (event) => {
        event.preventDefault();

        locationForm.post('/warehouse/settings/locations', {
            preserveScroll: true,
            onSuccess: () => {
                closeLocationModal();
                locationForm.reset();
            },
        });
    };

    documentForm.transform((data) => {
        const transformed = Object.fromEntries(
            Object.entries(data.document_settings).map(([type, settings]) => [
                type,
                {
                    ...settings,
                    prefix: settings.prefix ?? '',
                    suffix: settings.suffix ?? '',
                    next_number: Math.max(1, Number(settings.next_number) || 1),
                    padding: Math.min(8, Math.max(1, Number(settings.padding) || 1)),
                    reset_period: settings.reset_period ?? 'none',
                },
            ]),
        );

        return {
            document_settings: transformed,
        };
    });

    const submitDocumentSettings = (event) => {
        event.preventDefault();

        documentForm.post('/warehouse/settings', {
            preserveScroll: true,
        });
    };

    const documentData = documentForm.data.document_settings;

    const documentErrors = useMemo(() => errors ?? {}, [errors]);

    return (
        <>
            <Head title="Ustawienia magazynu" />
            <div className="space-y-6">
                {flash?.status && (
                    <Alert>
                        <AlertTitle>Sukces</AlertTitle>
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Magazyny</CardTitle>
                            <CardDescription>Zarządzaj lokalizacjami magazynowymi i przypisaniem katalogów.</CardDescription>
                        </div>
                        <Button onClick={openLocationModal}>Dodaj magazyn</Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {warehouses.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Brak zdefiniowanych magazynów. Dodaj pierwszy magazyn, aby rozpocząć pracę.
                            </p>
                        )}

                        {warehouses.map((warehouse) => (
                            <div key={warehouse.id} className="rounded-xl border border-border bg-card p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-base font-semibold text-foreground">{warehouse.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Kod: {warehouse.code ?? 'brak'} • Domyślny:{' '}
                                            {warehouse.is_default ? 'tak' : 'nie'} • Ścisła kontrola:{' '}
                                            {warehouse.strict_control ? 'tak' : 'nie'}
                                        </p>
                                    </div>
                                    {warehouse.is_default && <Badge variant="secondary">Magazyn domyślny</Badge>}
                                </div>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {warehouse.catalogs.length > 0 ? (
                                        warehouse.catalogs.map((catalog) => (
                                            <Badge key={catalog.id} variant="outline">
                                                {catalog.name}
                                            </Badge>
                                        ))
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            Brak przypisanych katalogów (dostęp do wszystkich).
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Numeracja dokumentów</CardTitle>
                        <CardDescription>
                            Ustal prefiksy, sufiksy i sposób resetowania numeracji dla poszczególnych typów dokumentów.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitDocumentSettings} className="space-y-4">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Typ</TableHead>
                                            <TableHead>Prefiks</TableHead>
                                            <TableHead>Sufiks</TableHead>
                                            <TableHead>Kolejny numer</TableHead>
                                            <TableHead>Wiodące zera</TableHead>
                                            <TableHead>Reset numeracji</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {documentTypes.map((type) => {
                                            const prefixError = documentErrors?.[`document_settings.${type}.prefix`];
                                            const suffixError = documentErrors?.[`document_settings.${type}.suffix`];
                                            const nextNumberError = documentErrors?.[`document_settings.${type}.next_number`];
                                            const paddingError = documentErrors?.[`document_settings.${type}.padding`];
                                            const resetError = documentErrors?.[`document_settings.${type}.reset_period`];

                                            return (
                                                <TableRow key={type}>
                                                    <TableCell className="font-medium text-foreground">{type}</TableCell>
                                                    <TableCell>
                                                        <Input
                                                            value={documentData?.[type]?.prefix ?? ''}
                                                            onChange={(event) =>
                                                                documentForm.setData('document_settings', {
                                                                    ...documentData,
                                                                    [type]: {
                                                                        ...documentData[type],
                                                                        prefix: event.target.value,
                                                                    },
                                                                })
                                                            }
                                                            placeholder={`${type}/`}
                                                        />
                                                        {prefixError && (
                                                            <p className="mt-1 text-xs text-destructive">{prefixError}</p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            value={documentData?.[type]?.suffix ?? ''}
                                                            onChange={(event) =>
                                                                documentForm.setData('document_settings', {
                                                                    ...documentData,
                                                                    [type]: {
                                                                        ...documentData[type],
                                                                        suffix: event.target.value,
                                                                    },
                                                                })
                                                            }
                                                            placeholder="/{{year}}"
                                                        />
                                                        {suffixError && (
                                                            <p className="mt-1 text-xs text-destructive">{suffixError}</p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            value={documentData?.[type]?.next_number ?? 1}
                                                            onChange={(event) =>
                                                                documentForm.setData('document_settings', {
                                                                    ...documentData,
                                                                    [type]: {
                                                                        ...documentData[type],
                                                                        next_number: event.target.value,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                        {nextNumberError && (
                                                            <p className="mt-1 text-xs text-destructive">
                                                                {nextNumberError}
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            max={8}
                                                            value={documentData?.[type]?.padding ?? 4}
                                                            onChange={(event) =>
                                                                documentForm.setData('document_settings', {
                                                                    ...documentData,
                                                                    [type]: {
                                                                        ...documentData[type],
                                                                        padding: event.target.value,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                        {paddingError && (
                                                            <p className="mt-1 text-xs text-destructive">{paddingError}</p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <select
                                                            className="w-full rounded-md border border-input bg-background px-2 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                                            value={documentData?.[type]?.reset_period ?? 'none'}
                                                            onChange={(event) =>
                                                                documentForm.setData('document_settings', {
                                                                    ...documentData,
                                                                    [type]: {
                                                                        ...documentData[type],
                                                                        reset_period: event.target.value,
                                                                    },
                                                                })
                                                            }
                                                        >
                                                            {resetOptions.map((option) => (
                                                                <option key={option.value} value={option.value}>
                                                                    {option.label}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        {resetError && (
                                                            <p className="mt-1 text-xs text-destructive">{resetError}</p>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={documentForm.processing}>
                                    Zapisz ustawienia
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {isLocationModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                    <div className="w-full max-w-xl rounded-2xl border border-border bg-background p-6 shadow-lg">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold text-foreground">Dodaj magazyn</h2>
                                <p className="text-sm text-muted-foreground">
                                    Określ nazwę, kod oraz katalogi dostępne w tej lokalizacji.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="rounded-md p-2 text-muted-foreground transition hover:bg-muted"
                                onClick={closeLocationModal}
                                aria-label="Zamknij"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="h-4 w-4" fill="none">
                                    <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>

                        <form onSubmit={submitLocation} className="mt-6 space-y-4">
                            <div className="space-y-2">
                                <label htmlFor="location_name" className="text-sm font-medium text-foreground">
                                    Nazwa magazynu
                                </label>
                                <Input
                                    id="location_name"
                                    value={locationForm.data.location_name}
                                    onChange={(event) => locationForm.setData('location_name', event.target.value)}
                                    required
                                />
                                {locationErrors.location_name && (
                                    <p className="text-xs text-destructive">{locationErrors.location_name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label htmlFor="location_code" className="text-sm font-medium text-foreground">
                                    Kod
                                </label>
                                <Input
                                    id="location_code"
                                    value={locationForm.data.location_code ?? ''}
                                    onChange={(event) => locationForm.setData('location_code', event.target.value)}
                                    placeholder="np. MW-1"
                                />
                                {locationErrors.location_code && (
                                    <p className="text-xs text-destructive">{locationErrors.location_code}</p>
                                )}
                            </div>

                            <div className="space-y-3 rounded-lg border border-dashed border-border p-3">
                                <label className="flex items-center gap-3 text-sm text-foreground">
                                    <Checkbox
                                        checked={locationForm.data.location_is_default}
                                        onChange={(event) =>
                                            locationForm.setData('location_is_default', event.target.checked)
                                        }
                                    />
                                    <span>Ustaw jako domyślny magazyn</span>
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Włączenie tej opcji spowoduje zastąpienie obecnego magazynu domyślnego.
                                </p>
                            </div>

                            <div className="space-y-3 rounded-lg border border-dashed border-border p-3">
                                <label className="flex items-center gap-3 text-sm text-foreground">
                                    <Checkbox
                                        checked={locationForm.data.location_strict_control}
                                        onChange={(event) =>
                                            locationForm.setData('location_strict_control', event.target.checked)
                                        }
                                    />
                                    <span>Włącz ścisłą kontrolę stanów magazynowych</span>
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Każda zmiana stanu towaru będzie wymagała dokumentu magazynowego.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <label htmlFor="location_catalogs" className="text-sm font-medium text-foreground">
                                    Katalogi produktów
                                </label>
                                <select
                                    id="location_catalogs"
                                    multiple
                                    value={locationForm.data.location_catalogs.map(String)}
                                    onChange={(event) => {
                                        const selectedOptions = Array.from(event.target.selectedOptions).map((option) =>
                                            Number(option.value),
                                        );
                                        locationForm.setData('location_catalogs', selectedOptions);
                                    }}
                                    className="h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                >
                                    {catalogs.map((catalog) => (
                                        <option key={catalog.id} value={catalog.id}>
                                            {catalog.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    Pozostaw puste, jeśli magazyn ma mieć dostęp do wszystkich katalogów.
                                </p>
                                {locationErrors.location_catalogs && (
                                    <p className="text-xs text-destructive">{locationErrors.location_catalogs}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="ghost" onClick={closeLocationModal}>
                                    Anuluj
                                </Button>
                                <Button type="submit" disabled={locationForm.processing}>
                                    Zapisz magazyn
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

WarehouseSettings.layout = (page) => <DashboardLayout title="Ustawienia magazynu">{page}</DashboardLayout>;

export default WarehouseSettings;
