import { Button } from '@/components/ui/button.jsx';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu.jsx';

export default function SelectionToolbar({
    selectedCount,
    totalProducts,
    selectionEmpty,
    onSelectPage,
    onDeselectPage,
    onSelectAllAcrossPages,
    onClearSelection,
    onBulkStatus,
    onBulkCategory,
    additionalActions = null,
}) {
    return (
        <div className="mb-4 flex flex-wrap items-center gap-3">
            <span className="text-sm text-gray-600">
                Wybrano <strong>{selectedCount}</strong> z {totalProducts} produktów
            </span>
            <div className="flex flex-wrap items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button type="button" variant="outline" size="sm">
                            Zarządzaj zaznaczeniem
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-52">
                        <DropdownMenuItem onSelect={onSelectPage}>Zaznacz bieżącą stronę</DropdownMenuItem>
                        <DropdownMenuItem onSelect={onDeselectPage}>Odznacz bieżącą stronę</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={onSelectAllAcrossPages}>
                            Zaznacz wszystkie wyniki
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={onClearSelection}>Wyczyść zaznaczenie</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button type="button" variant="outline" size="sm" onClick={onClearSelection} disabled={selectionEmpty}>
                    Wyczyść wszystko
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={selectionEmpty}
                    onClick={onBulkStatus}
                >
                    Zmień status
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={selectionEmpty}
                    onClick={onBulkCategory}
                >
                    Przypisz kategorię
                </Button>
                {additionalActions}
            </div>
        </div>
    );
}
