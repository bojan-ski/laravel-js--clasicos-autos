<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_profile_shows_the_uppercased_heading_and_the_account_rows(): void
    {
        $user = User::factory()->create([
            'username' => 'ángel ruiz',
            'email' => 'angel@example.com',
            'phone_number' => '+5355512345',
            'created_at' => '2024-03-07 10:15:00',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertViewHas('heading', __('app.profile.heading', ['username' => 'ÁNGEL RUIZ']))
            ->assertViewHas('accountCreated', '07.03.2024')
            ->assertSee('<title>'.e(__('app.profile.heading', ['username' => 'ÁNGEL RUIZ'])), false)
            ->assertSeeInOrder([
                __('app.profile.kicker'),
                __('app.profile.heading', ['username' => 'ÁNGEL RUIZ']),
                __('app.profile.details.title'),
                __('app.profile.details.username'), 'ángel ruiz',
                __('app.profile.details.email'), 'angel@example.com',
                __('app.profile.details.phone_number'), '+5355512345',
                __('app.profile.details.created_at'), '07.03.2024',
                __('app.account.password.title'),
            ]);
    }

    public function test_profile_marks_the_spanish_kicker(): void
    {
        $content = $this->actingAs(User::factory()->create())->get(route('profile'))->getContent();

        $this->assertMatchesRegularExpression('/<span[^>]*lang="es"[^>]*>'.preg_quote(__('app.profile.kicker'), '/').'<\/span>/', $content);
    }

    public function test_profile_password_form_puts_to_the_profile_route(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertSee('action="'.route('profile.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="old_password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(__('app.account.password.submit'));
    }

    public function test_profile_renders_the_delete_account_dialog(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertSee('<dialog id="delete-account" aria-labelledby="delete-account-title"', false)
            ->assertSee('<h2 id="delete-account-title"', false)
            ->assertSee(__('app.profile.delete.title'))
            ->assertSee('action="'.route('profile.destroy').'"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee(__('app.profile.delete.password_placeholder'))
            ->assertSee(__('app.profile.delete.cancel'));

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-dialog-open="delete-account"[^>]*commandfor="delete-account"[^>]*command="show-modal"/', $content);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-dialog-close[^>]*commandfor="delete-account"[^>]*command="close"/', $content);
        $this->assertMatchesRegularExpression('/<input id="delete_password" name="delete_password" type="password"[^>]*autocomplete="current-password"[^>]*\srequired\s/', $content);
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*action="'.preg_quote(route('profile.destroy'), '/').'"[^>]*novalidate/', $content);
    }

    public function test_profile_has_no_safe_word(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertDontSee('safe word', false)
            ->assertDontSee('safe_word', false);
    }

    public function test_my_car_listings_stamps_pending_and_rejected_and_shows_the_reason(): void
    {
        $user = User::factory()->create();
        CarListing::factory()->for($user, 'owner')->create(['name' => 'Pending Bel Air']);
        CarListing::factory()->approved()->for($user, 'owner')->create(['name' => 'Approved Impala']);
        CarListing::factory()->rejected('Please add interior photos.')->for($user, 'owner')->create(['name' => 'Rejected Fairlane']);

        $html = $this->actingAs($user)->get(route('profile'))->assertOk()->getContent();

        $this->assertStringContainsString(__('app.listings.status.pending'), $this->card($html, 'Pending Bel Air'));
        $this->assertStringContainsString(__('app.listings.status.rejected'), $this->card($html, 'Rejected Fairlane'));
        $this->assertStringContainsString('<span class="sr-only">'.__('app.listings.show.status.reason').' </span>Please add interior photos.', $this->card($html, 'Rejected Fairlane'));
        $approved = $this->card($html, 'Approved Impala');
        foreach (ListingStatus::cases() as $status) {
            $this->assertStringNotContainsString('>'.$status->label().'<', $approved);
        }
    }

    public function test_my_car_listings_never_shows_another_users_listing(): void
    {
        $user = User::factory()->create();
        CarListing::factory()->approved()->for($user, 'owner')->create(['name' => 'My Thunderbird']);
        CarListing::factory()->approved()->create(['name' => 'Someone Elses Corvette']);

        $this->actingAs($user)->get(route('profile'))
            ->assertSee('My Thunderbird')
            ->assertDontSee('Someone Elses Corvette');
    }

    public function test_my_car_listings_is_newest_first_eight_per_page(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 9) as $number) {
            CarListing::factory()->for($user, 'owner')->create(['name' => "Classic {$number}", 'created_at' => now()->subDays(10 - $number)]);
        }

        $first = $this->actingAs($user)->get(route('profile'))->assertViewHas('listings', fn ($listings): bool => $listings->perPage() === 8);
        $first->assertSeeInOrder(array_map(fn (int $number): string => "Classic {$number}<", range(9, 2)), false)
            ->assertDontSee('Classic 1<', false);

        $this->actingAs($user)->get(route('profile', ['page' => 2]))
            ->assertSee('Classic 1<', false)
            ->assertDontSee('Classic 2<', false);
    }

    public function test_my_car_listings_shows_the_empty_state_without_listings(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile'))
            ->assertSeeInOrder([__('app.profile.listings.heading'), __('app.profile.listings.empty')]);
    }

    public function test_a_page_past_the_last_shows_neither_cards_nor_the_empty_state(): void
    {
        $user = User::factory()->create();
        CarListing::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->get(route('profile', ['page' => 3]))
            ->assertOk()
            ->assertDontSee(__('app.profile.listings.empty'))
            ->assertDontSee('<article', false);
    }

    public function test_the_section_header_links_list_your_car_and_cards_use_h3(): void
    {
        $user = User::factory()->create();
        CarListing::factory()->for($user, 'owner')->create(['name' => 'Bel Air']);

        $html = $this->actingAs($user)->get(route('profile'))->getContent();

        $this->assertMatchesRegularExpression('/<div class="flex flex-wrap items-baseline justify-between gap-4 border-b border-rule pb-2.5">\s*<h2 id="my-listings-heading"[^>]*>'.preg_quote(__('app.profile.listings.heading'), '/').'<\/h2>\s*<a href="'.preg_quote(route('listings.create'), '/').'"[^>]*border-red text-red[^>]*min-h-12[^>]*>'.preg_quote(__('app.profile.listings.create'), '/').'<\/a>/', $html);
        $this->assertStringContainsString('<div class="grid gap-9 md:grid-cols-3">', $html);
        $this->assertMatchesRegularExpression('/<h3 class="[^"]*">Bel Air<\/h3>/', $html);
    }

    public function test_a_suspended_user_does_not_see_list_your_car(): void
    {
        $user = User::factory()->suspended()->create();

        $html = $this->actingAs($user)->get(route('profile'))->assertOk()->getContent();

        $this->assertStringContainsString('id="my-listings-heading"', $html);
        $this->assertDoesNotMatchRegularExpression('/my-listings-heading.*'.preg_quote(route('listings.create'), '/').'/s', $html);
    }

    public function test_own_cards_have_no_bookmark_button(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($user, 'owner')->create();

        $this->actingAs($user)->get(route('profile'))
            ->assertSee(route('compare.add', $listing), false)
            ->assertDontSee(route('bookmarks.store', $listing), false);
    }

    public function test_the_section_eager_loads_its_cards(): void
    {
        $user = User::factory()->create();
        CarListing::factory()->count(3)->for($user, 'owner')->withImages(1)->create();

        $this->actingAs($user)->get(route('profile'))->assertOk();
    }

    private function card(string $html, string $name): string
    {
        preg_match('/<article\b(?:(?!<\/article>).)*>'.preg_quote($name, '/').'<.*?<\/article>/s', $html, $match);

        $this->assertNotEmpty($match, "No card for {$name}.");

        return $match[0];
    }
}
