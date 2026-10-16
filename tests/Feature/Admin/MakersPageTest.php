<?php

namespace Tests\Feature\Admin;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MakersPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login_and_an_app_user_gets_403(): void
    {
        $this->get(route('admin.makers'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('admin.makers'))->assertForbidden();
    }

    public function test_the_page_renders_the_spanish_kicker_the_title_and_the_add_form(): void
    {
        $html = $this->page()->assertViewIs('pages.admin.makers.index')->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Marcas/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Makers</h1>', $html);
        $this->assertStringContainsString('action="'.route('admin.makers.store').'"', $html);
        $this->assertMatchesRegularExpression('/<input id="name" name="name" type="text"\s+required aria-required="true"\s+aria-describedby="name-hint"/', $html);
        $this->assertStringContainsString('<p id="name-hint" class="text-xs text-muted">Max 64 characters.</p>', $html);
        $this->assertMatchesRegularExpression('/border-red text-red[^"]*">Add maker<\/button>/', $html);
    }

    public function test_every_maker_is_listed_by_name_active_and_inactive(): void
    {
        $studebaker = CarMaker::factory()->create(['name' => 'Studebaker']);
        $buick = CarMaker::factory()->inactive()->create(['name' => 'Buick']);
        $packard = CarMaker::factory()->create(['name' => 'Packard']);

        $this->assertSame([$buick->id, $packard->id, $studebaker->id], $this->page()->viewData('makers')->pluck('id')->all());
    }

    public function test_the_counts_include_inactive_models_custom_and_non_approved_listings(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create();
        CarModel::factory()->inactive()->for($maker, 'maker')->create();
        CarListing::factory()->approved()->forCarModel($model)->create();
        CarListing::factory()->rejected()->forCarModel($model)->create();
        CarListing::factory()->customModel()->for($maker, 'maker')->create();
        CarListing::factory()->create();

        $row = $this->row($this->page()->getContent(), $maker);

        $this->assertMatchesRegularExpression('/uppercase lg:hidden">Models<\/span>\s*2\s*<\/td>/', $row);
        $this->assertMatchesRegularExpression('/uppercase lg:hidden">Listings<\/span>\s*3\s*<\/td>/', $row);
    }

    public function test_delete_is_offered_only_for_a_maker_with_no_models_and_no_listings(): void
    {
        $bare = CarMaker::factory()->create(['name' => 'Tucker']);
        $withModel = CarMaker::factory()->create();
        CarModel::factory()->for($withModel, 'maker')->create();
        $withCustomListing = CarMaker::factory()->create();
        CarListing::factory()->customModel()->for($withCustomListing, 'maker')->create();

        $html = $this->page()->getContent();
        $row = $this->row($html, $bare);

        $this->assertMatchesRegularExpression('/border-red-deep text-red-deep[^"]*"\s*data-dialog-open="delete-maker-'.$bare->id.'" commandfor="delete-maker-'.$bare->id.'" command="show-modal">\s*Delete<span class="sr-only"> — Tucker<\/span>/', $row);
        $this->assertStringContainsString('<dialog id="delete-maker-'.$bare->id.'"', $row);
        $this->assertStringContainsString('Delete Tucker?', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.destroy', $bare).'"', $row);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="DELETE">', $row);
        $this->assertStringNotContainsString('delete-maker-', $this->row($html, $withModel));
        $this->assertStringNotContainsString('delete-maker-', $this->row($html, $withCustomListing));
    }

    public function test_an_active_maker_reads_active_and_offers_deactivate(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Packard']);

        $row = $this->row($this->page()->getContent(), $maker);

        $this->assertStringContainsString('<span class="text-ink-2">Active</span>', $row);
        $this->assertStringNotContainsString('border-status-info', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.active', $maker).'"', $row);
        $this->assertStringContainsString('<input type="hidden" name="active" value="0">', $row);
        $this->assertMatchesRegularExpression('/border-rule text-ink[^"]*">\s*Deactivate<span class="sr-only"> — Packard<\/span>/', $row);
        $this->assertStringNotContainsString('Activate<', $row);
    }

    public function test_an_inactive_maker_carries_the_info_stamp_and_offers_activate(): void
    {
        $maker = CarMaker::factory()->inactive()->create(['name' => 'Hudson']);

        $row = $this->row($this->page()->getContent(), $maker);

        $this->assertStringContainsString('border-status-info text-status-info bg-status-info/10', $row);
        $this->assertMatchesRegularExpression('/uppercase">Inactive<\/span>/', $row);
        $this->assertStringContainsString('<input type="hidden" name="active" value="1">', $row);
        $this->assertMatchesRegularExpression('/border-red text-red[^"]*">\s*Activate<span class="sr-only"> — Hudson<\/span>/', $row);
    }

    public function test_the_name_links_to_the_models_page_and_edit_opens_a_prefilled_dialog(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Nash']);

        $row = $this->row($this->page()->getContent(), $maker);

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.makers.show', $maker), '/').'" class="[^"]*font-heading[^"]*text-red italic[^"]*">Nash<\/a>/', $row);
        $this->assertMatchesRegularExpression('/data-dialog-open="edit-maker-'.$maker->id.'" commandfor="edit-maker-'.$maker->id.'" command="show-modal">\s*Edit<span class="sr-only"> — Nash<\/span>/', $row);
        $this->assertMatchesRegularExpression('/<dialog id="edit-maker-'.$maker->id.'" aria-labelledby="edit-maker-'.$maker->id.'-title"\s+class="/', $row);
        $this->assertStringNotContainsString('data-dialog-autoopen', $row);
        $this->assertStringContainsString('action="'.route('admin.makers.update', $maker).'"', $row);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PATCH">', $row);
        $this->assertMatchesRegularExpression('/<input id="edit-maker-'.$maker->id.'-name" name="name" type="text"\s+value="Nash"/', $row);
    }

    public function test_a_maker_name_is_escaped(): void
    {
        $maker = CarMaker::factory()->create(['name' => '<b>Bold</b> Motors']);

        $row = $this->row($this->page()->getContent(), $maker);

        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; Motors', $row);
        $this->assertStringNotContainsString('<b>Bold</b>', $row);
    }

    public function test_the_makers_nav_item_is_current_on_both_pages_between_feedback_and_conversations(): void
    {
        $maker = CarMaker::factory()->create();

        foreach ([route('admin.makers'), route('admin.makers.show', $maker)] as $url) {
            $html = $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.makers'), '/').'"\s+aria-current="page"/', $html);
            $this->assertSeeInOrderHtml($html, ['href="'.route('admin.feedback').'"', 'href="'.route('admin.makers').'"', 'href="'.route('conversations.index').'"']);
        }
    }

    public function test_members_never_see_the_makers_nav_item(): void
    {
        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertDontSee('href="'.route('admin.makers').'"', false);
        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertOk()->assertDontSee('href="'.route('admin.makers').'"', false);
    }

    public function test_the_query_count_does_not_grow_with_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mixedMakers(2);
        $few = $this->queriesFor($admin);

        $this->mixedMakers(18);
        $many = $this->queriesFor($admin);

        $this->assertCount(20, $this->page($admin)->viewData('makers'));
        $this->assertSame($few, $many);
    }

    private function page(?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.makers'));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    private function mixedMakers(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $maker = $index % 2 === 0 ? CarMaker::factory()->inactive()->create() : CarMaker::factory()->create();

            if ($index % 3 !== 0) {
                CarListing::factory()->forCarModel(CarModel::factory()->for($maker, 'maker')->create())->create();
            }
        }
    }

    private function queriesFor(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page($admin);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function row(string $html, CarMaker $maker): string
    {
        preg_match_all('/<tr class="block border-t.*?<\/tr>/s', $html, $rows);
        $needle = route('admin.makers.update', $maker).'"';
        $matches = array_values(array_filter($rows[0], fn (string $row): bool => str_contains($row, $needle)));

        $this->assertCount(1, $matches, "Row of maker {$maker->id} not found once.");

        return $matches[0];
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $position = 0;

        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $position);
            $this->assertNotFalse($found, "Missing or out of order: {$needle}");
            $position = $found + strlen($needle);
        }
    }
}
