import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
import { Label } from '@/components/ui/label.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';

function ManufacturersEdit() {
    const { manufacturer, errors, flash } = usePage().props;
    const { data, setData, put, processing } = useForm({
        name: manufacturer.name ?? '',
        slug: manufacturer.slug ?? '',
        website: manufacturer.website ?? '',
        contacts: manufacturer.contacts ?? '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        put(`/manufacturers/${manufacturer.id}`);
    };

    return (
        <>
            <Head title={`Edycja producenta: ${manufacturer.name}`} />
            <div className="mx-auto max-w-3xl space-y-6">
                {flash?.status && (
                    <Alert>
                        <AlertTitle>Sukces</AlertTitle>
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Edycja producenta</CardTitle>
                        <CardDescription>
                            Uzupełnij dodatkowe informacje kontaktowe i adresy do komunikacji z producentem.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form id="manufacturer-edit-form" className="space-y-6" onSubmit={handleSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="name">Nazwa</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    value={data.slug}
                                    onChange={(event) => setData('slug', event.target.value)}
                                />
                                {errors.slug && <p className="text-xs text-destructive">{errors.slug}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="website">Strona WWW</Label>
                                <Input
                                    id="website"
                                    name="website"
                                    value={data.website}
                                    onChange={(event) => setData('website', event.target.value)}
                                    placeholder="https://example.com"
                                />
                                {errors.website && <p className="text-xs text-destructive">{errors.website}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="contacts">Dodatkowe kontakty (JSON)</Label>
                                <Textarea
                                    id="contacts"
                                    name="contacts"
                                    rows={4}
                                    value={data.contacts ?? ''}
                                    onChange={(event) => setData('contacts', event.target.value)}
                                />
                                {errors.contacts && <p className="text-xs text-destructive">{errors.contacts}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Przechowuj dodatkowe dane, takie jak adres e-mail opiekuna lub numer telefonu.
                                </p>
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="gap-3">
                        <Button type="submit" form="manufacturer-edit-form" disabled={processing}>
                            Zapisz zmiany
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/manufacturers">Wróć</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

ManufacturersEdit.layout = (page) => <DashboardLayout title="Edycja producenta">{page}</DashboardLayout>;

export default ManufacturersEdit;
