@props([
    'listing',
    'image',
    'number',
    'primary',
])

<li {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    <x-ui.postcard>
        <img src="{{ $image->thumbUrl() }}" alt="{{ __('app.listings.images.alt', ['name' => $listing->name, 'number' => $number]) }}"
             width="320" height="240" loading="lazy" class="photo-grade h-25 w-full object-cover md:h-45">
    </x-ui.postcard>

    <div class="mt-3 flex flex-wrap gap-2">
        @if ($primary)
            <p class="flex min-h-12 w-full items-center justify-center border border-red px-3 text-center text-xs text-red md:min-h-9">
                {{ __('app.listings.images.primary_tag') }}
            </p>
        @else
            <form method="POST" action="{{ route('listings.images.primary', [$listing, $image]) }}" class="flex flex-1">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit" variant="secondary" size="responsive" class="flex-1">
                    {{ __('app.listings.images.make_primary') }}
                    <span class="sr-only">{{ __('app.listings.images.image_number', ['number' => $number]) }}</span>
                </x-ui.button>
            </form>

            <x-ui.button variant="destructive" size="responsive" class="flex-1"
                         :data-dialog-open="'delete-image-'.$image->id" :commandfor="'delete-image-'.$image->id" command="show-modal">
                {{ __('app.listings.images.delete') }}
                <span class="sr-only">{{ __('app.listings.images.image_number', ['number' => $number]) }}</span>
            </x-ui.button>

            <x-ui.dialog :id="'delete-image-'.$image->id" :title="__('app.listings.images.dialog_title')">
                <form method="POST" action="{{ route('listings.images.destroy', [$listing, $image]) }}" class="mt-5 flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                                 data-dialog-close :commandfor="'delete-image-'.$image->id" command="close">
                        {{ __('app.listings.images.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                        {{ __('app.listings.images.delete') }}
                        <span class="sr-only">{{ __('app.listings.images.image_number', ['number' => $number]) }}</span>
                    </x-ui.button>
                </form>
            </x-ui.dialog>
        @endif
    </div>
</li>
