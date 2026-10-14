<?php

namespace Tests\Feature\Forms;

use Tests\TestCase;

class FormSelectTest extends TestCase
{
    private const string SELECT = '<x-forms.select name="transmission" label="Transmission" :options="$options" placeholder="Select the transmission" :value="$value" />';

    /**
     * @var array<string, string>
     */
    private const array OPTIONS = ['Manual' => 'Stick shift', 'Automatic' => 'Self-shifting'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withViewErrors([]);
    }

    public function test_it_renders_the_label_and_each_option_with_its_label(): void
    {
        $html = $this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => '']);

        $this->assertStringContainsString('<label for="transmission"', $html);
        $this->assertStringContainsString('<select id="transmission" name="transmission"', $html);
        $this->assertMatchesRegularExpression('/<option value="Manual">Stick shift<\/option>\s*<option value="Automatic">Self-shifting<\/option>/', $html);
    }

    public function test_the_placeholder_is_a_first_selectable_empty_option(): void
    {
        $html = $this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => '']);

        $this->assertMatchesRegularExpression('/<select[^>]*>\s*<option value="">Select the transmission<\/option>\s*<option value="Manual">/', $html);
        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringNotContainsString('selected', $html);
    }

    public function test_without_a_placeholder_there_is_no_empty_option(): void
    {
        $html = $this->render('<x-forms.select name="transmission" label="Transmission" :options="$options" />', ['options' => self::OPTIONS]);

        $this->assertStringNotContainsString('<option value="">', $html);
    }

    public function test_the_option_equal_to_the_value_is_selected_comparing_as_strings(): void
    {
        $html = $this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => 'Automatic']);
        $this->assertSame(['Automatic'], $this->selectedValues($html));

        $html = $this->render('<x-forms.select name="car_maker" label="Car Maker" :options="$options" :value="$value" />', ['options' => [7 => 'Buick', 12 => 'Cadillac'], 'value' => '12']);
        $this->assertSame(['12'], $this->selectedValues($html));
    }

    public function test_old_input_wins_over_the_passed_value(): void
    {
        $this->withOldInput(['transmission' => 'Manual']);

        $this->assertSame(['Manual'], $this->selectedValues($this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => 'Automatic'])));
    }

    public function test_an_array_old_input_is_ignored_and_the_passed_value_applies(): void
    {
        $this->withOldInput(['transmission' => ['Manual']]);

        $this->assertSame(['Automatic'], $this->selectedValues($this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => 'Automatic'])));
    }

    public function test_an_error_marks_the_select_and_describes_it(): void
    {
        $this->withViewErrors(['transmission' => 'The Transmission field is invalid.']);

        $html = $this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => '']);

        $this->assertStringContainsString('aria-invalid="true" aria-describedby="transmission-error"', $html);
        $this->assertStringContainsString('border-red', $html);
        $this->assertStringNotContainsString('border-rule-input', $html);
        $this->assertStringContainsString('<p id="transmission-error" class="text-xs text-red-deep">The Transmission field is invalid.</p>', $html);
    }

    public function test_without_an_error_the_select_is_not_marked_invalid(): void
    {
        $html = $this->render(self::SELECT, ['options' => self::OPTIONS, 'value' => '']);

        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('aria-describedby', $html);
        $this->assertStringContainsString('border-rule-input', $html);
    }

    public function test_a_hint_and_extra_attributes_reach_the_select(): void
    {
        $html = $this->render('<x-forms.select name="transmission" label="Transmission" :options="$options" hint="Pick one" data-probe="select" required />', ['options' => self::OPTIONS]);

        $this->assertStringContainsString('aria-describedby="transmission-hint"', $html);
        $this->assertStringContainsString('<p id="transmission-hint" class="text-xs text-muted">Pick one</p>', $html);
        $this->assertStringContainsString('required aria-required="true"', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*data-probe="select"/', $html);
    }

    public function test_an_array_entry_renders_as_an_optgroup_and_flat_entries_stay_options(): void
    {
        $html = $this->render('<x-forms.select name="car_model_id" label="Model" :options="$options" placeholder="Select car model" />', [
            'options' => ['Buick' => [3 => 'Roadmaster'], 'Chevrolet' => [7 => 'Bel Air', 9 => 'Impala'], 'other' => 'Other (specify)'],
        ]);

        $this->assertMatchesRegularExpression(
            '/<option value="">Select car model<\/option>\s*<optgroup label="Buick">\s*<option value="3">Roadmaster<\/option>\s*<\/optgroup>\s*<optgroup label="Chevrolet">\s*<option value="7">Bel Air<\/option>\s*<option value="9">Impala<\/option>\s*<\/optgroup>\s*<option value="other">Other \(specify\)<\/option>/',
            $html,
        );
    }

    public function test_old_input_selects_an_option_inside_a_group(): void
    {
        $this->withOldInput(['car_model_id' => '9']);

        $html = $this->render('<x-forms.select name="car_model_id" label="Model" :options="$options" :value="$value" />', [
            'options' => ['Chevrolet' => [7 => 'Bel Air', 9 => 'Impala'], 'other' => 'Other (specify)'],
            'value' => 'other',
        ]);

        $this->assertSame(['9'], $this->selectedValues($html));
    }

    public function test_the_field_ignores_an_array_old_input_and_keeps_a_scalar_one(): void
    {
        $field = '<x-forms.field name="model" label="Model" :value="$value" />';

        $this->withOldInput(['model' => ['Bel Air']]);
        $html = $this->render($field, ['value' => 'Impala']);
        $this->assertStringContainsString('value="Impala"', $html);
        $this->assertStringNotContainsString('Array', $html);

        $this->withOldInput(['model' => 'Bel Air']);
        $this->assertStringContainsString('value="Bel Air"', $this->render($field, ['value' => 'Impala']));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function withOldInput(array $input): void
    {
        $session = $this->app['session.store'];
        $session->put('_old_input', $input);
        $this->app['request']->setLaravelSession($session);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $template, array $data): string
    {
        return preg_replace('/\s+>/', '>', (string) $this->blade($template, $data));
    }

    /**
     * @return list<string>
     */
    private function selectedValues(string $html): array
    {
        preg_match_all('/<option value="([^"]*)" selected>/', $html, $matches);

        return $matches[1];
    }
}
