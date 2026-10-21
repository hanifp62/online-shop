@component('mail::message')
# {{ __('mail_ui.common.greeting', ['name' => $request->name]) }}

{{ __('mail_ui.concierge.received') }}

**{{ __('mail_ui.concierge.details') }}**
- **{{ __('mail_ui.concierge.service') }}** {{ $request->service }}
@if ($request->preferred_date)
- **{{ __('mail_ui.concierge.date') }}** {{ \Carbon\Carbon::parse($request->preferred_date)->translatedFormat('l, d F Y') }}
@endif
@if ($request->preferred_time)
- **{{ __('mail_ui.concierge.time') }}** {{ $request->preferred_time }}
@endif
@if ($request->notes)
- **{{ __('mail_ui.concierge.notes') }}** {{ $request->notes }}
@endif

{{ __('mail_ui.concierge.contact_before') }} **{{ $request->phone }}** {{ __('mail_ui.concierge.contact_after') }}

{{ __('mail_ui.concierge.reply') }}

{{ __('mail_ui.common.regards') }}
**ZAFIRA — La Maison de Lumière**
@endcomponent
