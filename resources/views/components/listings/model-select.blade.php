@props(['options'])

<div data-model-select {{ $attributes->merge(['class' => 'contents']) }}>
    <x-forms.select name="car_maker_id" :label="__('app.listings.form.fields.car_maker_id.label')"
                    :placeholder="__('app.listings.form.fields.car_maker_id.placeholder')"
                    :options="$options->makers" required data-model-select-maker />
    <x-forms.select name="car_model_id" :label="__('app.listings.form.fields.car_model_id.label')"
                    :placeholder="__('app.listings.form.fields.car_model_id.placeholder')"
                    :options="$options->models" required data-model-select-model />
    <div data-model-select-other>
        <x-forms.field name="model" :label="__('app.listings.form.fields.model.label')"
                       :placeholder="__('app.listings.form.fields.model.placeholder')"
                       :hint="__('app.listings.form.fields.model.hint')" maxlength="255" />
    </div>
    <script type="application/json" data-model-select-config>@json($options->modelConfig)</script>
</div>
