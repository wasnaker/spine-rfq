<?php

declare(strict_types=1);

namespace Modules\Rfq\Notifications;

use Modules\Rfq\Models\Rfq;
use Spine\Notifications\BaseNotification;

/**
 * Notifikasi bell untuk transisi status RFQ.
 * Channel database default; modul bisa override via() utk mail/pusher/telegram.
 */
class RfqStatusNotification extends BaseNotification
{
    private const LABELS = [
        Rfq::STATUS_DRAFT => 'Draf',
        Rfq::STATUS_SENT => 'Terkirim',
        Rfq::STATUS_ACCEPTED => 'Diterima',
        Rfq::STATUS_DECLINED => 'Ditolak',
        Rfq::STATUS_EXPIRED => 'Kedaluwarsa',
    ];

    private const BODIES = [
        Rfq::STATUS_DRAFT => 'RFQ dikembalikan ke draf (pengiriman dibatalkan).',
        Rfq::STATUS_SENT => 'RFQ baru dikirim untuk ditinjau.',
        Rfq::STATUS_ACCEPTED => 'RFQ diterima.',
        Rfq::STATUS_DECLINED => 'RFQ ditolak.',
        Rfq::STATUS_EXPIRED => 'RFQ kedaluwarsa.',
    ];

    public function __construct(string $formattedNumber, string $status, ?int $rfqId = null)
    {
        $label = self::LABELS[$status] ?? $status;

        parent::__construct(
            title: "RFQ {$formattedNumber} — {$label}",
            body: self::BODIES[$status] ?? "Status berubah: {$status}",
            module: 'rfq',
            url: $rfqId ? "/rfqs#{$rfqId}" : null,
            data: [
                'formatted_number' => $formattedNumber,
                'status' => $status,
                'rfq_id' => $rfqId,
            ],
        );
    }
}
