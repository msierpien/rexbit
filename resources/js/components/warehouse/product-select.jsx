import React, { useState, useRef, useEffect } from 'react';
import { Search, Package, X } from 'lucide-react';

export function ProductSelect({ products, value, onChange, placeholder = "Wyszukaj produkt..." }) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [filteredProducts, setFilteredProducts] = useState(products);
    const inputRef = useRef(null);
    const dropdownRef = useRef(null);

    const selectedProduct = products.find(product => product.id == value);

    useEffect(() => {
        if (!searchQuery.trim()) {
            setFilteredProducts(products);
            return;
        }

        const query = searchQuery.toLowerCase();
        const filtered = products.filter(product => {
            const nameMatch = product.name.toLowerCase().includes(query);
            const skuMatch = product.sku?.toLowerCase().includes(query);
            const eanMatch = product.ean?.toLowerCase().includes(query);
            return nameMatch || skuMatch || eanMatch;
        });

        setFilteredProducts(filtered);
    }, [searchQuery, products]);

    useEffect(() => {
        function handleClickOutside(event) {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target) &&
                inputRef.current &&
                !inputRef.current.contains(event.target)
            ) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleProductSelect = (product) => {
        onChange(product.id);
        setIsOpen(false);
        setSearchQuery('');
    };

    const handleClear = () => {
        onChange('');
        setSearchQuery('');
        setIsOpen(false);
    };

    const handleInputFocus = () => {
        setIsOpen(true);
        setSearchQuery('');
    };

    const formatProductDisplay = (product) => {
        const parts = [];
        if (product.name) parts.push(product.name);
        if (product.sku) parts.push(`SKU: ${product.sku}`);
        if (product.ean) parts.push(`EAN: ${product.ean}`);
        return parts.join(' • ');
    };

    return (
        <div className="relative">
            {/* Selected Product Display / Search Input */}
            <div className="relative">
                {selectedProduct && !isOpen ? (
                    <div className="flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm">
                        <div className="flex items-center space-x-2">
                            <Package className="h-4 w-4 text-gray-400" />
                            <span className="truncate">{formatProductDisplay(selectedProduct)}</span>
                        </div>
                        <button
                            type="button"
                            onClick={handleClear}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ) : (
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onFocus={handleInputFocus}
                            placeholder={placeholder}
                            data-scanner-input="true"
                            className="w-full rounded-md border border-gray-300 pl-10 pr-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                )}
            </div>

            {/* Dropdown */}
            {isOpen && (
                <div
                    ref={dropdownRef}
                    className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"
                >
                    {filteredProducts.length > 0 ? (
                        <ul className="py-1">
                            {filteredProducts.map((product) => (
                                <li key={product.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleProductSelect(product)}
                                        className="flex w-full items-center px-3 py-2 text-left text-sm hover:bg-gray-50"
                                    >
                                        <Package className="mr-2 h-4 w-4 text-gray-400" />
                                        <div>
                                            <div className="font-medium text-gray-900">{product.name}</div>
                                            <div className="text-xs text-gray-500">
                                                {product.sku && <span>SKU: {product.sku}</span>}
                                                {product.sku && product.ean && <span> • </span>}
                                                {product.ean && <span>EAN: {product.ean}</span>}
                                            </div>
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="px-3 py-2 text-sm text-gray-500">
                            Brak produktów spełniających kryteria wyszukiwania
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default ProductSelect;