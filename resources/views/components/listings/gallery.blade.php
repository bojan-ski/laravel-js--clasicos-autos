@props(['images', 'name'])

<x-ui.postcard {{ $attributes }}>
    @if ($images->isEmpty())
        <div class="flex aspect-video w-full items-center justify-center border border-rule bg-paper text-muted">
            <x-icons.image class="size-12" />
        </div>
    @else
        <div data-gallery class="flex flex-col gap-3">
            <img data-gallery-main src="{{ $images->first()->url() }}" alt="{{ $name }}" width="1600" height="900"
                 class="aspect-video w-full object-cover photo-grade">

            @if ($images->count() > 1)
                <ul class="flex flex-wrap gap-2.5" aria-label="{{ __('app.listings.show.gallery.label') }}">
                    @foreach ($images as $image)
                        <li>
                            <a href="{{ $image->url() }}" data-gallery-thumb @if ($loop->first) aria-current="true" @endif
                               class="block border border-rule aria-[current=true]:border-transparent aria-[current=true]:outline-2 aria-[current=true]:outline-offset-2 aria-[current=true]:outline-red">
                                <img src="{{ $image->thumbUrl() }}" alt="{{ __('app.listings.show.gallery.thumb_alt', ['name' => $name, 'number' => $loop->iteration]) }}"
                                     width="320" height="240" class="aspect-4/3 w-21 object-cover photo-grade">
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-ui.postcard>
