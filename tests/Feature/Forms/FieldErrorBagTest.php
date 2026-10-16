<?php

namespace Tests\Feature\Forms;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FieldErrorBagTest extends TestCase
{
    private const string FIELD = '<x-forms.field name="name" label="Name" :id="$id" :bag="$bag" :value="$value" />';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withViewErrors([]);
        $session = $this->app['session.store'];
        $session->put('_old_input', ['name' => 'Typed name']);
        $this->app['request']->setLaravelSession($session);
    }

    public function test_without_a_bag_the_default_errors_and_old_input_apply(): void
    {
        $this->shareErrors(['default' => ['name' => 'The name has already been taken.']]);

        $html = $this->render(['id' => null, 'bag' => null, 'value' => 'Row name']);

        $this->assertStringContainsString('value="Typed name"', $html);
        $this->assertStringContainsString('aria-invalid="true" aria-describedby="name-error"', $html);
        $this->assertStringContainsString('<p id="name-error" class="text-xs text-red-deep">The name has already been taken.</p>', $html);
    }

    public function test_without_a_bag_old_input_applies_even_with_no_errors(): void
    {
        $html = $this->render(['id' => null, 'bag' => null, 'value' => 'Row name']);

        $this->assertStringContainsString('value="Typed name"', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
    }

    public function test_a_bag_holding_errors_marks_the_field_and_refills_the_old_input(): void
    {
        $this->shareErrors(['maker-7' => ['name' => 'The name has already been taken.']]);

        $html = $this->render(['id' => 'edit-maker-7-name', 'bag' => 'maker-7', 'value' => 'Row name']);

        $this->assertStringContainsString('<input id="edit-maker-7-name" name="name" type="text"', $html);
        $this->assertStringContainsString('value="Typed name"', $html);
        $this->assertStringContainsString('aria-invalid="true" aria-describedby="edit-maker-7-name-error"', $html);
        $this->assertStringContainsString('<p id="edit-maker-7-name-error" class="text-xs text-red-deep">The name has already been taken.</p>', $html);
    }

    public function test_an_empty_bag_shows_the_value_and_ignores_other_bags_errors_and_old_input(): void
    {
        $this->shareErrors(['default' => ['name' => 'The name field is required.'], 'maker-8' => ['name' => 'Taken.']]);

        $html = $this->render(['id' => 'edit-maker-7-name', 'bag' => 'maker-7', 'value' => 'Row name']);

        $this->assertStringContainsString('value="Row name"', $html);
        $this->assertStringNotContainsString('Typed name', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('text-red-deep', $html);
    }

    /**
     * @param  array<string, array<string, string>>  $bags
     */
    private function shareErrors(array $bags): void
    {
        $errors = new ViewErrorBag;

        foreach ($bags as $name => $messages) {
            $errors->put($name, new MessageBag($messages));
        }

        $this->app['view']->share('errors', $errors);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(array $data): string
    {
        return preg_replace('/\s+>/', '>', (string) $this->blade(self::FIELD, $data));
    }
}
