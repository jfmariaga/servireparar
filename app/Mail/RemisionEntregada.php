<?php

namespace App\Mail;

use App\Models\SolicitudDespacho;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Copia de la remisión de entrega al correo del cliente (spec 003, US6): se
 * envía automáticamente al confirmar la entrega recibida a satisfacción, con el
 * PDF de la remisión adjunto.
 */
class RemisionEntregada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudDespacho $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Remisión de entrega '.$this->solicitud->remision->numero.' — SERVIREPARAR',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.remision-entregada',
            with: ['solicitud' => $this->solicitud],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.remision-entrega', ['solicitud' => $this->solicitud]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'remision-'.$this->solicitud->remision->numero.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
