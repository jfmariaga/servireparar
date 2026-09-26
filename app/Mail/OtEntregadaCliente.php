<?php

namespace App\Mail;

use App\Models\OrdenTrabajo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su OT fue entregada (spec 002, FR-011). Phase 11.
 * Encolado (spec 008, T027): si el envío falla, Laravel lo reintenta según
 * `$tries`/`backoff()` y lo deja en `failed_jobs` para reintento manual en vez
 * de perder el aviso silenciosamente.
 */
class OtEntregadaCliente extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public OrdenTrabajo $ot) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su orden de trabajo '.$this->ot->numero_ot.' fue entregada',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ot-entregada',
            with: [
                'numero' => $this->ot->numero_ot,
                'cliente' => $this->ot->cliente?->nombre,
                'descripcion' => $this->ot->descripcion,
                'fechaEntrega' => optional($this->ot->fecha_entrega)->format('d/m/Y'),
            ],
        );
    }
}
