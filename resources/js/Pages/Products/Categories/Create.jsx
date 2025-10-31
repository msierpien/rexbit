import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Input } from '@/components/ui/input.jsx';
import { Label } from '@/components/ui/label.jsx';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select.jsx';

const NONE_OPTION = '__none__';

export default function ProductCategoryCreate() {
    const { catalogs, categories, errors } = usePage().props;
    const { data, setData, post, processing } = useForm({
        name: '',
        slug: '',
        catalog_id: catalogs[0]?.id ?? '',
        parent_id: '',
        position: 0,
    });

    const filteredCategories = categories.filter((category) => !data.catalog_id || category.catalog_id === Number(data.catalog_id));

    const submit = (event) => {
        event.preventDefault();
        post('/product-categories', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Nowa kategoria" />
            <form
                onSubmit={submit}
                className="max-w-3xl space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
            >
                <div>
                    <h1 className="text-lg font-semibold text-gray-900">Dodaj kategorię</h1>
                    <p className="text-sm text-gray-500">Zgrupuj produkty w strukturze kategorii.</p>
                </div>

                <div className="grid grid-cols-1 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Nazwa</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            required
                        />
                        {errors?.name && <p className="text-xs text-red-600">{errors.name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="slug">Slug</Label>
                        <Input
                            id="slug"
                            value={data.slug}
                            onChange={(event) => setData('slug', event.target.value)}
                            placeholder="Pozostaw puste aby wygenerować automatycznie"
                        />
                        {errors?.slug && <p className="text-xs text-red-600">{errors.slug}</p>}
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Katalog</Label>
                            <Select
                                value={data.catalog_id ? String(data.catalog_id) : undefined}
                                onValueChange={(value) => setData('catalog_id', value)}
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
                            {errors?.catalog_id && <p className="text-xs text-red-600">{errors.catalog_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Kategoria nadrzędna</Label>
                            <Select
                                value={data.parent_id ? String(data.parent_id) : NONE_OPTION}
                                onValueChange={(value) => setData('parent_id', value === NONE_OPTION ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Brak" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE_OPTION}>Brak</SelectItem>
                                    {filteredCategories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors?.parent_id && <p className="text-xs text-red-600">{errors.parent_id}</p>}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="position">Pozycja</Label>
                        <Input
                            id="position"
                            type="number"
                            value={data.position}
                            onChange={(event) => setData('position', event.target.value)}
                        />
                        {errors?.position && <p className="text-xs text-red-600">{errors.position}</p>}
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        Zapisz kategorię
                    </Button>
                    <Button type="button" variant="ghost" asChild>
                        <Link href="/product-categories">Anuluj</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

ProductCategoryCreate.layout = (page) => (
    <DashboardLayout title="Nowa kategoria">{page}</DashboardLayout>
);
