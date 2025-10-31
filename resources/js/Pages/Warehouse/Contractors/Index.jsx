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
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table.jsx';
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

function ContractorsIndex() {
    const { contractors, flash } = usePage().props;

    const handleDelete = (contractor) => {
        if (!confirm(`Usunąć kontrahenta "${contractor.name}"?`)) {
            return;
        }

        router.delete(`/warehouse/contractors/${contractor.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Kontrahenci magazynu" />
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
                            <CardTitle>Kontrahenci</CardTitle>
                            <CardDescription>
                                Zarządzaj dostawcami i odbiorcami wykorzystywanymi w dokumentach magazynowych.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href="/warehouse/contractors/create">Dodaj kontrahenta</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nazwa</TableHead>
                                        <TableHead>NIP</TableHead>
                                        <TableHead>Kontakt</TableHead>
                                        <TableHead>Miasto</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead className="text-right">Akcje</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {contractors.data.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                                                Brak kontrahentów. Dodaj pierwszego kontrahenta, aby rozpocząć.
                                            </TableCell>
                                        </TableRow>
                                    )}

                                    {contractors.data.map((contractor) => (
                                        <TableRow key={contractor.id}>
                                            <TableCell className="font-medium text-foreground">{contractor.name}</TableCell>
                                            <TableCell>{contractor.tax_id ?? '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-col text-sm">
                                                    <span>{contractor.email ?? '—'}</span>
                                                    <span className="text-muted-foreground">{contractor.phone ?? '—'}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{contractor.city ?? '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {contractor.is_supplier && <Badge variant="outline">Dostawca</Badge>}
                                                    {contractor.is_customer && <Badge variant="outline">Odbiorca</Badge>}
                                                    {!contractor.is_supplier && !contractor.is_customer && (
                                                        <span className="text-xs text-muted-foreground">Brak</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/warehouse/contractors/${contractor.id}/edit`}>
                                                            Edytuj
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDelete(contractor)}
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

                        <Pagination links={contractors.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ContractorsIndex.layout = (page) => <DashboardLayout title="Kontrahenci magazynu">{page}</DashboardLayout>;

export default ContractorsIndex;
