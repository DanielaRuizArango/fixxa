<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseResponded extends Notification
{
    use Queueable;

    protected $response;
    protected $technician;
    protected $serviceCase;

    /**
     * Create a new notification instance.
     */
    public function __construct($response, $technician, $serviceCase)
    {
        $this->response = $response;
        $this->technician = $technician;
        $this->serviceCase = $serviceCase;
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
        return [
            'type'            => 'case_responded',
            'response_id'     => $this->response->id,
            'service_case_id' => $this->serviceCase->id,
            'technician_name' => $this->technician->user->name,
            'case_title'      => $this->serviceCase->title,
            'estimated_cost'  => $this->response->estimated_cost,
            'title'           => 'Tu caso "' . $this->serviceCase->title . '" ha sido respondido',
        ];
    }
}
