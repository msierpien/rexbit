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
import { Badge } from '@/components/ui/badge.jsx';

export default function NotificationsIndex() {
    const { notifications } = usePage().props;

    return (
        <>
            <Head title="Powiadomienia" />
            <Card>
                <CardHeader>
                    <CardTitle>Ostatnie powiadomienia</CardTitle>
                    <CardDescription>
                        Importy produktów, statusy oraz inne zdarzenia systemowe z Twojego konta.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {notifications.length === 0 && (
                        <Alert>
                            <AlertTitle>Brak powiadomień</AlertTitle>
                            <AlertDescription>
                                Będziemy informować Cię o ważnych zdarzeniach, gdy tylko się pojawią.
                            </AlertDescription>
                        </Alert>
                    )}

                    {notifications.map((notification) => {
                        const variant = notification.status === 'error' ? 'destructive' : 'default';

                        return (
                            <Alert key={notification.id} variant={variant}>
                                <AlertTitle>{notification.message ?? 'Powiadomienie systemowe'}</AlertTitle>
                                <AlertDescription>
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        {notification.status && (
                                            <Badge
                                                variant={
                                                    notification.status === 'error' ? 'destructive' : 'secondary'
                                                }
                                            >
                                                {notification.status}
                                            </Badge>
                                        )}
                                        <span>{notification.created_at}</span>
                                    </div>
                                    {notification.data?.details && (
                                        <p className="mt-2 text-sm leading-relaxed text-foreground/80">
                                            {notification.data.details}
                                        </p>
                                    )}
                                </AlertDescription>
                            </Alert>
                        );
                    })}
                </CardContent>
            </Card>
        </>
    );
}

NotificationsIndex.layout = (page) => <DashboardLayout title="Powiadomienia">{page}</DashboardLayout>;
