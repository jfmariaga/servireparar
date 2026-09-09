<x-mail::message>
# Orden de trabajo entregada

Estimado/a {{ $cliente ?? 'cliente' }},

Le informamos que su orden de trabajo **{{ $numero }}** fue entregada{{ $fechaEntrega ? ' el '.$fechaEntrega : '' }}.

**Servicio:** {{ $descripcion }}

Gracias por confiar en SERVIREPARAR S.A.S.

<x-mail::subcopy>
Este es un mensaje automático. Si tiene alguna consulta, comuníquese con nuestro taller.
</x-mail::subcopy>
</x-mail::message>
