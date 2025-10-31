import { Head, Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card.jsx';
import {
    Table,
    TableHeader,
    TableHead,
    TableRow,
    TableBody,
    TableCell,
} from '@/components/ui/table.jsx';
import { Badge } from '@/components/ui/badge.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';

function Pagination({ links }) {
    if (!links) {
        return null;
    }

    return (
        <nav className="mt-6 flex flex-wrap items-center gap-2 text-sm">
            {links.map((link, index) => {
                const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');

                return (
                    <button
                        key={`${label}-${index}`}
                        type="button"
                        disabled={!link.url}
                        onClick={() =>
                            link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })
                        }
                        className={`rounded-md px-3 py-1.5 transition ${
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'border border-border bg-background text-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-50'
                        }`}
                    >
                        <span dangerouslySetInnerHTML={{ __html: label }} />
                    </button>
                );
            })}
        </nav>
    );
}

function CatalogsIndex() {
    const { catalogs, flash, can } = usePage().props;

    const handleDelete = (catalog) => {
        if (!confirm(`Usunąć katalog "${catalog.name}"?`)) {
            return;
        }

        router.delete(`/product-catalogs/${catalog.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Katalogi produktów" />
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
                            <CardTitle>Katalogi produktów</CardTitle>
                            <CardDescription>
                                Organizuj ofertę w niezależnych katalogach dla różnych kanałów sprzedaży.
                            </CardDescription>
                        </div>
                        {can?.create && (
                            <Button asChild>
                                <Link href="/product-catalogs/create">Dodaj katalog</Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nazwa</TableHead>
                                        <TableHead>Slug</TableHead>
                                        <TableHead>Produkty</TableHead>
                                        <TableHead className="text-right">Akcje</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {catalogs.data.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-center text-sm text-muted-foreground">
                                                Brak katalogów. Dodaj pierwszy katalog, aby rozpocząć pracę.
                                            </TableCell>
                                        </TableRow>
                                    )}

                                    {catalogs.data.map((catalog) => (
                                        <TableRow key={catalog.id}>
                                            <TableCell className="align-top">
                                                <div className="font-medium text-foreground">{catalog.name}</div>
                                                {catalog.description && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {catalog.description}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <Badge variant="outline">{catalog.slug}</Badge>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <span className="text-sm font-medium">{catalog.products_count}</span>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/product-catalogs/${catalog.id}/edit`}>Edytuj</Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDelete(catalog)}
                                                    >
                                                        Usuń
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={catalogs.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CatalogsIndex.layout = (page) => <DashboardLayout title="Katalogi produktów">{page}</DashboardLayout>;

export default CatalogsIndex;
