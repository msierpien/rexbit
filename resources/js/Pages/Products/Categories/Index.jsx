import { Head, Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import { Badge } from '@/components/ui/badge.jsx';

function CategoryNode({ node }) {
    const handleDelete = () => {
        if (confirm('Usunąć tę kategorię?')) {
            router.delete(`/product-categories/${node.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="rounded-lg border border-gray-200 px-4 py-3">
            <div className="flex items-center justify-between">
                <div>
                    <p className="font-medium text-gray-900">{node.name}</p>
                    {node.children?.length > 0 && (
                        <p className="text-xs text-gray-500">{node.children.length} podkategorii</p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/product-categories/${node.id}/edit`}>Edytuj</Link>
                    </Button>
                    <Button variant="destructive" size="sm" onClick={handleDelete}>
                        Usuń
                    </Button>
                </div>
            </div>

            {node.children?.length > 0 && (
                <div className="mt-3 space-y-3 border-l border-dashed border-gray-200 pl-4">
                    {node.children.map((child) => (
                        <CategoryNode key={child.id} node={child} />
                    ))}
                </div>
            )}
        </div>
    );
}

function CategoryCatalog({ catalog }) {
    return (
        <section>
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">{catalog.name}</h3>
                <Badge variant="outline">{catalog.categories.length} kategorii</Badge>
            </div>

            {catalog.categories.length > 0 ? (
                <div className="space-y-3">
                    {catalog.categories.map((category) => (
                        <CategoryNode key={category.id} node={category} />
                    ))}
                </div>
            ) : (
                <p className="text-sm text-gray-500">Brak kategorii w tym katalogu.</p>
            )}
        </section>
    );
}

function ProductCategoriesIndex() {
    const { catalogs, flash } = usePage().props;

    return (
        <>
            <Head title="Kategorie produktów" />
            <div className="space-y-6">
                {flash?.status && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.status}
                    </div>
                )}

                <div className="flex flex-col justify-between gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 md:flex-row md:items-center">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Kategorie produktów</h2>
                        <p className="text-sm text-gray-500">Zarządzaj strukturą drzewa kategorii.</p>
                    </div>
                    <Button asChild>
                        <Link href="/product-categories/create">Dodaj kategorię</Link>
                    </Button>
                </div>

                <div className="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    {catalogs.length > 0 ? (
                        catalogs.map((catalog) => <CategoryCatalog key={catalog.id} catalog={catalog} />)
                    ) : (
                        <p className="text-sm text-gray-500">Brak kategorii. Dodaj pierwszą kategorię aby rozpocząć.</p>
                    )}
                </div>
            </div>
        </>
    );
}

ProductCategoriesIndex.layout = (page) => (
    <DashboardLayout title="Kategorie produktów">{page}</DashboardLayout>
);

export default ProductCategoriesIndex;
