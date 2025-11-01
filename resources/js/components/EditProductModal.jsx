import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const NONE_OPTION = '__none__';

export default function EditProductModal({
    open,
    onClose,
    product,
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

    // Update form data when product changes
    useEffect(() => {
        if (product) {
            setFormData({
                name: product.name ?? '',
                sku: product.sku ?? '',
                ean: product.ean ?? '',
                slug: product.slug ?? '',
                catalog_id: product.catalog_id ? String(product.catalog_id) : '',
                category_id: product.category_id ? String(product.category_id) : '',
                manufacturer_id: product.manufacturer_id ? String(product.manufacturer_id) : '',
                description: product.description ?? '',
                images: Array.isArray(product.images) ? product.images.join(', ') : product.images ?? '',
                purchase_price_net: product.purchase_price_net !== null && product.purchase_price_net !== undefined
                    ? String(product.purchase_price_net)
                    : '',
                purchase_vat_rate: product.purchase_vat_rate !== null && product.purchase_vat_rate !== undefined
                    ? String(product.purchase_vat_rate)
                    : '',
                sale_price_net: product.sale_price_net !== null && product.sale_price_net !== undefined
                    ? String(product.sale_price_net)
                    : '',
                sale_vat_rate: product.sale_vat_rate !== null && product.sale_vat_rate !== undefined
                    ? String(product.sale_vat_rate)
                    : '',
                status: product.status ?? 'active',
            });
        }
    }, [product]);

    // Update image preview when images field changes
    useEffect(() => {
        if (formData.images) {
            const urls = formData.images.split(',').map((url) => url.trim()).filter((url) => url);
            setImagePreview(urls);
        } else {
            setImagePreview([]);
        }
    }, [formData.images]);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!product) return;

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

        router.put(`/products/${product.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
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
            },
            onError: (validationErrors) => {
                console.error('Update errors:', validationErrors);
            },
        });
    };

    const handleOpenChange = (nextOpen) => {
        if (!nextOpen) {
            onClose();
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
        }
    };

    // Filter categories based on selected catalog
    const filteredCategories = categories.filter(
        (category) => !formData.catalog_id || Number(category.catalog_id) === Number(formData.catalog_id),
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

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edytuj produkt</DialogTitle>
                    <DialogDescription>
                        Zaktualizuj informacje o produkcie
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Nazwa *</Label>
                            <Input
                                id="name"
                                type="text"
                                value={formData.name}
                                onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input
                                id="sku"
                                type="text"
                                value={formData.sku}
                                onChange={(e) => setFormData((prev) => ({ ...prev, sku: e.target.value }))}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ean">EAN</Label>
                            <Input
                                id="ean"
                                type="text"
                                value={formData.ean}
                                onChange={(e) => setFormData((prev) => ({ ...prev, ean: e.target.value }))}
                                maxLength={50}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                type="text"
                                value={formData.slug}
                                onChange={(e) => setFormData((prev) => ({ ...prev, slug: e.target.value }))}
                                placeholder="Pozostaw puste aby wygenerować automatycznie"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="catalog_id">Katalog *</Label>
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
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="category_id">Kategoria</Label>
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
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="manufacturer_id">Producent</Label>
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
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="status">Status</Label>
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
                        </div>
                    </div>

                    {/* Pricing Section */}
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <div className="space-y-2">
                            <Label htmlFor="purchase_price_net">Cena zakupu netto</Label>
                            <Input
                                id="purchase_price_net"
                                type="number"
                                step="0.01"
                                value={formData.purchase_price_net}
                                onChange={(e) => setFormData((prev) => ({ ...prev, purchase_price_net: e.target.value }))}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="purchase_vat_rate">VAT zakupu (%)</Label>
                            <Input
                                id="purchase_vat_rate"
                                type="number"
                                value={formData.purchase_vat_rate}
                                onChange={(e) =>
                                    setFormData((prev) => ({ ...prev, purchase_vat_rate: e.target.value }))
                                }
                                min="0"
                                max="99"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sale_price_net">Cena sprzedaży netto</Label>
                            <Input
                                id="sale_price_net"
                                type="number"
                                step="0.01"
                                value={formData.sale_price_net}
                                onChange={(e) => setFormData((prev) => ({ ...prev, sale_price_net: e.target.value }))}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sale_vat_rate">VAT sprzedaży (%)</Label>
                            <Input
                                id="sale_vat_rate"
                                type="number"
                                value={formData.sale_vat_rate}
                                onChange={(e) => setFormData((prev) => ({ ...prev, sale_vat_rate: e.target.value }))}
                                min="0"
                                max="99"
                            />
                        </div>
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Opis</Label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
                            rows={4}
                        />
                    </div>

                    {/* Images */}
                    <div className="space-y-2">
                        <Label htmlFor="images">Zdjęcia (URL)</Label>
                        <Textarea
                            id="images"
                            value={formData.images}
                            onChange={(e) => setFormData((prev) => ({ ...prev, images: e.target.value }))}
                            rows={3}
                            placeholder="Wpisz URL zdjęć oddzielone przecinkami"
                        />
                        <p className="text-xs text-gray-500">Wpisz adresy URL zdjęć oddzielone przecinkami</p>

                        {/* Image Preview */}
                        {imagePreview.length > 0 && (
                            <div className="mt-4">
                                <Label className="text-sm text-gray-600">Podgląd zdjęć:</Label>
                                <div className="grid grid-cols-2 gap-2 mt-2 md:grid-cols-4">
                                    {imagePreview.slice(0, 8).map((url, index) => (
                                        <div key={index} className="relative">
                                            <img
                                                src={url}
                                                alt={`Zdjęcie ${index + 1}`}
                                                className="w-full h-20 object-cover rounded border"
                                                onError={(e) => {
                                                    e.target.style.opacity = '0.5';
                                                    e.target.style.filter = 'grayscale(100%)';
                                                }}
                                            />
                                        </div>
                                    ))}
                                    {imagePreview.length > 8 && (
                                        <div className="flex items-center justify-center w-full h-20 bg-gray-100 rounded border text-xs text-gray-500">
                                            +{imagePreview.length - 8} więcej
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 pt-4 border-t">
                        <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                            Anuluj
                        </Button>
                        <Button type="submit">
                            Zapisz zmiany
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
