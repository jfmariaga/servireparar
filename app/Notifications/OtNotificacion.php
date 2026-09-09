<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso genérico del módulo de OT para la campana in-app (canal `database`).
 * Phase 11 / D5 — micro-slice del spec 008; se puede especializar sin cambiar la
 * forma de los datos (`titulo`, `cuerpo`, `url`, `icono`).
 */
class OtNotificacion extends Notification
{
    use Queueable;

    public function __construct(
        public string $titulo,
        public string $cuerpo,
        public ?string $url = null,
        public string $icono = 'ot',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'url' => $this->url,
            'icono' => $this->icono,
        ];
    }
}
