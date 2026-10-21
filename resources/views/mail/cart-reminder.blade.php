@component('mail::message')
# {{ __('mail_ui.cart.greeting', ['name' => $user->first_name ?: $user->name]) }}

{{ __('mail_ui.cart.intro') }}

@foreach ($items as $item)
- {{ $item['title'] }} × {{ $item['quantity'] }}
@endforeach

{{ __('mail_ui.cart.stock') }}

@component('mail::button', ['url' => $cartUrl])
{{ __('mail_ui.cart.button') }}
@endcomponent

{{ __('mail_ui.cart.reason') }} [{{ __('mail_ui.cart.unsubscribe') }}]({{ $unsubscribeUrl }}).

{{ __('mail_ui.common.regards') }}
**ZAFIRA — La Maison de Lumière**
@endcomponent
