import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';

function WarehouseDeliveriesIndex() {
    return (
        <>
            <Head title="Dostawy magazynowe" />
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 className="text-lg font-semibold text-gray-900">Dostawy magazynowe</h2>
                <p className="mt-2 text-sm text-gray-500">
                    Widok dostaw zostanie wypełniony po wdrożeniu modułu zarządzania dostawcami oraz importu danych. Na ten
                    moment możesz dokumentować dostawy za pomocą dokumentów PZ.
                </p>
            </div>
        </>
    );
}

WarehouseDeliveriesIndex.layout = (page) => <DashboardLayout title="Dostawy magazynowe">{page}</DashboardLayout>;

export default WarehouseDeliveriesIndex;
