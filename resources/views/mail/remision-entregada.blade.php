<x-mail::message>
# Remisión de entrega {{ $solicitud->remision->numero }}

Estimados **{{ $solicitud->cliente->nombre }}**,

Adjuntamos la remisión correspondiente a la entrega realizada el
{{ optional($solicitud->remision->entregada_en)->format('d/m/Y') }}, recibida a satisfacción por
{{ $solicitud->remision->recibido_por_nombre }}.

@if ($solicitud->remision->nota_entrega)
> {{ $solicitud->remision->nota_entrega }}
@endif

Cualquier inquietud, quedamos atentos.

Gracias,<br>
SERVIREPARAR S.A.S
</x-mail::message>
