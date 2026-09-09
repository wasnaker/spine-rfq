<?php

declare(strict_types=1);

namespace Modules\Rfq\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Rfq\Listeners\LogRfqActivity;

class RfqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::listen(\Spine\Events\EntityCreated::class, LogRfqActivity::class . '@created');
        Event::listen(\Spine\Events\EntityUpdated::class, LogRfqActivity::class . '@updated');
        Event::listen(\Spine\Events\EntityDeleted::class, LogRfqActivity::class . '@deleted');
    }
}
