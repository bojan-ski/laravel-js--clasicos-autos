<?php

namespace Tests\Feature;

use App\Contracts\NavCounter;
use App\Enums\HeaderActionStyle;
use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use App\Models\User;
use App\Support\HeaderAction;
use App\Support\MainNavigation;
use App\Support\NavItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ShellComponentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_lazy_loading_is_prevented_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    public function test_layout_title_appends_the_app_name(): void
    {
        $this->blade('<x-layouts.guest title="Sign in">Body</x-layouts.guest>')
            ->assertSee('<title>'.e('Sign in — '.__('app.name')).'</title>', false)
            ->assertSee('Body');
    }

    public function test_nav_item_requires_counter_and_count_style_together(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NavItem('inbox', 'app.nav.home', 'home', 'home', [NavAudience::Member], counter: 'SomeCounter');
    }

    public function test_selected_count_renders_a_note_without_the_menu_signal(): void
    {
        $this->fakeNavigation(
            items: [new NavItem('compare', 'app.nav.home', 'home', 'home', [NavAudience::Guest], $this->counterReturning(2), NavCountStyle::Selected)],
        );

        $this->get('/')
            ->assertSee(__('app.header.selected_count', ['count' => 2]))
            ->assertDontSee('data-menu-signal', false);
    }

    public function test_queue_count_renders_on_the_admin_bar_without_the_menu_signal(): void
    {
        $this->fakeNavigation(
            items: [new NavItem('queue', 'app.nav.home', 'home', 'home', [NavAudience::Admin], $this->counterReturning(4), NavCountStyle::Queue)],
        );

        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Admin" />')
            ->assertSee('data-nav-count', false)
            ->assertSee('>4<', false)
            ->assertDontSee('data-menu-signal', false);
    }

    public function test_header_renders_post_actions_as_csrf_forms_and_the_avatar_as_a_link(): void
    {
        $this->fakeNavigation(actions: [
            new HeaderAction('profile', 'AB', '/profile', 'GET', HeaderActionStyle::Avatar, 'Profile'),
            new HeaderAction('logout', 'Log out', '/logout', 'POST', HeaderActionStyle::Ghost),
        ]);

        $this->actingAs(User::factory()->make())
            ->get('/')
            ->assertSeeInOrder(['href="/profile"', 'AB', '<span class="sr-only">Profile</span>'], false)
            ->assertDontSee('aria-label="Profile"', false)
            ->assertSee('action="/logout"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_button_renders_a_link_with_href_and_a_button_without(): void
    {
        $this->blade('<x-ui.button href="/somewhere">Go</x-ui.button>')
            ->assertSee('<a href="/somewhere"', false)
            ->assertDontSee('<button', false);

        $this->blade('<x-ui.button type="submit">Send</x-ui.button>')
            ->assertSee('<button type="submit"', false)
            ->assertDontSee('<a ', false);
    }

    public function test_icons_are_hidden_from_assistive_technology_and_merge_attributes(): void
    {
        $this->blade('<x-icons.menu data-probe="menu" />')
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('focusable="false"', false)
            ->assertSee('viewBox="0 0 24 24"', false)
            ->assertSee('data-probe="menu"', false);
    }

    /**
     * @return class-string<NavCounter>
     */
    private function counterReturning(int $value): string
    {
        $counter = new class($value) implements NavCounter
        {
            public function __construct(private int $value) {}

            public function count(?User $user): int
            {
                return $this->value;
            }
        };

        $this->app->instance($counter::class, $counter);

        return $counter::class;
    }

    /**
     * @param  list<NavItem>|null  $items
     * @param  list<HeaderAction>  $actions
     */
    private function fakeNavigation(?array $items = null, array $actions = []): void
    {
        $this->app->instance(MainNavigation::class, new class($this->app, $this->app['router'], $items, $actions) extends MainNavigation
        {
            public function __construct($container, $router, private ?array $fakeItems, private array $fakeActions)
            {
                parent::__construct($container, $router);
            }

            public function items(): array
            {
                return $this->fakeItems ?? parent::items();
            }

            public function actionsFor(NavAudience $audience, ?User $user): array
            {
                return $this->fakeActions;
            }
        });
    }
}
