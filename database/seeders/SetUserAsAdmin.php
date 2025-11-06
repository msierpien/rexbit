<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Przykładowy skrypt do ustawienia użytkownika jako admin
     * 
     * Run: php artisan tinker
     * >>> include 'database/seeders/SetUserAsAdmin.php';
     */
    public function setUserAsAdmin(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            echo "❌ Użytkownik o ID {$userId} nie istnieje!\n";
            return;
        }
        
        $oldRole = $user->role->value ?? 'null';
        $user->role = \App\Enums\Role::ADMIN;
        $user->save();
        
        echo "✅ Użytkownik {$user->name} (ID: {$userId}) został ustawiony jako admin\n";
        echo "   Poprzednia rola: {$oldRole}\n";
        echo "   Nowa rola: {$user->role->value}\n";
    }
    
    /**
     * Sprawdź role użytkownika
     */
    public function checkUserRole(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            echo "❌ Użytkownik o ID {$userId} nie istnieje!\n";
            return;
        }
        
        echo "Użytkownik: {$user->name} (ID: {$userId})\n";
        echo "Rola: {$user->role->value}\n";
        echo "Jest adminem: " . ($user->isAdmin() ? 'TAK' : 'NIE') . "\n";
    }
};

// Przykłady użycia w tinker:
// include 'database/seeders/SetUserAsAdmin.php';
// $seeder = new class extends Migration { ... };
// $seeder->setUserAsAdmin(1);
// $seeder->checkUserRole(1);
