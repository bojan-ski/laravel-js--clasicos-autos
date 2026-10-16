@props([
    'id',
    'title',
])

<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title"
        {{ $attributes->merge(['class' => 'm-auto w-[calc(100%-40px)] max-w-110 rounded-xs bg-card p-8 text-ink shadow-lg backdrop:bg-[rgb(45_43_43/.5)] motion-public:open:animate-dialog-in motion-public:backdrop:animate-fade-in']) }}>
    <h2 id="{{ $id }}-title" class="text-[26px] leading-tight text-red italic">{{ $title }}</h2>

    {{ $slot }}
</dialog>
