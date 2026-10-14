@foreach ($sections() as $section)
    <x-listings.form-section :id="$section['id']" :number="$section['number']" :title="$section['title']" :note="$section['note']">
        @if ($section['note'] === null)
            <x-slot:note>
                <x-forms.required-note />
            </x-slot:note>
        @endif

        @foreach ($section['fields'] as $field)
            @if ($field['type'] === 'model-select')
                <x-listings.model-select :options="$options" :values="$values" />
            @elseif ($field['type'] === 'select')
                <x-forms.select :name="$field['name']" :label="$field['label']" :placeholder="$field['placeholder']"
                                :options="$field['options']" :required="$field['required']" :value="$values[$field['name']] ?? null" />
            @else
                <x-forms.field :name="$field['name']" :label="$field['label']" :type="$field['type']"
                               :placeholder="$field['placeholder']" :required="$field['required']"
                               :min="$field['min']" :max="$field['max']" :maxlength="$field['maxlength']" :inputmode="$field['inputmode']"
                               :value="$values[$field['name']] ?? null" />
            @endif
        @endforeach
    </x-listings.form-section>
@endforeach
