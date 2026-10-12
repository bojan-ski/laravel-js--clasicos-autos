<x-layouts.guest :title="__('app.auth.reset.title')" width="wide">
    @if ($linkValid)
        <x-auth.reset-form :token="$token" :email="$email" />
    @else
        <x-auth.invalid-link />
    @endif
</x-layouts.guest>
