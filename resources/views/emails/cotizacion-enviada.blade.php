<x-mail::message>
# Cotización {{ $numero }}

Estimado/a {{ $cliente ?? 'cliente' }},

Adjuntamos la cotización solicitada por un valor de **{{ \App\Support\Moneda::cop($total) }}**.

Puede responder este mismo correo para aceptarla, rechazarla o solicitar ajustes.

Gracias por confiar en SERVIREPARAR S.A.S.

<x-mail::subcopy>
Este es un mensaje automático. Responda directamente a este correo para que quede registrado en el caso.
</x-mail::subcopy>
</x-mail::message>
