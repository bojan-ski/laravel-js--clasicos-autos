<x-layouts.app :seo="$seo">
    <x-home.hero />
    <x-home.about />
    @if ($latestListings->isNotEmpty())
        <x-home.latest-listings :listings="$latestListings" />
    @endif
</x-layouts.app>
