<x-mail::message>
# Password reset

Hello {{ $name }},

A password reset was requested for your Batang Surigaonon Scholar's App account.

@if(!empty($resetUrl))
Use this secure link to continue. It expires in **{{ $minutes }} minutes** and can be used only once.

<x-mail::button :url="$resetUrl">
Reset password
</x-mail::button>

Or enter this verification code on the recovery page:
@else
Enter this verification code on the password recovery page:
@endif

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

Do not share this email or code. If you did not request a password reset, you can ignore this message. Your current password will stay the same.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
