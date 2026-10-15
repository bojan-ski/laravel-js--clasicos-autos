@php($hasError = $errors->has('message'))

<form method="POST" action="{{ $action }}" data-composer data-sticky-bar
      {{ $attributes->merge(['class' => 'sticky bottom-(--cookie-banner-h,0px) z-10 -mx-5 -mb-6 flex flex-col gap-1.5 border-t-2 border-dashed border-gold bg-paper px-5 py-4 md:static md:mx-0 md:mb-0 md:bg-transparent md:px-0']) }}>
    @csrf
    <label for="message" class="text-xs text-ink/70">{{ __('app.conversations.composer.label') }}</label>
    <div class="flex items-start gap-3">
        <input id="message" name="message" type="text" value="{{ old('message') }}" autocomplete="off"
               required minlength="{{ $min() }}" maxlength="{{ $max() }}"
               placeholder="{{ __('app.conversations.composer.placeholder') }}"
               aria-describedby="message-counter{{ $hasError ? ' message-error' : '' }}"
               @if ($hasError) aria-invalid="true" @endif
               data-composer-input
               @class([
                   'h-12 min-w-0 flex-1 rounded-xs border bg-card-input px-3 text-base text-ink placeholder:text-muted md:h-11 md:text-sm',
                   'border-rule-input' => ! $hasError,
                   'border-red' => $hasError,
               ])>
        <x-ui.button type="submit" variant="primary" size="responsive" data-composer-submit>{{ __('app.conversations.composer.send') }}</x-ui.button>
    </div>
    <p id="message-counter" class="text-xs text-muted tabular-nums" data-composer-counter
       data-template="{{ $counterTemplate() }}" data-min="{{ $min() }}" data-max="{{ $max() }}">{{ $counter() }}</p>
    @if ($hasError)
        <p id="message-error" class="text-xs text-red-deep">{{ $errors->first('message') }}</p>
    @endif
</form>
