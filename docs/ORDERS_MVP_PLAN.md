# Plan MVP - Moduł Zamówień

## Faza 1: Minimum Viable Product (2 tygodnie)

### Tabele bazowe:
```sql
-- orders (uproszczone)
- id, number, user_id, integration_id, external_order_id
- status, total_net, total_gross, currency
- customer_name, customer_email, customer_phone
- created_at, updated_at

-- order_items (uproszczone)  
- id, order_id, product_id, integration_product_link_id
- name, sku, quantity, price_net, price_gross
- created_at, updated_at

-- order_status_history
- id, order_id, from_status, to_status, changed_by
- comment, created_at
```

### Funkcjonalność MVP:
1. ✅ Lista zamówień z filtrowaniem
2. ✅ Widok szczegółowy zamówienia
3. ✅ Ręczna zmiana statusu
4. ✅ Import z PrestaShop (tylko odczyt)
5. ✅ Podstawowe API

### Kontrolery MVP:
```php
OrderController:
- index() - lista z filtrowaniem
- show() - szczegóły zamówienia  
- updateStatus() - zmiana statusu

OrderImportController:
- importFromPrestashop() - import z integracji
```

### Komponenty React MVP:
```jsx
- OrderList.jsx - tabela z zamówieniami
- OrderDetail.jsx - szczegóły + zmiana statusu
- OrderStatusBadge.jsx - kolorowe statusy
- OrderFilters.jsx - filtry
```

## Korzyści MVP:
- Szybkie uruchomienie (2 tygodnie vs 2-3 miesiące)
- Wczesny feedback od użytkowników
- Stopniowe rozwijanie funkcjonalności
- Mniejsze ryzyko błędów