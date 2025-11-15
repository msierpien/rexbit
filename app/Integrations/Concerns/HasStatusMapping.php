<?php

namespace App\Integrations\Concerns;

use App\Models\OrderStatus;

trait HasStatusMapping
{
    /**
     * Mapuj status zamówienia z zewnętrznego systemu na lokalny status
     */
    public function mapOrderStatus(string $externalStatus, string $platform): string
    {
        $mapping = config("integrations.status_mapping.{$platform}.order_statuses", []);
        $localKey = $mapping[$externalStatus] ?? 'awaiting_payment';
        
        // Sprawdź czy status istnieje w bazie dla tego użytkownika
        $status = OrderStatus::where('key', $localKey)
            ->where('type', 'order')
            ->where('is_active', true)
            ->first();
            
        return $status ? $status->key : OrderStatus::getDefaultOrderStatus();
    }

    /**
     * Mapuj status płatności na podstawie statusu zamówienia i danych płatności
     */
    public function mapPaymentStatus(array $orderData, string $platform): string
    {
        $status = $orderData['current_state'] ?? $orderData['status'] ?? '';
        $totalPaid = (float)($orderData['total_paid'] ?? 0);
        $totalOrder = (float)($orderData['total_paid_tax_incl'] ?? $orderData['total_paid'] ?? $orderData['total'] ?? 0);
        
        $paymentMapping = config("integrations.status_mapping.{$platform}.payment_statuses", []);
        
        $localKey = 'pending'; // domyślnie
        
        // Sprawdź czy status oznacza pełną płatność
        if (in_array($status, $paymentMapping['paid_statuses'] ?? [])) {
            if ($totalPaid >= $totalOrder) {
                $localKey = 'paid';
            } elseif ($totalPaid > 0) {
                $localKey = 'partially_paid';
            }
        }
        
        // Sprawdź czy status oznacza częściową płatność
        if (in_array($status, $paymentMapping['partial_paid_statuses'] ?? [])) {
            $localKey = 'partially_paid';
        }
        
        // Sprawdź czy status oznacza zwrot
        if (in_array($status, $paymentMapping['refunded_statuses'] ?? [])) {
            $localKey = 'refunded';
        }
        
        // Sprawdź czy status oznacza częściowy zwrot
        if (in_array($status, $paymentMapping['partial_refunded_statuses'] ?? [])) {
            $localKey = 'partially_refunded';
        }
        
        // Sprawdź czy status oznacza błąd płatności
        if (in_array($status, $paymentMapping['error_statuses'] ?? [])) {
            $localKey = 'payment_error';
        }
        
        // Sprawdź czy status istnieje w bazie dla tego użytkownika
        $paymentStatus = OrderStatus::where('key', $localKey)
            ->where('type', 'payment')
            ->where('is_active', true)
            ->first();
            
        return $paymentStatus ? $paymentStatus->key : OrderStatus::getDefaultPaymentStatus();
    }

    /**
     * Pobierz wszystkie dostępne statusy zamówień dla danej platformy
     */
    public function getAvailableOrderStatuses(string $platform): array
    {
        return config("integrations.status_mapping.{$platform}.order_statuses", []);
    }

    /**
     * Pobierz lokalne statusy zamówień z bazy danych
     */
    public function getLocalOrderStatuses(): array
    {
        return OrderStatus::getOrderStatuses();
    }

    /**
     * Pobierz lokalne statusy płatności z bazy danych
     */
    public function getLocalPaymentStatuses(): array
    {
        return OrderStatus::getPaymentStatuses();
    }
}