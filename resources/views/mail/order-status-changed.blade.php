@component('mail::message')
# {{ __('mail_ui.common.greeting', ['name' => $order->user?->name]) }}

{{ $message }}

- **{{ __('mail_ui.order.number') }}** #{{ $order->id }}
- **{{ __('mail_ui.order.total') }}** Rp {{ number_format((int) $order->total, 0, ',', '.') }}

{{ __('mail_ui.common.regards') }}
**ZAFIRA — La Maison de Lumière**
@endcomponent
