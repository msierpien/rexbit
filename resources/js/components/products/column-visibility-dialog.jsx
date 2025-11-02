import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog.jsx';
import { Button } from '@/components/ui/button.jsx';

export default function ColumnVisibilityDialog({
    open,
    onOpenChange,
    trigger,
    definitions,
    visibility,
    onToggle,
    onReset,
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Widoczne kolumny</DialogTitle>
                    <DialogDescription>
                        Wybierz, które kolumny mają być widoczne na liście produktów.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    {definitions.map((column) => {
                        const checked = visibility[column.key] !== false;
                        return (
                            <label
                                key={column.key}
                                className="flex items-center gap-3 rounded-lg border border-border px-3 py-2 hover:border-primary/60"
                            >
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary"
                                    checked={checked}
                                    onChange={(event) => onToggle(column.key, event.target.checked)}
                                />
                                <span className="text-sm font-medium text-foreground">{column.label}</span>
                            </label>
                        );
                    })}
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onReset}>
                        Przywróć domyślne
                    </Button>
                    <Button type="button" onClick={() => onOpenChange(false)}>
                        Zamknij
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
