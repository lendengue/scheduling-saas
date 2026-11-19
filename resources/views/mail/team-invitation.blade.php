<x-mail::message>
# Join {{ $tenantName }} on scheduling-saas

@if ($inviterName)
{{ $inviterName }} invited you to join **{{ $tenantName }}** as **{{ $role }}**.
@else
You have been invited to join **{{ $tenantName }}** as **{{ $role }}**.
@endif

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

This link expires on {{ $expiresAt->toDayDateTimeString() }} (UTC). If you were not expecting this invitation, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
