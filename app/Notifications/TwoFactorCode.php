<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCode extends Notification
{
    public function __construct(public string $code, public int $minutes)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de verificação: '.$this->code)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Use o código abaixo para concluir a verificação em duas etapas no '.config('app.name').'.')
            ->line('**'.$this->code.'**')
            ->line('O código vale por '.$this->minutes.' minutos. Se não foi você que tentou entrar, troque sua senha.')
            ->salutation('Equipe '.config('app.name'));
    }
}
