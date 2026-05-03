<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TechnicianRated extends Notification
{
    use Queueable;

    protected $rating;
    protected $client;
    protected $serviceCase;

    /**
     * Create a new notification instance.
     */
    public function __construct($rating, $client, $serviceCase)
    {
        $this->rating = $rating;
        $this->client = $client;
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
            'type'            => 'technician_rated',
            'rating_id'       => $this->rating->id,
            'service_case_id' => $this->serviceCase->id,
            'client_name'     => $this->client->user->name,
            'score'           => $this->rating->score,
            'case_title'      => $this->serviceCase->title,
            'title'           => 'Has recibido una nueva calificación',
            'message'         => 'El cliente ' . $this->client->user->name . ' te ha calificado con ' . $this->rating->score . ' estrellas.',
        ];
    }
}
