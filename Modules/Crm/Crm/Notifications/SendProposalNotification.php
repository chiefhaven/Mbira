<?php

namespace Modules\Crm\Notifications;

use App\Utils\NotificationUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendProposalNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $proposal;

    public $media;

    public function __construct($proposal)
    {
        $notificationUtil = new NotificationUtil();
        $notificationUtil->configureEmail();

        $this->proposal = $proposal;
        $this->media = $proposal->media;
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
                ->subject($this->proposal->subject)
                ->view(
                    'emails.plain_html',
                    ['content' => $this->proposal->body]
                );

        if (! empty($this->proposal->cc)) {
            $mail->cc(explode(',', $this->proposal->cc));
        }
        if (! empty($this->proposal->bcc)) {
            $mail->bcc(explode(',', $this->proposal->bcc));
        }

        if ($this->media->count() > 0) {
            foreach ($this->media as $media) {
                $mail->attach(public_path('uploads').'/media/'.$media->file_name, ['as' => $media->display_name]);
            }
        }

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
