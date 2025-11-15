<?php

// Sprawdź zamówienia w PrestaShop

$integration = App\Models\Integration::where('type', 'prestashop-db')->first();

if ($integration) {
    $config = app(App\Integrations\IntegrationService::class)->runtimeConfig($integration);
    $prefix = $config['db_prefix'] ?? 'ps_';
    echo "PrestaShop DB prefix: {$prefix}\n";
    
    try {
        $connection = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']}",
            $config['db_username'],
            $config['db_password']
        );
        
        // Sprawdź kilka zamówień
        $stmt = $connection->prepare("SELECT id_order, reference, total_paid, current_state, date_add FROM {$prefix}orders LIMIT 5");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Sample orders from PrestaShop:\n";
        foreach ($orders as $order) {
            echo "- Order #{$order['id_order']}: {$order['reference']} - {$order['total_paid']} EUR - State: {$order['current_state']}\n";
        }
        
        // Liczba zamówień
        $stmt = $connection->prepare("SELECT COUNT(*) as total FROM {$prefix}orders");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nTotal orders in PrestaShop: {$result['total']}\n";
        
    } catch (Exception $e) {
        echo "DB Error: " . $e->getMessage();
    }
} else {
    echo "No PrestaShop DB integration found";
}