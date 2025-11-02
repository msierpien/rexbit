import { Head, Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import { Button } from '@/components/ui/button.jsx';
import DocumentStatusActions from '@/components/warehouse/document-status-actions.jsx';

function StatusBadge({ document }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold ${document.status_badge_class}`}>
            {document.status_label}
        </span>
    );
}

function DocumentTable({ documents }) {
    if (!documents.data.length) {
        return (
            <div className="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                Brak dokumentów magazynowych.
            </div>
        );
    }

    const handleDelete = (document) => {
        if (!document.can_be_deleted) {
            alert(document.deletion_block_reason || 'Nie można usunąć tego dokumentu.');
            return;
        }

        if (confirm('Usunąć dokument?')) {
            router.delete(`/warehouse/documents/${document.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th className="px-4 py-3">Numer</th>
                        <th className="px-4 py-3">Typ</th>
                        <th className="px-4 py-3">Magazyn</th>
                        <th className="px-4 py-3">Kontrahent</th>
                        <th className="px-4 py-3">Data</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3 text-right">Akcje</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {documents.data.map((document) => (
                        <tr key={document.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3 font-semibold text-gray-900">{document.number}</td>
                            <td className="px-4 py-3 text-gray-600">{document.type}</td>
                            <td className="px-4 py-3 text-gray-600">{document.warehouse?.name ?? 'Brak'}</td>
                            <td className="px-4 py-3 text-gray-600">{document.contractor?.name ?? '—'}</td>
                            <td className="px-4 py-3 text-gray-600">{document.issued_at}</td>
                            <td className="px-4 py-3">
                                <StatusBadge document={document} />
                            </td>
                            <td className="px-4 py-3 text-right">
                                <div className="flex justify-end gap-2">
                                    <DocumentStatusActions document={document} />
                                    
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        asChild
                                        disabled={!document.can_be_edited}
                                        title={!document.can_be_edited ? 'Tylko dokumenty robocze mogą być edytowane' : ''}
                                    >
                                        <Link href={`/warehouse/documents/${document.id}/edit`}>Edytuj</Link>
                                    </Button>
                                    <Button 
                                        variant="destructive" 
                                        size="sm" 
                                        onClick={() => handleDelete(document)}
                                        disabled={!document.can_be_deleted}
                                        title={document.deletion_block_reason || ''}
                                    >
                                        Usuń
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Pagination({ documents }) {
    if (!documents.meta?.links) {
        return null;
    }

    return (
        <nav className="mt-6 flex flex-wrap gap-2 text-sm">
            {documents.meta.links.map((link, index) => (
                <button
                    key={index}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })}
                    className={`rounded-md px-3 py-1.5 ${
                        link.active
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-100 disabled:text-gray-400'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}

function WarehouseDocumentsIndex() {
    const { documents, flash } = usePage().props;

    return (
        <>
            <Head title="Dokumenty magazynowe" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Dokumenty magazynowe</h2>
                        <p className="text-sm text-gray-500">Twórz dokumenty PZ, WZ oraz inne ruchy magazynowe.</p>
                    </div>
                    <Button asChild>
                        <Link href="/warehouse/documents/create">Nowy dokument</Link>
                    </Button>
                </div>

                {flash?.status && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.status}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                <DocumentTable documents={documents} />
                <Pagination documents={documents} />
            </div>
        </>
    );
}

WarehouseDocumentsIndex.layout = (page) => <DashboardLayout title="Dokumenty magazynowe">{page}</DashboardLayout>;

export default WarehouseDocumentsIndex;
