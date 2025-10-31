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
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
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
                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                </button>
            ))}
        </nav>
    );
}

function ManufacturersIndex() {
    const { manufacturers, flash, can } = usePage().props;

    const handleDelete = (manufacturer) => {
        if (!confirm(`Usunąć producenta "${manufacturer.name}"?`)) {
            return;
        }

        router.delete(`/manufacturers/${manufacturer.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Producenci" />
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
                            <CardTitle>Producenci</CardTitle>
                            <CardDescription>
                                Dodawaj producentów, aby mapować produkty na wytwórców oraz tworzyć raporty.
                            </CardDescription>
                        </div>
                        {can?.create && (
                            <Button asChild>
                                <Link href="/manufacturers/create">Dodaj producenta</Link>
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
                                        <TableHead>Strona WWW</TableHead>
                                        <TableHead>Produkty</TableHead>
                                        <TableHead className="text-right">Akcje</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {manufacturers.data.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-center text-sm text-muted-foreground">
                                                Brak producentów. Dodaj pierwszego producenta, aby rozpocząć.
                                            </TableCell>
                                        </TableRow>
                                    )}

                                    {manufacturers.data.map((manufacturer) => (
                                        <TableRow key={manufacturer.id}>
                                            <TableCell className="font-medium text-foreground">
                                                {manufacturer.name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{manufacturer.slug}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                {manufacturer.website ? (
                                                    <a
                                                        href={manufacturer.website}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-sm text-primary underline underline-offset-2"
                                                    >
                                                        {manufacturer.website}
                                                    </a>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-sm font-medium">
                                                    {manufacturer.products_count}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/manufacturers/${manufacturer.id}/edit`}>
                                                            Edytuj
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDelete(manufacturer)}
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

                        <Pagination links={manufacturers.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ManufacturersIndex.layout = (page) => <DashboardLayout title="Producenci">{page}</DashboardLayout>;

export default ManufacturersIndex;
