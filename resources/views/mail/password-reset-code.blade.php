<x-mail::message>
# Password reset verification code

Hello {{ $name }},

A password reset was requested for your Batang Surigaonon Scholar's App account. Enter this verification code on the password recovery page. It is the same code the application will check.

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

This code expires in **{{ $minutes }} minutes** and can be used only once. Type the digits exactly as shown. Do not share this code.

If you did not request a password reset, you can ignore this email. Your current password will stay the same.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
