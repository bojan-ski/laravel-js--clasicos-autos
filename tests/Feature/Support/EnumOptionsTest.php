<?php

namespace Tests\Feature\Support;

use App\Enums\EngineCondition;
use App\Support\EnumOptions;
use Tests\TestCase;

class EnumOptionsTest extends TestCase
{
    public function test_it_maps_each_case_value_to_its_label_in_case_order(): void
    {
        __('app.listings.options.engine_condition.running');
        app('translator')->addLines([
            'app.listings.options.engine_condition.running' => 'Runs fine',
            'app.listings.options.engine_condition.not_running' => 'Does not run',
        ], 'en');

        $this->assertSame([
            'Running' => 'Runs fine',
            'Needs Tuning' => 'Needs Tuning',
            'Not Running' => 'Does not run',
            'Rebuilt Engine' => 'Rebuilt Engine',
            'Original Factory Engine' => 'Original Factory Engine',
        ], EnumOptions::for(EngineCondition::class));
    }
}
