<x-mail::message>
# {{ $title }}

Hello {{ $recipientName }},

{{ $messageBody }}

@if($actionUrl !== '')
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Account and security messages are always sent. Other messages follow the notification settings saved in BSSA. This link still requires you to sign in with the correct account.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
