<?php

declare(strict_types=1);

namespace Modules\Rfq\Listeners;

use Modules\Rfq\Models\Rfq;
use Spine\Events\EntityCreated;
use Spine\Events\EntityDeleted;
use Spine\Events\EntityUpdated;
use Spine\Services\ActivityLogService;

/**
 * HOOK — lifecycle dokumen RFQ (HasLifecycleHooks).
 * created/updated/deleted -> activity log.
 */
class LogRfqActivity
{
    public function __construct(private readonly ActivityLogService $activityLog)
    {
    }

    public function created(EntityCreated $event): void
    {
        if (! $this->supports($event->entity)) {
            return;
        }

        $this->activityLog->log(
            'RFQ created: ' . $this->displayName($event->entity),
            $event->entity,
            $this->user(),
            ['event' => 'created'],
        );
    }

    public function updated(EntityUpdated $event): void
    {
        if (! $this->supports($event->entity)) {
            return;
        }

        $changes = $event->changes;

        $this->activityLog->log(
            'RFQ updated: ' . $this->displayName($event->entity) . ' (' . $this->describe($event->entity, $changes) . ')',
            $event->entity,
            $this->user(),
            ['event' => 'updated', 'changes' => $changes],
        );
    }

    public function deleted(EntityDeleted $event): void
    {
        if (! $this->supports($event->entity)) {
            return;
        }

        $this->activityLog->log(
            'RFQ deleted: ' . $this->displayName($event->entity),
            null,
            $this->user(),
            ['event' => 'deleted', 'id' => $event->entity->getKey()],
            null,
            $event->entityType,
        );
    }

    private function supports(object $entity): bool
    {
        return $entity instanceof Rfq;
    }

    private function displayName(object $entity): string
    {
        return $entity->formatted_number ?? '#' . $entity->getKey();
    }

    private function describe(object $entity, array $changes): string
    {
        $parts = [];
        $labels = method_exists($entity, 'labels') ? $entity::labels() : [];

        foreach ($changes as $field => $change) {
            if (in_array($field, ['updated_at', 'remember_token'], true)) {
                continue;
            }

            $old = $change['old'];
            $new = $change['new'];
            $label = $labels[$field] ?? $field;
            $parts[] = $label . ': ' . ($old === null || $old === '' ? '(kosong)' : $old) . ' -> ' . ($new === null || $new === '' ? '(kosong)' : $new);
        }

        return implode(', ', $parts);
    }

    private function user(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return auth('sanctum')->user() ?? auth()->user();
    }
}
