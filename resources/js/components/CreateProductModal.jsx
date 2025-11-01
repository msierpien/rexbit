import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const NONE_OPTION = '__none__';

export default function CreateProductModal({
    open,
    onClose,
    catalogs = [],
    categories = [],
    manufacturers = [],
}) {
    const { errors = {} } = usePage().props;
    const [formData, setFormData] = useState({
        name: '',
        sku: '',
        ean: '',
        slug: '',
        catalog_id: '',
        category_id: '',
        manufacturer_id: '',
        description: '',
        images: '',
        purchase_price_net: '',
        purchase_vat_rate: '',
        sale_price_net: '',
        sale_vat_rate: '',
        status: 'active',
    });
    const [imagePreview, setImagePreview] = useState([]);

    useEffect(() => {
        if (formData.images) {
            const urls = formData.images
                .split(',')
                .map((url) => url.trim())
                .filter((url) => url.length > 0);

            setImagePreview(urls);
        } else {
            setImagePreview([]);
        }
    }, [formData.images]);

    const resetForm = () => {
        setFormData({
            name: '',
            sku: '',
            ean: '',
            slug: '',
            catalog_id: '',
            category_id: '',
            manufacturer_id: '',
            description: '',
            images: '',
            purchase_price_net: '',
            purchase_vat_rate: '',
            sale_price_net: '',
            sale_vat_rate: '',
            status: 'active',
        });
        setImagePreview([]);
    };

    useEffect(() => {
        if (open && !formData.catalog_id && catalogs.length > 0) {
            setFormData((prev) => ({ ...prev, catalog_id: catalogs[0].id.toString() }));
        }
    }, [open, catalogs, formData.catalog_id]);

    const filteredCategories = useMemo(
        () =>
            categories.filter(
                (category) => !formData.catalog_id || Number(category.catalog_id) === Number(formData.catalog_id),
            ),
        [categories, formData.catalog_id],
    );

    useEffect(() => {
        if (!formData.catalog_id) {
            return;
        }

        const belongsToCatalog = filteredCategories.some(
            (category) => category.id.toString() === formData.category_id,
        );

        if (!belongsToCatalog) {
            setFormData((prev) => ({ ...prev, category_id: '' }));
        }
    }, [formData.catalog_id, filteredCategories]);

    const handleSubmit = (event) => {
        event.preventDefault();

        const payload = {
            ...formData,
            catalog_id: formData.catalog_id ? Number(formData.catalog_id) : null,
            category_id: formData.category_id ? Number(formData.category_id) : null,
            manufacturer_id: formData.manufacturer_id ? Number(formData.manufacturer_id) : null,
            purchase_price_net: formData.purchase_price_net !== '' ? Number(formData.purchase_price_net) : null,
            purchase_vat_rate: formData.purchase_vat_rate !== '' ? Number(formData.purchase_vat_rate) : null,
            sale_price_net: formData.sale_price_net !== '' ? Number(formData.sale_price_net) : null,
            sale_vat_rate: formData.sale_vat_rate !== '' ? Number(formData.sale_vat_rate) : null,
        };

        router.post('/products', payload, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                resetForm();
            },
            onError: (validationErrors) => {
                console.error('Create errors:', validationErrors);
            },
        });
    };

    const handleOpenChange = (nextOpen) => {
        if (!nextOpen) {
            onClose();
            resetForm();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Dodaj nowy produkt</DialogTitle>
                    <DialogDescription>Uzupełnij podstawowe informacje o produkcie</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="product-name">Nazwa *</Label>
                            <Input
                                id="product-name"
                                value={formData.name}
                                onChange={(event) => setFormData((prev) => ({ ...prev, name: event.target.value }))}
                                required
                            />
                            {errors.name && <p className="text-xs text-red-500">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-sku">SKU</Label>
                            <Input
                                id="product-sku"
                                value={formData.sku}
                                onChange={(event) => setFormData((prev) => ({ ...prev, sku: event.target.value }))}
                            />
                            {errors.sku && <p className="text-xs text-red-500">{errors.sku}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-ean">EAN</Label>
                            <Input
                                id="product-ean"
                                value={formData.ean}
                                maxLength={50}
                                onChange={(event) => setFormData((prev) => ({ ...prev, ean: event.target.value }))}
                            />
                            {errors.ean && <p className="text-xs text-red-500">{errors.ean}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-slug">Slug</Label>
                            <Input
                                id="product-slug"
                                value={formData.slug}
                                placeholder="Pozostaw puste aby wygenerować automatycznie"
                                onChange={(event) => setFormData((prev) => ({ ...prev, slug: event.target.value }))}
                            />
                            {errors.slug && <p className="text-xs text-red-500">{errors.slug}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Katalog *</Label>
                            <Select
                                value={formData.catalog_id || undefined}
                                onValueChange={(value) => setFormData((prev) => ({ ...prev, catalog_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Wybierz katalog" />
                                </SelectTrigger>
                                <SelectContent>
                                    {catalogs.map((catalog) => (
                                        <SelectItem key={catalog.id} value={catalog.id.toString()}>
                                            {catalog.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.catalog_id && <p className="text-xs text-red-500">{errors.catalog_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Kategoria</Label>
                            <Select
                                value={formData.category_id || NONE_OPTION}
                                onValueChange={(value) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        category_id: value === NONE_OPTION ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Brak" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE_OPTION}>Brak</SelectItem>
                                    {filteredCategories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            {category.catalog_name ? `${category.catalog_name} › ` : ''}
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && <p className="text-xs text-red-500">{errors.category_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Producent</Label>
                            <Select
                                value={formData.manufacturer_id || NONE_OPTION}
                                onValueChange={(value) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        manufacturer_id: value === NONE_OPTION ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Brak" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE_OPTION}>Brak</SelectItem>
                                    {manufacturers.map((manufacturer) => (
                                        <SelectItem key={manufacturer.id} value={manufacturer.id.toString()}>
                                            {manufacturer.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.manufacturer_id && <p className="text-xs text-red-500">{errors.manufacturer_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select
                                value={formData.status}
                                onValueChange={(value) => setFormData((prev) => ({ ...prev, status: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Szkic</SelectItem>
                                    <SelectItem value="active">Aktywny</SelectItem>
                                    <SelectItem value="archived">Archiwum</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && <p className="text-xs text-red-500">{errors.status}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <div className="space-y-2">
                            <Label htmlFor="product-purchase-price">Cena zakupu netto</Label>
                            <Input
                                id="product-purchase-price"
                                type="number"
                                step="0.01"
                                value={formData.purchase_price_net}
                                onChange={(event) =>
                                    setFormData((prev) => ({ ...prev, purchase_price_net: event.target.value }))
                                }
                            />
                            {errors.purchase_price_net && (
                                <p className="text-xs text-red-500">{errors.purchase_price_net}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-purchase-vat">VAT zakupu (%)</Label>
                            <Input
                                id="product-purchase-vat"
                                type="number"
                                min="0"
                                max="99"
                                value={formData.purchase_vat_rate}
                                onChange={(event) =>
                                    setFormData((prev) => ({ ...prev, purchase_vat_rate: event.target.value }))
                                }
                            />
                            {errors.purchase_vat_rate && (
                                <p className="text-xs text-red-500">{errors.purchase_vat_rate}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-sale-price">Cena sprzedaży netto</Label>
                            <Input
                                id="product-sale-price"
                                type="number"
                                step="0.01"
                                value={formData.sale_price_net}
                                onChange={(event) =>
                                    setFormData((prev) => ({ ...prev, sale_price_net: event.target.value }))
                                }
                            />
                            {errors.sale_price_net && <p className="text-xs text-red-500">{errors.sale_price_net}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="product-sale-vat">VAT sprzedaży (%)</Label>
                            <Input
                                id="product-sale-vat"
                                type="number"
                                min="0"
                                max="99"
                                value={formData.sale_vat_rate}
                                onChange={(event) =>
                                    setFormData((prev) => ({ ...prev, sale_vat_rate: event.target.value }))
                                }
                            />
                            {errors.sale_vat_rate && <p className="text-xs text-red-500">{errors.sale_vat_rate}</p>}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="product-description">Opis</Label>
                        <Textarea
                            id="product-description"
                            rows={4}
                            value={formData.description}
                            onChange={(event) =>
                                setFormData((prev) => ({ ...prev, description: event.target.value }))
                            }
                        />
                        {errors.description && <p className="text-xs text-red-500">{errors.description}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="product-images">Zdjęcia (URL)</Label>
                        <Textarea
                            id="product-images"
                            rows={3}
                            placeholder="Wpisz URL zdjęć oddzielone przecinkami"
                            value={formData.images}
                            onChange={(event) =>
                                setFormData((prev) => ({ ...prev, images: event.target.value }))
                            }
                        />
                        {errors.images && <p className="text-xs text-red-500">{errors.images}</p>}
                        <p className="text-xs text-muted-foreground">Wpisz adresy URL zdjęć oddzielone przecinkami.</p>

                        {imagePreview.length > 0 && (
                            <div className="mt-4">
                                <Label className="text-sm text-gray-600">Podgląd zdjęć:</Label>
                                <div className="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                                    {imagePreview.slice(0, 8).map((url, index) => (
                                        <div key={index} className="relative">
                                            <img
                                                src={url}
                                                alt={`Zdjęcie ${index + 1}`}
                                                className="h-20 w-full rounded border object-cover"
                                                onError={(event) => {
                                                    event.target.style.opacity = '0.5';
                                                    event.target.style.filter = 'grayscale(100%)';
                                                }}
                                            />
                                        </div>
                                    ))}
                                    {imagePreview.length > 8 && (
                                        <div className="flex h-20 w-full items-center justify-center rounded border bg-gray-100 text-xs text-gray-500">
                                            +{imagePreview.length - 8} więcej
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t pt-4">
                        <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                            Anuluj
                        </Button>
                        <Button type="submit">Dodaj produkt</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
