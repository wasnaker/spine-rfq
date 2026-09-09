<?php

declare(strict_types=1);

namespace Modules\Rfq\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Rfq\Listeners\LogRfqActivity;
use Modules\Workflow\Support\Workflow;

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
        Event::listen(\Spine\Events\EntityUpdated::class, \Modules\Rfq\Listeners\NotifyRfqStatus::class . '@updated');
        Event::listen(\Spine\Events\EntityDeleted::class, LogRfqActivity::class . '@deleted');

        if (class_exists(Workflow::class)) {
            Workflow::register('rfq', [
                'label' => 'RFQ',
                'states' => [
                    'draft'    => ['label' => 'Draft',    'color' => '#aaaaaa'],
                    'sent'     => ['label' => 'Sent',     'color' => '#3498db'],
                    'declined' => ['label' => 'Declined', 'color' => '#e74c3c'],
                    'accepted' => ['label' => 'Accepted', 'color' => '#2ecc71'],
                    'expired'  => ['label' => 'Expired',  'color' => '#f39c12'],
                ],
                'transitions' => [
                    'send'    => ['label' => 'Send',    'from' => ['draft'], 'to' => 'sent',     'actor' => ['customer']],
                    'retract' => ['label' => 'Retract', 'from' => ['sent'],  'to' => 'draft',    'actor' => ['customer']],
                    'accept'  => ['label' => 'Accept',  'from' => ['sent'],  'to' => 'accepted', 'actor' => ['surveyor']],
                    'decline' => ['label' => 'Decline', 'from' => ['sent'],  'to' => 'declined', 'actor' => ['surveyor']],
                    'expire'  => ['label' => 'Expire',  'from' => ['sent'],  'to' => 'expired',  'actor' => ['surveyor']],
                ],
            ]);
        }
    }
}
