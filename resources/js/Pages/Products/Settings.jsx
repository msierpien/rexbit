import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout.jsx';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card.jsx';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert.jsx';
import { Button } from '@/components/ui/button.jsx';

function ProductsSettings() {
    const { suggestions } = usePage().props;

    return (
        <>
            <Head title="Ustawienia produktów" />
            <div className="space-y-6">
                <Alert>
                    <AlertTitle>W trakcie budowy</AlertTitle>
                    <AlertDescription>
                        Sekcja konfiguracji produktów jest przygotowywana. Poniżej znajdziesz propozycje elementów,
                        które dodamy w kolejnych iteracjach.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4 md:grid-cols-2">
                    {suggestions.map((suggestion) => (
                        <Card key={suggestion.title}>
                            <CardHeader>
                                <CardTitle>{suggestion.title}</CardTitle>
                                <CardDescription>{suggestion.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button variant="outline" size="sm" disabled>
                                    Wkrótce dostępne
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

ProductsSettings.layout = (page) => <DashboardLayout title="Ustawienia produktów">{page}</DashboardLayout>;

export default ProductsSettings;
