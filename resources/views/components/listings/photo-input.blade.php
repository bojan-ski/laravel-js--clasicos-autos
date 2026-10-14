@php
    $max = config('listings.images.max');
    $hasError = $errors->has('images') || $errors->has('images.*');
    $error = $errors->first('images') ?: $errors->first('images.*');
    $describedBy = 'images-notes'.($hasError ? ' images-error' : '');
    $button = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xs border border-rule font-heading leading-tight text-ink transition-colors duration-150 hover:bg-ink/7 active:bg-ink/14 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-gold-ink';
@endphp

<div data-photo-input
     data-count-one="{{ __('app.listings.form.photos.count_one') }}"
     data-count-other="{{ __('app.listings.form.photos.count_other') }}"
     role="group" aria-labelledby="images-label"
     {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <p id="images-label" class="text-xs text-ink/70">{{ __('app.listings.form.fields.images.label', ['max' => $max]) }} <span aria-hidden="true">*</span><span class="sr-only">({{ __('app.forms.required') }})</span></p>

    @if ($errors->any())
        <p class="text-sm text-ink-2">{{ __('app.listings.form.photos.reselect') }}</p>
    @endif

    <div data-photo-drop class="hidden flex-col items-center gap-3 rounded-xs border-2 border-dashed border-gold bg-card px-6 py-8 text-center data-dragging:bg-gold/10 md:flex">
        <x-icons.image class="size-8 text-gold-ink" />
        <p class="font-heading text-xl">{{ __('app.listings.form.photos.drop') }}</p>
        <label class="{{ $button }} min-h-9 px-3.5 py-2 text-sm">
            {{ __('app.listings.form.photos.choose') }}
            <input id="images-desktop" type="file" name="images[]" accept="image/jpeg,image/png" multiple class="sr-only"
                   aria-describedby="{{ $describedBy }}" @if ($hasError) aria-invalid="true" @endif>
        </label>
    </div>

    <div class="flex flex-col gap-3 md:hidden">
        <p class="font-heading text-xl">{{ __('app.listings.form.photos.mobile_heading') }}</p>
        <div class="grid grid-cols-2 gap-3">
            <label class="{{ $button }} min-h-12 px-6 py-3 text-base">
                {{ __('app.listings.form.photos.camera') }}
                <input id="images-camera" type="file" name="images[]" accept="image/jpeg,image/png" capture="environment" class="sr-only"
                       aria-describedby="{{ $describedBy }}" @if ($hasError) aria-invalid="true" @endif>
            </label>
            <label class="{{ $button }} min-h-12 px-6 py-3 text-base">
                {{ __('app.listings.form.photos.library') }}
                <input id="images-library" type="file" name="images[]" accept="image/jpeg,image/png" multiple class="sr-only"
                       aria-describedby="{{ $describedBy }}" @if ($hasError) aria-invalid="true" @endif>
            </label>
        </div>
    </div>

    <div id="images-notes" class="flex flex-col gap-0.5 text-xs text-muted">
        <p>{{ __('app.listings.form.photos.max_note', ['max' => $max]) }}</p>
        <p>{{ __('app.listings.form.photos.resize_note') }}</p>
    </div>

    <p data-photo-status aria-live="polite" class="text-sm text-ink-2"></p>

    @if ($hasError)
        <p id="images-error" class="text-xs text-red-deep">{{ $error }}</p>
    @endif
</div>
