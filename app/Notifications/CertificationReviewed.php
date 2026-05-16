<?php

namespace App\Notifications;

use App\Models\TechnicianAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificationReviewed extends Notification
{
    use Queueable;

    protected TechnicianAsset $asset;

    public function __construct(TechnicianAsset $asset)
    {
        $this->asset = $asset;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isApproved = $this->asset->status === 'approved';

        return [
            'type'             => 'certification_reviewed',
            'asset_id'         => $this->asset->id,
            'status'           => $this->asset->status,
            'rejection_reason' => $this->asset->rejection_reason,
            'title'            => $isApproved
                ? '¡Tu certificado ha sido aprobado!'
                : 'Tu certificado ha sido rechazado',
            'message'          => $isApproved
                ? 'Tu certificación ha sido revisada y aprobada por el equipo de Fixxa.'
                : 'Tu certificación fue rechazada. Motivo: ' . ($this->asset->rejection_reason ?? 'Sin especificar'),
        ];
    }
}
