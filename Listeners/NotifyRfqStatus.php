<?php

declare(strict_types=1);

namespace Modules\Rfq\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Customer\Models\CustomerStaff;
use Modules\Rfq\Models\Rfq;
use Modules\Rfq\Notifications\RfqStatusNotification;
use Modules\Surveyor\Models\SurveyorStaff;
use Spine\Events\EntityUpdated;
use App\Models\User;

/**
 * HOOK — transisi status RFQ (EntityUpdated dgn changes.status) -> notifikasi bell.
 *
 * Penerima ditentukan MODUL (tenant target): admin entity secara default,
 * atau SEMUA user tenant kalau $notifyAll = true.
 */
class NotifyRfqStatus
{
    public function updated(EntityUpdated $event): void
    {
        $rfq = $event->entity;

        if (! $rfq instanceof Rfq || ! isset($event->changes['status'])) {
            return;
        }

        $old = $event->changes['status']['old'];
        $new = $event->changes['status']['new'];
        $actor = Rfq::TRANSITIONS[$old][$new] ?? null;

        if ($actor === null) {
            return;
        }

        // Notifikasi dikirim ke pihak LAWAN dari pelaku transisi.
        $this->notifyParty($rfq, $actor === 'customer' ? 'surveyor' : 'customer', $new);
    }

    private function notifyParty(Rfq $rfq, string $party, string $status, bool $notifyAll = false): void
    {
        $users = $notifyAll
            ? $this->tenantStaffUserIds($rfq, $party)
            : $this->tenantAdminUserId($rfq, $party);

        if (empty($users)) {
            return;
        }

        Notification::send(
            User::whereIn('id', $users)->get(),
            new RfqStatusNotification($rfq->formatted_number, $status, $rfq->id),
        );
    }

    private function tenantAdminUserId(Rfq $rfq, string $party): array
    {
        $adminId = $party === 'customer'
            ? $rfq->customer?->admin_id
            : $rfq->surveyor?->admin_id;

        return $adminId ? [$adminId] : [];
    }

    private function tenantStaffUserIds(Rfq $rfq, string $party): array
    {
        if ($party === 'customer') {
            return CustomerStaff::where('customer_id', $rfq->customer_id)->pluck('user_id')->all();
        }

        return SurveyorStaff::where('surveyor_id', $rfq->surveyor_id)->pluck('user_id')->all();
    }
}
