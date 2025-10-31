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

const initialForm = {
    name: '',
    slug: '',
    description: '',
};

function CatalogsCreate() {
    const { errors } = usePage().props;
    const { data, setData, post, processing } = useForm(initialForm);

    const handleSubmit = (event) => {
        event.preventDefault();

        post('/product-catalogs');
    };

    return (
        <>
            <Head title="Nowy katalog produktów" />
            <div className="mx-auto max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Dodaj katalog produktów</CardTitle>
                        <CardDescription>
                            Twórz oddzielne katalogi dla różnych marek, krajów lub kanałów sprzedaży.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form id="catalog-create-form" className="space-y-6" onSubmit={handleSubmit}>
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
                                    placeholder="Pozostaw puste, aby wygenerować automatycznie"
                                />
                                {errors.slug && <p className="text-xs text-destructive">{errors.slug}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Opis</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    value={data.description}
                                    onChange={(event) => setData('description', event.target.value)}
                                />
                                {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="gap-3">
                        <Button type="submit" form="catalog-create-form" disabled={processing}>
                            Zapisz katalog
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/product-catalogs">Anuluj</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

CatalogsCreate.layout = (page) => <DashboardLayout title="Nowy katalog">{page}</DashboardLayout>;

export default CatalogsCreate;
