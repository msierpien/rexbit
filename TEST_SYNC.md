# Test synchronizacji dostępności dostawcy

## 1. Ustaw minimalny próg w PrestaShop
http://localhost/integrations/1/edit
- Minimalny stan u dostawcy: **20**
- Synchronizuj tylko zmienione: **TAK** ✓
- Zapisz

## 2. Testuj synchronizację z limitem
```bash
sail artisan supplier:sync-availability --prestashop=1 --limit=10
```

## 3. Sprawdź wyniki
- **total**: 10 (sprawdzonych produktów)
- **synced**: X (wysłanych do PrestaShop)
- **skipped**: Y (bez zmian, pominięto)
- **failed**: 0

## 4. Sprawdź w bazie danych
```sql
SELECT 
    p.id,
    p.name,
    ipl.supplier_availability->>'stock_quantity' as supplier_stock,
    ipl.metadata->'supplier_sync'->>'last_availability' as is_available,
    ipl.metadata->'supplier_sync'->>'last_stock_quantity' as last_stock
FROM integration_product_links ipl
JOIN products p ON p.id = ipl.product_id
WHERE ipl.integration_id = 1
  AND ipl.supplier_availability IS NOT NULL
LIMIT 10;
```

## Logika:
- supplier_stock >= 20 → is_available = true → PrestaShop out_of_stock = 1 (można zamawiać)
- supplier_stock < 20 → is_available = false → PrestaShop out_of_stock = 0 (niedostępny)
