<x-mail::message>
# Verify backup recovery email

Hello {{ $name }},

An authorized administrator requested that **{{ $pendingEmail }}** be used as a backup recovery email for Forgot Password on the Batang Surigaonon Scholar’s App.

This address will only be enabled for password recovery after you confirm it. The link expires in {{ $expireMinutes }} minutes and can be used once.

<x-mail::button :url="$verificationUrl">
Verify backup email
</x-mail::button>

If you did not request this change, ignore this message. Your current backup recovery settings will stay unchanged.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
