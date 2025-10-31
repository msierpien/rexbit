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

function CatalogsEdit() {
    const { catalog, errors, flash } = usePage().props;
    const { data, setData, put, processing } = useForm({
        name: catalog.name ?? '',
        slug: catalog.slug ?? '',
        description: catalog.description ?? '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        put(`/product-catalogs/${catalog.id}`);
    };

    return (
        <>
            <Head title={`Edycja katalogu: ${catalog.name}`} />
            <div className="mx-auto max-w-3xl space-y-6">
                {flash?.status && (
                    <Alert>
                        <AlertTitle>Sukces</AlertTitle>
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Edycja katalogu</CardTitle>
                        <CardDescription>
                            Aktualizuj informacje o katalogu oraz jego opis wykorzystywany w integracjach.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form id="catalog-edit-form" className="space-y-6" onSubmit={handleSubmit}>
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
                                <Label htmlFor="description">Opis</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    value={data.description ?? ''}
                                    onChange={(event) => setData('description', event.target.value)}
                                />
                                {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="gap-3">
                        <Button type="submit" form="catalog-edit-form" disabled={processing}>
                            Zapisz zmiany
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/product-catalogs">Wróć</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

CatalogsEdit.layout = (page) => <DashboardLayout title="Edycja katalogu">{page}</DashboardLayout>;

export default CatalogsEdit;
