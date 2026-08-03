<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        Passport::enablePasswordGrant();
        Passport::useTokenModel(\Laravel\Passport\Token::class);
        // ── Token TTLs ────────────────────────────────────────────────────────
        Passport::tokensExpireIn(Carbon::now()->addHour());
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(30));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addYear());

        // ── Scope definitions ──────────────────────────────────────────────────
        Passport::tokensCan([
            // User Management
            'users.view'            => 'View users',
            'users.create'          => 'Create users',
            'users.update'          => 'Update users',
            'users.delete'          => 'Delete users',

            // Role Management
            'roles.view'            => 'View roles',
            'roles.create'          => 'Create roles',
            'roles.update'          => 'Update roles',
            'roles.delete'          => 'Delete roles',

            // Product Management
            'products.view'         => 'View products',
            'products.create'       => 'Create products',
            'products.update'       => 'Update products',
            'products.delete'       => 'Delete products',

            // Inventory
            'inventory.create'      => 'Store inventory',
            'inventory.view'        => 'View inventory',
            'inventory.reserve'     => 'Reserve inventory',
            'inventory.release'     => 'Release reserved inventory',

            // Orders
            'orders.view'           => 'View orders',
            'orders.create'         => 'Create orders',
            'orders.update'         => 'Update orders',
            'orders.cancel'         => 'Cancel orders',

            // Notifications
            'notifications.send'    => 'Send notifications',

            // OAuth Client Management
            'oauth-clients.manage'  => 'Manage OAuth clients',
        ]);
    }
}
