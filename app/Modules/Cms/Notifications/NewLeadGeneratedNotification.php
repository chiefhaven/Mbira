<?php

namespace Modules\Cms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadGeneratedNotification extends Notification
{
    use Queueable;

    protected $lead;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($lead_details)
    {
        $this->lead = $lead_details;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
                ->greeting('Hello!')
                ->subject('New inquiry from '.$this->lead['name'])
                ->line($this->lead['message'])
                ->line('<br> <br> Other details are: <br>')
                ->line('Name: '.$this->lead['name'])
                ->line('Mobile: '.$this->lead['mobile'])
                ->line('Email: '.$this->lead['email']);

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
