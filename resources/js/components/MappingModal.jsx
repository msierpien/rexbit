import React, { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Save, X, RefreshCw } from 'lucide-react';

export default function MappingModal({ 
    isOpen, 
    onClose, 
    profile, 
    integrationId, 
    mappingMeta,
    onSaved 
}) {
    const [isLoading, setIsLoading] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        mappings: {
            product: {},
            category: {},
        }
    });

    // Inicjalizacja danych mappings z profile
    useEffect(() => {
        if (profile && isOpen) {
            const productMappings = Object.fromEntries(
                Object.keys(mappingMeta.product_fields || {}).map((key) => [
                    key,
                    profile.mappings?.product?.[key] || '',
                ])
            );
            
            const categoryMappings = Object.fromEntries(
                Object.keys(mappingMeta.category_fields || {}).map((key) => [
                    key,
                    profile.mappings?.category?.[key] || '',
                ])
            );

            setData('mappings', {
                product: productMappings,
                category: categoryMappings,
            });
        }
    }, [profile, isOpen, mappingMeta]);

    const handleSave = () => {
        post(`/integrations/${integrationId}/import-profiles/${profile.id}/mappings`, {
            preserveScroll: true,
            onSuccess: () => {
                onSaved?.();
                onClose();
            },
            onError: (errors) => {
                console.error('Błąd zapisywania mappings:', errors);
            }
        });
    };

    const handleMappingChange = (type, targetField, sourceField) => {
        setData('mappings', {
            ...data.mappings,
            [type]: {
                ...data.mappings[type],
                [targetField]: sourceField,
            }
        });
    };

    const clearMapping = (type, targetField) => {
        handleMappingChange(type, targetField, '');
    };

    const getMappedFieldsCount = () => {
        const productCount = Object.values(data.mappings.product || {}).filter(v => v !== '').length;
        const categoryCount = Object.values(data.mappings.category || {}).filter(v => v !== '').length;
        return productCount + categoryCount;
    };

    const getAvailableHeaders = () => {
        return profile?.last_headers || [];
    };

    if (!profile) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        Mapowanie pól - {profile.name}
                        <Badge variant="secondary">
                            {getMappedFieldsCount()} zmapowanych pól
                        </Badge>
                    </DialogTitle>
                    <DialogDescription>
                        Przypisz kolumny z pliku/API do pól produktów i kategorii.
                        Dostępne kolumny: {getAvailableHeaders().length}
                    </DialogDescription>
                </DialogHeader>

                {getAvailableHeaders().length === 0 ? (
                    <div className="text-center py-8">
                        <RefreshCw className="w-8 h-8 mx-auto text-muted-foreground mb-2" />
                        <p className="text-muted-foreground">
                            Brak dostępnych nagłówków. Najpierw odśwież nagłówki dla tego profilu.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Produkty */}
                        <div className="space-y-4">
                            <h3 className="font-semibold text-sm uppercase tracking-wide text-muted-foreground">
                                Pola produktów
                            </h3>
                            <div className="space-y-3 max-h-96 overflow-y-auto">
                                {Object.entries(mappingMeta.product_fields || {}).map(([targetField, label]) => (
                                    <div key={targetField} className="space-y-1">
                                        <Label className="text-xs font-medium">{label}</Label>
                                        <div className="flex gap-2">
                                            <Select
                                                value={data.mappings.product?.[targetField] || '__none__'}
                                                onValueChange={(value) => handleMappingChange('product', targetField, value === '__none__' ? '' : value)}
                                            >
                                                <SelectTrigger className="flex-1">
                                                    <SelectValue placeholder="Wybierz kolumnę..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">-- Nie mapuj --</SelectItem>
                                                    {getAvailableHeaders().map((header) => (
                                                        <SelectItem key={header} value={header}>
                                                            {header}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {data.mappings.product?.[targetField] && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => clearMapping('product', targetField)}
                                                >
                                                    <X className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                        {errors[`mappings.product.${targetField}`] && (
                                            <p className="text-xs text-red-600">
                                                {errors[`mappings.product.${targetField}`]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Kategorie */}
                        <div className="space-y-4">
                            <h3 className="font-semibold text-sm uppercase tracking-wide text-muted-foreground">
                                Pola kategorii
                            </h3>
                            <div className="space-y-3 max-h-96 overflow-y-auto">
                                {Object.entries(mappingMeta.category_fields || {}).map(([targetField, label]) => (
                                    <div key={targetField} className="space-y-1">
                                        <Label className="text-xs font-medium">{label}</Label>
                                        <div className="flex gap-2">
                                            <Select
                                                value={data.mappings.category?.[targetField] || '__none__'}
                                                onValueChange={(value) => handleMappingChange('category', targetField, value === '__none__' ? '' : value)}
                                            >
                                                <SelectTrigger className="flex-1">
                                                    <SelectValue placeholder="Wybierz kolumnę..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">-- Nie mapuj --</SelectItem>
                                                    {getAvailableHeaders().map((header) => (
                                                        <SelectItem key={header} value={header}>
                                                            {header}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {data.mappings.category?.[targetField] && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => clearMapping('category', targetField)}
                                                >
                                                    <X className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                        {errors[`mappings.category.${targetField}`] && (
                                            <p className="text-xs text-red-600">
                                                {errors[`mappings.category.${targetField}`]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Anuluj
                    </Button>
                    <Button 
                        onClick={handleSave} 
                        disabled={processing || getAvailableHeaders().length === 0}
                        className="gap-2"
                    >
                        {processing ? (
                            <RefreshCw className="w-4 h-4 animate-spin" />
                        ) : (
                            <Save className="w-4 h-4" />
                        )}
                        Zapisz mapowanie
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}