<?php

namespace Tests\Feature\Admin;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MakerModelsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login_and_an_app_user_gets_403(): void
    {
        $maker = CarMaker::factory()->create();

        $this->get(route('admin.makers.show', $maker))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.makers.show', $maker))->assertForbidden();
    }

    public function test_the_page_renders_the_back_link_kicker_maker_name_and_add_form(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);

        $html = $this->page($maker)->assertViewIs('pages.admin.makers.show')->getContent();

        $this->assertStringContainsString('<a href="'.route('admin.makers').'"', $html);
        $this->assertStringContainsString('All makers', $html);
        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Modelos/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Chevrolet</h1>', $html);
        $this->assertStringContainsString('action="'.route('admin.makers.models.store', $maker).'"', $html);
        $this->assertMatchesRegularExpression('/<input id="year_from" name="year_from" type="number"[^>]*inputmode="numeric" min="1900" max="1989"/', $html);
        $this->assertMatchesRegularExpression('/<input id="year_to" name="year_to" type="number"[^>]*aria-describedby="year_to-hint"/', $html);
        $this->assertStringContainsString('<p id="year_to-hint" class="text-xs text-muted">Between 1900 and 1989.</p>', $html);
        $this->assertStringNotContainsString('border-status-info', $html);
        $this->assertStringNotContainsString('This maker is inactive', $html);
    }

    public function test_an_inactive_maker_is_marked_under_the_title(): void
    {
        $html = $this->page(CarMaker::factory()->inactive()->create())->getContent();

        $this->assertStringContainsString('border-status-info text-status-info bg-status-info/10', $html);
        $this->assertStringContainsString('<p class="text-sm text-ink-2">This maker is inactive, so its models stay out of the listing form until it is reactivated.</p>', $html);
    }

    public function test_only_this_makers_models_are_listed_by_name_active_and_inactive(): void
    {
        $maker = CarMaker::factory()->create();
        $impala = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Impala']);
        $belAir = CarModel::factory()->inactive()->for($maker, 'maker')->create(['name' => 'Bel Air']);
        CarModel::factory()->create(['name' => 'Corvair']);

        $this->assertSame([$belAir->id, $impala->id], $this->page($maker)->viewData('models')->pluck('id')->all());
    }

    public function test_a_row_shows_years_and_counts_only_its_linked_listings_in_every_status(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1975]);
        CarListing::factory()->approved()->forCarModel($model)->create();
        CarListing::factory()->rejected()->forCarModel($model)->create();
        CarListing::factory()->forCarModel($model)->create();
        CarListing::factory()->customModel()->for($maker, 'maker')->create(['model' => 'Bel Air']);

        $row = $this->row($this->page($maker)->getContent(), $model);

        $this->assertStringContainsString('<span class="min-w-0 font-heading text-lg text-ink italic wrap-break-word">Bel Air</span>', $row);
        $this->assertMatchesRegularExpression('/uppercase lg:hidden">Years<\/span>\s*1950–1975\s*<\/td>/', $row);
        $this->assertMatchesRegularExpression('/uppercase lg:hidden">Listings<\/span>\s*3\s*<\/td>/', $row);
        $this->assertStringNotContainsString('delete-model-', $row);
        $this->assertStringContainsString('<p class="text-xs text-ink-2">Saving also renames its 3 listings, and the years must include theirs.</p>', $row);
    }

    public function test_a_model_without_listings_offers_delete_and_no_rename_hint(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->inactive()->for($maker, 'maker')->create(['name' => 'Nomad']);

        $row = $this->row($this->page($maker)->getContent(), $model);

        $this->assertMatchesRegularExpression('/data-dialog-open="delete-model-'.$model->id.'"[^>]*>\s*Delete<span class="sr-only"> — Nomad<\/span>/', $row);
        $this->assertStringContainsString('Delete Nomad?', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.models.destroy', [$maker, $model]).'"', $row);
        $this->assertMatchesRegularExpression('/border-red-deep text-red-deep[^"]*">\s*Delete model\s*<\/button>/', $row);
        $this->assertMatchesRegularExpression('/uppercase">Inactive<\/span>/', $row);
        $this->assertMatchesRegularExpression('/border-red text-red[^"]*">\s*Activate<span class="sr-only"> — Nomad<\/span>/', $row);
        $this->assertStringNotContainsString('Saving also renames', $row);
    }

    public function test_a_single_listing_reads_in_the_singular_and_the_edit_dialog_is_prefilled(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Nomad', 'year_from' => 1955, 'year_to' => 1961]);
        CarListing::factory()->forCarModel($model)->create();

        $row = $this->row($this->page($maker)->getContent(), $model);
        $dialog = 'edit-model-'.$model->id;

        $this->assertStringContainsString('<h2 id="'.$dialog.'-title" class="text-[26px] leading-tight text-red italic">Edit Nomad</h2>', $row);
        $this->assertMatchesRegularExpression('/Saving also renames its 1 listing, and the years must include its year\.<\/p>\s*<div class="flex flex-col gap-1.5">\s*<label for="'.$dialog.'-name"/', $row);
        $this->assertMatchesRegularExpression('/data-dialog-open="'.$dialog.'"[^>]*>\s*Edit<span class="sr-only"> — Nomad<\/span>/', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.models.update', [$maker, $model]).'"', $row);
        $this->assertMatchesRegularExpression('/<input id="'.$dialog.'-name" name="name" type="text"\s+value="Nomad"/', $row);
        $this->assertMatchesRegularExpression('/<input id="'.$dialog.'-year-from" name="year_from" type="number"\s+value="1955"/', $row);
        $this->assertMatchesRegularExpression('/<input id="'.$dialog.'-year-to" name="year_to" type="number"\s+value="1961"/', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.models.active', [$maker, $model]).'"', $row);
        $this->assertMatchesRegularExpression('/border-rule text-ink[^"]*">\s*Deactivate<span class="sr-only"> — Nomad<\/span>/', $row);
    }

    public function test_a_maker_without_models_shows_the_empty_state(): void
    {
        $html = $this->page(CarMaker::factory()->create())->getContent();

        $this->assertStringContainsString('No models yet.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_an_unknown_or_non_numeric_maker_is_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/makers/999999')->assertNotFound();
        $this->actingAs($admin)->get('/admin/makers/abc')->assertNotFound();
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function modelWrites(): array
    {
        return [
            'update' => ['patch', 'admin.makers.models.update', ['name' => 'Moved', 'year_from' => 1950, 'year_to' => 1960]],
            'active' => ['patch', 'admin.makers.models.active', ['active' => '0']],
            'destroy' => ['delete', 'admin.makers.models.destroy', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('modelWrites')]
    public function test_a_model_of_another_maker_in_the_url_is_404(string $method, string $route, array $payload): void
    {
        $model = CarModel::factory()->create(['name' => 'Kept']);
        $otherMaker = CarMaker::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->{$method}(route($route, [$otherMaker, $model]), $payload)
            ->assertNotFound();

        $this->assertModelExists($model);
        $this->assertSame('Kept', $model->refresh()->name);
        $this->assertTrue($model->is_active);
    }

    public function test_the_query_count_does_not_grow_with_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $maker = CarMaker::factory()->create();
        $this->mixedModels($maker, 2);
        $few = $this->queriesFor($maker, $admin);

        $this->mixedModels($maker, 18);
        $many = $this->queriesFor($maker, $admin);

        $this->assertCount(20, $this->page($maker, $admin)->viewData('models'));
        $this->assertSame($few, $many);
    }

    private function page(CarMaker $maker, ?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.makers.show', $maker));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    private function mixedModels(CarMaker $maker, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $factory = CarModel::factory()->for($maker, 'maker');
            $model = $index % 2 === 0 ? $factory->inactive()->create() : $factory->create();

            if ($index % 3 !== 0) {
                CarListing::factory()->forCarModel($model)->create();
            }
        }
    }

    private function queriesFor(CarMaker $maker, User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page($maker, $admin);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function row(string $html, CarModel $model): string
    {
        preg_match_all('/<tr class="block border-t.*?<\/tr>/s', $html, $rows);
        $needle = route('admin.makers.models.update', [$model->car_maker_id, $model]).'"';
        $matches = array_values(array_filter($rows[0], fn (string $row): bool => str_contains($row, $needle)));

        $this->assertCount(1, $matches, "Row of model {$model->id} not found once.");

        return $matches[0];
    }
}
