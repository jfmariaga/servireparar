<?php

namespace App\Mail;

use App\Models\OrdenTrabajo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su OT fue entregada (spec 002, FR-011). Phase 11.
 */
class OtEntregadaCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenTrabajo $ot) {}

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
