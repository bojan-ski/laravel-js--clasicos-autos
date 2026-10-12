<x-layouts.guest :title="$linkValid ? __('app.auth.reset.title') : __('app.auth.reset.invalid_title')" width="wide">
    @if ($linkValid)
        <x-auth.reset-form :token="$token" :email="$email" />
    @else
        <x-auth.invalid-link />
    @endif
</x-layouts.guest>
