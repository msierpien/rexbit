import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button.jsx';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu.jsx';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.jsx';
import { Textarea } from '@/components/ui/textarea.jsx';
import { Archive, ArrowRightLeft, CheckCircle2, MoreHorizontal, XCircle } from 'lucide-react';

const actionMeta = {
    post: {
        icon: CheckCircle2,
        confirm: 'Czy na pewno chcesz zatwierdzić ten dokument?',
        successVariant: 'default',
    },
    cancel: {
        icon: XCircle,
        confirm: null,
        successVariant: 'destructive',
    },
    archive: {
        icon: Archive,
        confirm: 'Czy na pewno chcesz zarchiwizować ten dokument?',
        successVariant: 'outline',
    },
};

export default function DocumentStatusActions({ document }) {
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [cancelReason, setCancelReason] = useState('');

    const availableActions = useMemo(
        () => Object.entries(document.available_transitions || {}),
        [document.available_transitions]
    );

    if (!availableActions.length) {
        return null;
    }

    const routes = {
        post: `/warehouse/documents/${document.id}/post`,
        cancel: `/warehouse/documents/${document.id}/cancel`,
        archive: `/warehouse/documents/${document.id}/archive`,
    };

    const executeAction = (action, payload = {}) => {
        const meta = actionMeta[action];
        if (meta?.confirm && !window.confirm(meta.confirm)) {
            return;
        }

        router.post(routes[action], payload, {
            preserveScroll: true,
        });
    };

    const handleCancelConfirm = () => {
        executeAction('cancel', { reason: cancelReason });
        setCancelDialogOpen(false);
        setCancelReason('');
    };

    const handleActionSelect = (action) => {
        if (action === 'cancel') {
            setCancelDialogOpen(true);
        } else {
            executeAction(action);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        className="shrink-0"
                        onClick={(event) => event.stopPropagation()}
                        title="Zmień status dokumentu"
                    >
                        <MoreHorizontal className="size-4" />
                        <span className="sr-only">Zmień status dokumentu</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56" onClick={(event) => event.stopPropagation()}>
                    {availableActions.map(([action, label]) => {
                        const Icon = actionMeta[action]?.icon ?? ArrowRightLeft;
                        const variant = action === 'cancel' ? 'destructive' : 'default';

                        return (
                            <DropdownMenuItem
                                key={action}
                                variant={variant}
                                onSelect={(event) => {
                                    event.preventDefault();
                                    handleActionSelect(action);
                                }}
                                className="font-medium"
                            >
                                <Icon className="size-4" />
                                {label}
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <DialogContent className="max-w-lg" onInteractOutside={(event) => event.preventDefault()}>
                    <DialogHeader>
                        <DialogTitle>Anulowanie dokumentu</DialogTitle>
                        <DialogDescription>
                            Opcjonalnie podaj powód anulowania. Informacja zostanie zapisana w historii dokumentu.
                        </DialogDescription>
                    </DialogHeader>

                    <Textarea
                        value={cancelReason}
                        onChange={(event) => setCancelReason(event.target.value)}
                        placeholder="Powód anulowania (maksymalnie 500 znaków)"
                        maxLength={500}
                    />
                    <p className="text-xs text-muted-foreground">{cancelReason.length}/500 znaków</p>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => {
                                setCancelDialogOpen(false);
                                setCancelReason('');
                            }}
                        >
                            Zamknij
                        </Button>
                        <Button variant="destructive" type="button" onClick={handleCancelConfirm}>
                            Potwierdź anulowanie
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
