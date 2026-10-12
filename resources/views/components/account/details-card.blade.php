@props([
    'user',
    'accountCreated',
])

@php
    $rows = [
        'username' => $user->username,
        'email' => $user->email,
        'phone_number' => $user->phone_number,
        'created_at' => $accountCreated,
    ];
@endphp

<section aria-labelledby="account-details-title" {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-xs border border-rule bg-card p-6 shadow-sm md:p-8']) }}>
    <h2 id="account-details-title" class="text-2xl text-red italic">{{ __('app.profile.details.title') }}</h2>

    <dl class="flex flex-col">
        @foreach ($rows as $key => $value)
            <div class="flex justify-between gap-4 border-b border-rule-strong py-2 text-sm">
                <dt class="shrink-0 text-muted">{{ __("app.profile.details.{$key}") }}</dt>
                <dd class="text-right break-all text-ink">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
</section>
