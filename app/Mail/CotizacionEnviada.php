<?php

namespace App\Mail;

use App\Models\Cotizacion;
use App\Services\Cotizaciones\GenerarPdfCotizacionService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Cotización enviada al cliente (spec 006, FR-004). El Message-ID se fija
 * explícitamente (no lo genera el MTA) para poder guardarlo en
 * `mensajes_cotizacion.message_id_correo` y matchear la respuesta del
 * cliente en el mismo hilo (US3).
 */
class CotizacionEnviada extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Cotizacion $cotizacion,
        public string $messageIdCorreo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización '.$this->cotizacion->numero.' — SERVIREPARAR',
        );
    }

    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageIdCorreo);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cotizacion-enviada',
            with: [
                'numero' => $this->cotizacion->numero,
                'cliente' => $this->cotizacion->cliente?->nombre,
                'total' => $this->cotizacion->total,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = app(GenerarPdfCotizacionService::class)->generar($this->cotizacion);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Cotizacion-'.$this->cotizacion->numero.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
