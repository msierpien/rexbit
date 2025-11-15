#!/bin/bash
# Komendy do uruchomienia na serwerze 192.168.1.191

echo "=== Sprawdzanie statusu kontenerów ==="
cd ~/rexbit
docker ps

echo "=== Sprawdzanie logów kontenerów ==="
docker-compose logs --tail=50

echo "=== Sprawdzanie konfiguracji bazy danych ==="
docker exec rexbit-laravel.test-1 php artisan config:show database

echo "=== Sprawdzanie statusu migracji ==="
docker exec rexbit-laravel.test-1 php artisan migrate:status

echo "=== Czyszczenie cache przed migracjami ==="
docker exec rexbit-laravel.test-1 php artisan config:clear
docker exec rexbit-laravel.test-1 php artisan cache:clear

echo "=== Próba uruchomienia migracji ==="
docker exec rexbit-laravel.test-1 php artisan migrate --force

echo "=== Jeśli migracje nie działają, sprawdź połączenie z bazą ==="
docker exec rexbit-laravel.test-1 php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection OK';"

echo "=== Sprawdź czy baza danych istnieje ==="
docker exec rexbit-pgsql-1 psql -U sail -l

echo "=== Utwórz bazę danych jeśli nie istnieje ==="
docker exec rexbit-pgsql-1 createdb -U sail laravel 2>/dev/null || echo "Database already exists or error creating"

echo "=== Ponów migracje po utworzeniu bazy ==="
docker exec rexbit-laravel.test-1 php artisan migrate --force