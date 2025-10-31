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
import { Checkbox } from '@/components/ui/checkbox.jsx';

const initialForm = {
    name: '',
    tax_id: '',
    email: '',
    phone: '',
    city: '',
    street: '',
    postal_code: '',
    is_supplier: true,
    is_customer: false,
    meta: '',
};

function ContractorsCreate() {
    const { errors } = usePage().props;
    const contractorForm = useForm(initialForm);

    contractorForm.transform((data) => ({
        ...data,
        is_supplier: data.is_supplier ? 1 : 0,
        is_customer: data.is_customer ? 1 : 0,
    }));

    const handleSubmit = (event) => {
        event.preventDefault();
        contractorForm.post('/warehouse/contractors');
    };

    return (
        <>
            <Head title="Nowy kontrahent" />
            <div className="mx-auto max-w-4xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Dodaj kontrahenta</CardTitle>
                        <CardDescription>
                            Wprowadź dane kontaktowe i zaznacz, czy kontrahent jest dostawcą lub odbiorcą.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form id="contractor-create-form" className="grid gap-6 md:grid-cols-2" onSubmit={handleSubmit}>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="name">Nazwa</Label>
                                <Input
                                    id="name"
                                    value={contractorForm.data.name}
                                    onChange={(event) => contractorForm.setData('name', event.target.value)}
                                    required
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tax_id">NIP</Label>
                                <Input
                                    id="tax_id"
                                    value={contractorForm.data.tax_id}
                                    onChange={(event) => contractorForm.setData('tax_id', event.target.value)}
                                />
                                {errors.tax_id && <p className="text-xs text-destructive">{errors.tax_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={contractorForm.data.email}
                                    onChange={(event) => contractorForm.setData('email', event.target.value)}
                                />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone">Telefon</Label>
                                <Input
                                    id="phone"
                                    value={contractorForm.data.phone}
                                    onChange={(event) => contractorForm.setData('phone', event.target.value)}
                                />
                                {errors.phone && <p className="text-xs text-destructive">{errors.phone}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="city">Miasto</Label>
                                <Input
                                    id="city"
                                    value={contractorForm.data.city}
                                    onChange={(event) => contractorForm.setData('city', event.target.value)}
                                />
                                {errors.city && <p className="text-xs text-destructive">{errors.city}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="street">Ulica</Label>
                                <Input
                                    id="street"
                                    value={contractorForm.data.street}
                                    onChange={(event) => contractorForm.setData('street', event.target.value)}
                                />
                                {errors.street && <p className="text-xs text-destructive">{errors.street}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="postal_code">Kod pocztowy</Label>
                                <Input
                                    id="postal_code"
                                    value={contractorForm.data.postal_code}
                                    onChange={(event) => contractorForm.setData('postal_code', event.target.value)}
                                />
                                {errors.postal_code && (
                                    <p className="text-xs text-destructive">{errors.postal_code}</p>
                                )}
                            </div>

                            <div className="space-y-3 rounded-lg border border-dashed border-border p-3 md:col-span-2 md:flex md:flex-row md:items-start md:justify-between">
                                <label className="flex items-center gap-3 text-sm text-foreground">
                                    <Checkbox
                                        checked={contractorForm.data.is_supplier}
                                        onChange={(event) =>
                                            contractorForm.setData('is_supplier', event.target.checked)
                                        }
                                    />
                                    Dostawca
                                </label>

                                <label className="flex items-center gap-3 text-sm text-foreground">
                                    <Checkbox
                                        checked={contractorForm.data.is_customer}
                                        onChange={(event) =>
                                            contractorForm.setData('is_customer', event.target.checked)
                                        }
                                    />
                                    Odbiorca
                                </label>
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="meta">Dodatkowe dane (JSON)</Label>
                                <Textarea
                                    id="meta"
                                    rows={4}
                                    value={contractorForm.data.meta}
                                    onChange={(event) => contractorForm.setData('meta', event.target.value)}
                                    placeholder='{"notes":"Godziny dostaw 8-16"}'
                                />
                                {errors.meta && <p className="text-xs text-destructive">{errors.meta}</p>}
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="gap-3">
                        <Button type="submit" form="contractor-create-form" disabled={contractorForm.processing}>
                            Zapisz kontrahenta
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href="/warehouse/contractors">Anuluj</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

ContractorsCreate.layout = (page) => <DashboardLayout title="Nowy kontrahent">{page}</DashboardLayout>;

export default ContractorsCreate;
