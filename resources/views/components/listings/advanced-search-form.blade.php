<form method="GET" action="{{ route('listings.advanced') }}" {{ $attributes->merge(['class' => 'rounded-xs border border-rule bg-card p-5 shadow-sm md:p-8']) }}>
    <div>
        @foreach ($fieldsets() as $fieldset)
            <x-listings.advanced-fieldset :title="$fieldset['title']" :note="$fieldset['note']">
                @foreach ($fieldset['fields'] as $field)
                    @if ($field['type'] === 'select')
                        <x-forms.select :name="$field['name']" :label="$field['label']" :placeholder="$field['placeholder']"
                                        :options="$field['options']" :value="$field['value']" />
                    @else
                        <x-forms.field :name="$field['name']" :label="$field['label']" :type="$field['type']"
                                       :placeholder="$field['placeholder']" :value="$field['value']"
                                       :min="$field['min']" :max="$field['max']" :maxlength="$field['maxlength']" :inputmode="$field['inputmode']" />
                    @endif
                @endforeach
            </x-listings.advanced-fieldset>
        @endforeach
    </div>

    <div class="flex justify-end gap-3 border-t border-rule pt-6">
        <x-ui.button variant="ghost" size="responsive" :href="route('listings.advanced')">{{ __('app.listings.advanced.clear') }}</x-ui.button>
        <div class="hidden md:block">
            <x-ui.button variant="primary" type="submit">{{ __('app.listings.advanced.search') }}</x-ui.button>
        </div>
    </div>

    <div data-sticky-bar class="sticky bottom-0 z-10 -mx-5 mt-5 -mb-5 border-t border-rule bg-paper p-5 md:hidden">
        <x-ui.button variant="primary" type="submit" size="lg" class="w-full">{{ trans_choice('app.listings.advanced.show', $total, ['count' => $total]) }}</x-ui.button>
    </div>
</form>
