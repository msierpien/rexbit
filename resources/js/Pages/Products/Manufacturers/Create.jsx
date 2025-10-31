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
    website: '',
    contacts: '',
};

function ManufacturersCreate() {
    const { errors } = usePage().props;
    const { data, setData, post, processing } = useForm(initialForm);

    const handleSubmit = (event) => {
        event.preventDefault();
        post('/manufacturers');
    };

    return (
        <>
            <Head title="Nowy producent" />
            <div className="mx-auto max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Dodaj producenta</CardTitle>
                        <CardDescription>
                            Przechowuj podstawowe informacje kontaktowe oraz adresy internetowe producentów.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form id="manufacturer-create-form" className="space-y-6" onSubmit={handleSubmit}>
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
                                    value={data.contacts}
                                    onChange={(event) => setData('contacts', event.target.value)}
                                    placeholder='{"email":"support@example.com","phone":"+48 123 456 789"}'
                                />
                                {errors.contacts && <p className="text-xs text-destructive">{errors.contacts}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Wprowadź dane w formacie JSON, aby przechowywać dodatkowe kontakty producenta.
                                </p>
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="gap-3">
                        <Button type="submit" form="manufacturer-create-form" disabled={processing}>
                            Zapisz producenta
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/manufacturers">Anuluj</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

ManufacturersCreate.layout = (page) => <DashboardLayout title="Nowy producent">{page}</DashboardLayout>;

export default ManufacturersCreate;
