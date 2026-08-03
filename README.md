docker exec auth-service composer require laravel/passport

docker exec inventory-service php artisan vendor:publish --provider="Ecomstarter\Core\CoreServiceProvider"
