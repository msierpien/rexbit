import { useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function EditProductModal({ 
    open, 
    onClose, 
    product, 
    catalogs = [], 
    categories = [], 
    manufacturers = [] 
}) {
    const { data, setData, put, processing, errors, reset } = useForm({
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
            console.log('Product data in modal:', product);
            setData({
                name: product.name || '',
                sku: product.sku || '',
                ean: product.ean || '',
                slug: product.slug || '',
                catalog_id: product.catalog_id || '',
                category_id: product.category_id || '',
                manufacturer_id: product.manufacturer_id || '',
                description: product.description || '',
                images: Array.isArray(product.images) ? product.images.join(', ') : (product.images || ''),
                purchase_price_net: product.purchase_price_net || '',
                purchase_vat_rate: product.purchase_vat_rate || '',
                sale_price_net: product.sale_price_net || '',
                sale_vat_rate: product.sale_vat_rate || '',
                status: product.status || 'active',
            });
        }
    }, [product]);

    // Update image preview when images field changes
    useEffect(() => {
        if (data.images) {
            const urls = data.images.split(',').map(url => url.trim()).filter(url => url);
            setImagePreview(urls);
        } else {
            setImagePreview([]);
        }
    }, [data.images]);

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (!product) return;
        
        router.put(route('products.update', product.id), data, {
            preserveScroll: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            onSuccess: () => {
                onClose();
                reset();
            },
            onError: (errors) => {
                console.error('Update errors:', errors);
            }
        });
    };

    const handleClose = () => {
        onClose();
        reset();
    };

    // Filter categories based on selected catalog
    const filteredCategories = categories.filter(category => 
        !data.catalog_id || category.catalog_id === parseInt(data.catalog_id)
    );

    return (
        <Dialog open={open} onOpenChange={handleClose}>
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
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={errors.name ? 'border-red-500' : ''}
                                required
                            />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input
                                id="sku"
                                type="text"
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                className={errors.sku ? 'border-red-500' : ''}
                            />
                            {errors.sku && <p className="text-sm text-red-500">{errors.sku}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ean">EAN</Label>
                            <Input
                                id="ean"
                                type="text"
                                value={data.ean}
                                onChange={(e) => setData('ean', e.target.value)}
                                className={errors.ean ? 'border-red-500' : ''}
                                maxLength={50}
                            />
                            {errors.ean && <p className="text-sm text-red-500">{errors.ean}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                type="text"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                className={errors.slug ? 'border-red-500' : ''}
                                placeholder="Pozostaw puste aby wygenerować automatycznie"
                            />
                            {errors.slug && <p className="text-sm text-red-500">{errors.slug}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="catalog_id">Katalog *</Label>
                            <Select value={data.catalog_id.toString()} onValueChange={(value) => setData('catalog_id', value)}>
                                <SelectTrigger className={errors.catalog_id ? 'border-red-500' : ''}>
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
                            {errors.catalog_id && <p className="text-sm text-red-500">{errors.catalog_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="category_id">Kategoria</Label>
                            <Select value={data.category_id.toString()} onValueChange={(value) => setData('category_id', value)}>
                                <SelectTrigger className={errors.category_id ? 'border-red-500' : ''}>
                                    <SelectValue placeholder="Wybierz kategorię" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredCategories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            {category.catalog?.name ? `${category.catalog.name} › ` : ''}{category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && <p className="text-sm text-red-500">{errors.category_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="manufacturer_id">Producent</Label>
                            <Select value={data.manufacturer_id.toString()} onValueChange={(value) => setData('manufacturer_id', value)}>
                                <SelectTrigger className={errors.manufacturer_id ? 'border-red-500' : ''}>
                                    <SelectValue placeholder="Wybierz producenta" />
                                </SelectTrigger>
                                <SelectContent>
                                    {manufacturers.map((manufacturer) => (
                                        <SelectItem key={manufacturer.id} value={manufacturer.id.toString()}>
                                            {manufacturer.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.manufacturer_id && <p className="text-sm text-red-500">{errors.manufacturer_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="status">Status</Label>
                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger className={errors.status ? 'border-red-500' : ''}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Szkic</SelectItem>
                                    <SelectItem value="active">Aktywny</SelectItem>
                                    <SelectItem value="archived">Archiwum</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && <p className="text-sm text-red-500">{errors.status}</p>}
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
                                value={data.purchase_price_net}
                                onChange={(e) => setData('purchase_price_net', e.target.value)}
                                className={errors.purchase_price_net ? 'border-red-500' : ''}
                            />
                            {errors.purchase_price_net && <p className="text-sm text-red-500">{errors.purchase_price_net}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="purchase_vat_rate">VAT zakupu (%)</Label>
                            <Input
                                id="purchase_vat_rate"
                                type="number"
                                value={data.purchase_vat_rate}
                                onChange={(e) => setData('purchase_vat_rate', e.target.value)}
                                className={errors.purchase_vat_rate ? 'border-red-500' : ''}
                                min="0"
                                max="99"
                            />
                            {errors.purchase_vat_rate && <p className="text-sm text-red-500">{errors.purchase_vat_rate}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sale_price_net">Cena sprzedaży netto</Label>
                            <Input
                                id="sale_price_net"
                                type="number"
                                step="0.01"
                                value={data.sale_price_net}
                                onChange={(e) => setData('sale_price_net', e.target.value)}
                                className={errors.sale_price_net ? 'border-red-500' : ''}
                            />
                            {errors.sale_price_net && <p className="text-sm text-red-500">{errors.sale_price_net}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sale_vat_rate">VAT sprzedaży (%)</Label>
                            <Input
                                id="sale_vat_rate"
                                type="number"
                                value={data.sale_vat_rate}
                                onChange={(e) => setData('sale_vat_rate', e.target.value)}
                                className={errors.sale_vat_rate ? 'border-red-500' : ''}
                                min="0"
                                max="99"
                            />
                            {errors.sale_vat_rate && <p className="text-sm text-red-500">{errors.sale_vat_rate}</p>}
                        </div>
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Opis</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className={errors.description ? 'border-red-500' : ''}
                            rows={4}
                        />
                        {errors.description && <p className="text-sm text-red-500">{errors.description}</p>}
                    </div>

                    {/* Images */}
                    <div className="space-y-2">
                        <Label htmlFor="images">Zdjęcia (URL)</Label>
                        <Textarea
                            id="images"
                            value={data.images}
                            onChange={(e) => setData('images', e.target.value)}
                            className={errors.images ? 'border-red-500' : ''}
                            rows={3}
                            placeholder="Wpisz URL zdjęć oddzielone przecinkami"
                        />
                        {errors.images && <p className="text-sm text-red-500">{errors.images}</p>}
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
                        <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
                            Anuluj
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Zapisywanie...' : 'Zapisz zmiany'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}