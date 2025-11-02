import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input.jsx';
import { Textarea } from '@/components/ui/textarea.jsx';
import { Checkbox } from '@/components/ui/checkbox.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';
import { Store, Layers3, ShieldCheck } from 'lucide-react';

const TYPE_ICONS = {
    store: Store,
    'file-stack': Layers3,
};

function TypeOption({ type, isActive, onSelect }) {
    const Icon = TYPE_ICONS[type.icon] ?? ShieldCheck;

    return (
        <button
            type="button"
            onClick={() => onSelect(type.value)}
            className={`w-full rounded-xl border p-5 text-left transition ${
                isActive
                    ? 'border-blue-500 bg-blue-50/70 shadow-sm'
                    : 'border-border bg-card hover:border-blue-200 hover:shadow-sm'
            }`}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <Icon className={`size-5 ${isActive ? 'text-blue-600' : 'text-muted-foreground'}`} />
                        <span className="text-base font-semibold text-foreground">{type.label}</span>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">{type.description}</p>
                </div>
                {type.capabilities?.import_profiles && <Badge variant="outline">Import CSV/XML</Badge>}
            </div>
        </button>
    );
}

function FieldInput({ field, value, onChange, error }) {
    if (field.type === 'checkbox') {
        const checked = Boolean(value);

        return (
            <div className="space-y-2">
                <div className="flex items-center gap-3">
                    <Checkbox
                        id={field.name}
                        checked={checked}
                        onChange={(event) => onChange(event.target.checked)}
                    />
                    <label htmlFor={field.name} className="text-sm font-medium text-foreground">
                        {field.label}
                    </label>
                </div>
                {field.helper && <p className="pl-7 text-xs text-muted-foreground">{field.helper}</p>}
                {error && <p className="pl-7 text-xs text-red-600">{error}</p>}
            </div>
        );
    }

    if (field.type === 'textarea') {
        return (
            <div className="space-y-2">
                <label htmlFor={field.name} className="text-sm font-medium text-foreground">
                    {field.label}
                </label>
                <Textarea
                    id={field.name}
                    rows={4}
                    value={value ?? ''}
                    onChange={(event) => onChange(event.target.value)}
                    required={field.required}
                    placeholder={field.placeholder}
                />
                {field.helper && <p className="text-xs text-muted-foreground">{field.helper}</p>}
                {error && <p className="text-xs text-red-600">{error}</p>}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <label htmlFor={field.name} className="text-sm font-medium text-foreground">
                {field.label}
            </label>
            <Input
                id={field.name}
                type={field.type ?? 'text'}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={field.required}
                placeholder={field.placeholder}
            />
            {field.helper && <p className="text-xs text-muted-foreground">{field.helper}</p>}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}

function IntegrationsCreate() {
    const { types, defaults, errors } = usePage().props;
    const initialType = defaults?.type ?? (types[0]?.value ?? '');
    const [currentType, setCurrentType] = useState(initialType);

    const typeMap = useMemo(() => Object.fromEntries(types.map((type) => [type.value, type])), [types]);

    const { data, setData, post, processing, clearErrors } = useForm({
        type: currentType,
        name: '',
        description: '',
        ...Object.fromEntries(
            Object.entries(defaults?.config?.[currentType] ?? {}).map(([key, value]) => [key, value ?? '']),
        ),
    });

    const activeType = typeMap[currentType] ?? types[0];

    useEffect(() => {
        const fields = (typeMap[currentType]?.fields ?? []).map((field) => field.name);

        setData((previous) => {
            const next = { ...previous, type: currentType };
            const defaultsForType = defaults?.config?.[currentType] ?? {};

            fields.forEach((fieldName) => {
                next[fieldName] = defaultsForType[fieldName] ?? '';
            });

            return next;
        });

        clearErrors();
    }, [currentType]);

    const activeFields = (activeType?.fields ?? []);

    const handleSubmit = (event) => {
        event.preventDefault();

        post('/integrations', {
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        const defaultsForType = defaults?.config?.[currentType] ?? {};
        const nextData = {
            type: currentType,
            name: '',
            description: '',
        };

        Object.keys(defaultsForType).forEach((key) => {
            nextData[key] = defaultsForType[key] ?? '';
        });

        setData(nextData);
        clearErrors();
    };

    return (
        <>
            <Head title="Nowa integracja" />
            <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                <div className="space-y-4">
                    <div>
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                            Wybierz typ integracji
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Typ określa dostępne funkcje i sposób konfiguracji połączenia.
                        </p>
                    </div>
                    <div className="space-y-3">
                        {types.map((type) => (
                            <TypeOption
                                key={type.value}
                                type={type}
                                isActive={type.value === currentType}
                                onSelect={setCurrentType}
                            />
                        ))}
                    </div>
                </div>

                <Card className="border border-border shadow-sm">
                    <form onSubmit={handleSubmit}>
                        <CardHeader>
                            <CardTitle>Konfiguracja integracji</CardTitle>
                            <CardDescription>
                                Wypełnij podstawowe dane i połącz system z RexBit.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="space-y-2">
                                <label htmlFor="name" className="text-sm font-medium text-foreground">
                                    Nazwa integracji
                                </label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                />
                                {errors?.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <label htmlFor="description" className="text-sm font-medium text-foreground">
                                    Opis (opcjonalnie)
                                </label>
                                <Textarea
                                    id="description"
                                    rows={3}
                                    value={data.description ?? ''}
                                    onChange={(event) => setData('description', event.target.value)}
                                />
                                {errors?.description && (
                                    <p className="text-xs text-red-600">{errors.description}</p>
                                )}
                            </div>

                            {activeFields.length > 0 ? (
                                <div className="space-y-5">
                                    {activeFields.map((field) => (
                            <FieldInput
                                key={field.name}
                                field={field}
                                value={data[field.name]}
                                onChange={(nextValue) => setData(field.name, nextValue)}
                                error={errors?.[field.name]}
                            />
                                    ))}
                                </div>
                            ) : (
                                <Alert>
                                    <AlertTitle>Brak dodatkowych ustawień</AlertTitle>
                                    <AlertDescription>
                                        Ten typ integracji nie wymaga dodatkowych parametrów. Po zapisaniu przejdź
                                        do profili importu, aby zdefiniować źródła danych i mapowanie.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                        <CardFooter className="flex items-center justify-between">
                            <div className="text-xs text-muted-foreground">
                                Wybrany typ: <span className="font-medium text-foreground">{activeType?.label}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <Button type="button" variant="ghost" onClick={handleReset}>
                                    Wyczyść
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Zapisz integrację
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href="/integrations">Anuluj</Link>
                                </Button>
                            </div>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

IntegrationsCreate.layout = (page) => <DashboardLayout title="Nowa integracja">{page}</DashboardLayout>;

export default IntegrationsCreate;
