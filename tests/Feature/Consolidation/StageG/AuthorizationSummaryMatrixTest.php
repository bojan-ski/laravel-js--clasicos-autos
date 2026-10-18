<?php

namespace Tests\Feature\Consolidation\StageG;

use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\Transmission;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\CompareService;
use App\Support\ListingFormValues;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApplicationRoutes;
use Tests\TestCase;

class AuthorizationSummaryMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<string, list<string>>
     */
    private const array TABLES = [
        'users' => ['id'],
        'car_listings' => ['id'],
        'listing_images' => ['id'],
        'user_bookmarks' => ['user_id', 'car_listing_id'],
        'conversations' => ['id'],
        'messages' => ['id'],
        'message_reports' => ['id'],
        'contact_messages' => ['id'],
        'car_makers' => ['id'],
        'car_models' => ['id'],
    ];

    private const array HIDDEN = ['Lp', 'Lr', 'Ls'];

    private const array OWN_HIDDEN = ['Lp', 'Lr'];

    private const string TEXT = 'Is this car still available?';

    private const string PASSWORD = 'secret12';

    /**
     * @var array<string, Model>
     */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function cases(): Generator
    {
        foreach (self::rows() as $row => $cells) {
            $actors = [];

            foreach ($cells as $cell) {
                $actors = [...$actors, ...array_keys($cell['codes'])];
            }

            foreach (array_unique($actors) as $actor) {
                yield "row {$row} as {$actor}" => [(string) $row, $actor];
            }
        }
    }

    #[DataProvider('cases')]
    public function test_each_summary_row_gives_each_actor_its_outcome(string $row, string $actor): void
    {
        $expected = [];
        $actual = [];

        foreach (self::rows()[$row] as $cell) {
            if (! array_key_exists($actor, $cell['codes'])) {
                continue;
            }

            $key = "{$cell['route']} {$cell['target']} {$cell['arm']}";
            $expected[$key] = $cell['codes'][$actor];
            $actual[$key] = $this->runCell($cell, $actor, false);

            if (in_array($actor, $cell['invalid'], true)) {
                $expected["{$key} †"] = $cell['codes'][$actor];
                $actual["{$key} †"] = $this->runCell($cell, $actor, true);
            }
        }

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $actual);
    }

    public function test_every_named_route_but_logout_appears_in_a_cell(): void
    {
        $covered = [];

        foreach (self::rows() as $cells) {
            $covered = [...$covered, ...array_column($cells, 'route')];
        }

        $covered = array_values(array_unique($covered));
        sort($covered);
        $named = array_values(array_diff(array_keys(ApplicationRoutes::named()), ['logout']));
        sort($named);

        $this->assertSame($named, $covered);
    }

    /**
     * @return array<string, list<array{route: string, target: ?string, arm: string, codes: array<string, string>, invalid: list<string>}>>
     */
    private static function rows(): array
    {
        $everyone = ['guest' => 'OK', 'T' => 'OK', 'O' => 'OK', 'A' => 'OK'];
        $ownHidden = fn (string $target, string $code): array => in_array($target, self::OWN_HIDDEN, true) ? ['O' => $code] : [];
        $rows = [];

        $rows['1'] = [
            ...array_map(
                fn (string $route): array => self::cell($route, null, 'get', $everyone),
                ['home', 'listings.index', 'listings.advanced', 'legal.privacy', 'legal.terms', 'legal.cookies', 'robots', 'sitemap'],
            ),
            self::cell('listings.show', 'L', 'get', [...$everyone, 'guest' => 'OK+contact']),
            self::cell('cookie-consent.store', null, 'form', $everyone),
        ];

        foreach (['2a', '2b', '2c', '2d', '2e', '2f'] as $id) {
            $rows[$id] = [];
        }

        foreach (self::HIDDEN as $target) {
            $rows['2a'][] = self::cell('listings.show', $target, 'get', ['guest' => '404', 'T' => '404', ...$ownHidden($target, 'OK'), 'A' => 'OK']);

            $managed = ['guest' => 'LOGIN', 'T' => '404', ...$ownHidden($target, 'OK'), 'A' => 'OK'];
            $rows['2b'][] = self::cell('listings.edit', $target, 'get', $managed);
            $rows['2b'][] = self::cell('listings.images.edit', $target, 'get', $managed);

            $rows['2c'][] = self::cell('listings.update', $target, 'form', $managed, ['T']);
            $rows['2c'][] = self::cell('listings.destroy', $target, 'form', $managed);
            $rows['2c'][] = self::cell('listings.images.store', $target, 'form', $managed, ['T']);
            $rows['2c'][] = self::cell('listings.images.primary', $target, 'form', $managed);
            $rows['2c'][] = self::cell('listings.images.destroy', $target, 'form', $managed);

            $rows['2d'][] = self::cell('bookmarks.store', $target, 'form', ['guest' => 'LOGIN', 'T' => '404', ...$ownHidden($target, '403'), 'A' => '403']);
            $rows['2d'][] = self::cell('bookmarks.store', $target, 'json', ['guest' => 'J401', 'T' => 'J404', ...$ownHidden($target, 'J403'), 'A' => 'J403']);

            $rows['2e'][] = self::cell('compare.add', $target, 'form', ['guest' => '404', 'T' => '404', ...$ownHidden($target, '404'), 'A' => '404']);
            $rows['2e'][] = self::cell('compare.add', $target, 'json', ['guest' => 'J404', 'T' => 'J404', ...$ownHidden($target, 'J404'), 'A' => 'J404']);

            $messaging = ['guest' => 'LOGIN', 'T' => '404', ...$ownHidden($target, '403'), 'A' => '403'];
            $rows['2f'][] = self::cell('conversations.create', $target, 'get', $messaging);
            $rows['2f'][] = self::cell('conversations.store', $target, 'form', $messaging, ['T', 'O']);
        }

        $rows['3'] = [
            self::cell('compare.show', null, 'get', $everyone),
            self::cell('compare.add', 'L', 'form', $everyone),
            self::cell('compare.add', 'L', 'json', $everyone),
            self::cell('compare.remove', 'L', 'json', $everyone),
            self::cell('compare.clear', null, 'form', $everyone),
        ];

        $rows['4'] = array_map(
            fn (string $route): array => self::cell($route, null, 'get', ['guest' => 'OK', 'T' => '→P', 'A' => '→AU']),
            ['register', 'login', 'password.request', 'password.reset'],
        );

        $rows['4w'] = array_map(
            fn (string $route): array => self::cell($route, null, 'form', ['T' => '→P', 'A' => '→AU']),
            ['register.store', 'login.store', 'password.email', 'password.update'],
        );

        $create = ['guest' => 'LOGIN', 'T' => 'OK', 'A' => '403'];
        $rows['5'] = [
            self::cell('listings.create', null, 'get', $create),
            self::cell('listings.store', null, 'form', $create),
        ];

        $manage = ['guest' => 'LOGIN', 'T' => '403', 'O' => 'OK', 'A' => 'OK'];
        $rows['6'] = [
            self::cell('listings.edit', 'L', 'get', $manage),
            self::cell('listings.images.edit', 'L', 'get', $manage),
            self::cell('listings.update', 'L', 'form', $manage, ['T']),
            self::cell('listings.destroy', 'L', 'form', $manage),
            self::cell('listings.images.store', 'L', 'form', $manage, ['T']),
            self::cell('listings.images.primary', 'L', 'form', $manage),
            self::cell('listings.images.destroy', 'L', 'form', $manage),
        ];

        $moderate = ['guest' => 'LOGIN', 'T' => '403', 'O' => '403', 'A' => 'OK'];
        $rows['7'] = [
            self::cell('admin.listings', null, 'get', $moderate),
            self::cell('admin.listings.approve', 'Lp', 'form', $moderate),
            self::cell('admin.listings.reject', 'Lp', 'form', $moderate),
            self::cell('admin.listings.promote', 'Lc', 'form', $moderate),
        ];

        $rows['8'] = [
            self::cell('bookmarks.index', null, 'get', ['guest' => 'LOGIN', 'T' => 'OK', 'O' => 'OK', 'A' => 'OK']),
            self::cell('bookmarks.store', 'L', 'form', ['guest' => 'LOGIN', 'T' => 'OK', 'O' => '403', 'A' => 'OK']),
            self::cell('bookmarks.store', 'L', 'json', ['guest' => 'J401', 'T' => 'OK', 'O' => 'J403', 'A' => 'OK']),
            self::cell('bookmarks.destroy', 'L', 'form', ['guest' => 'LOGIN', 'T' => 'OK', 'O' => '403', 'A' => 'OK']),
            self::cell('bookmarks.destroy', 'L', 'json', ['guest' => 'J401', 'T' => 'OK', 'O' => 'J403', 'A' => 'OK']),
        ];

        $rows['9a'] = [self::cell('users.listings', 'O', 'get', ['guest' => 'LOGIN', 'T' => 'OK', 'O' => 'OK', 'A' => 'OK'])];
        $rows['9b'] = [self::cell('users.listings', 'X', 'get', ['guest' => 'LOGIN', 'T' => '404', 'A' => 'OK'])];
        $rows['9c'] = [
            self::cell('users.listings', 'A2', 'get', ['guest' => 'LOGIN', 'T' => '404', 'A' => '404']),
            self::cell('users.listings', 'A', 'get', ['guest' => 'LOGIN', 'T' => '404', 'A' => '404']),
        ];

        $start = ['guest' => 'LOGIN', 'T' => 'OK', 'O' => '403', 'A' => '403'];
        $rows['10a'] = [
            self::cell('conversations.create', 'L', 'get', $start),
            self::cell('conversations.store', 'L', 'form', $start, ['O']),
        ];

        $rows['10b'] = [
            self::cell('conversations.messages.store', 'C', 'form', ['guest' => 'LOGIN', 'T' => '404', 'B' => 'OK', 'O' => 'OK', 'A' => '403'], ['T']),
            self::cell('conversations.messages.store', 'C', 'json', ['guest' => 'J401', 'T' => 'J404', 'B' => 'OK', 'O' => 'OK', 'A' => 'J403'], ['T']),
        ];

        $participants = ['guest' => 'LOGIN', 'T' => '404', 'B' => 'OK', 'O' => 'OK', 'A' => 'OK'];
        $rows['11'] = [
            self::cell('conversations.show', 'C', 'get', $participants),
            self::cell('conversations.read', 'C', 'form', $participants),
            self::cell('conversations.index', 'C', 'get', ['guest' => 'LOGIN', 'T' => 'OK-C', 'B' => 'OK', 'O' => 'OK', 'A' => 'OK+C']),
        ];

        $rows['12'] = [self::cell('conversations.messages.destroy', 'mB', 'form', ['guest' => 'LOGIN', 'T' => '404', 'B' => 'OK', 'O' => '403', 'A' => 'OK'])];

        $rows['13'] = [
            self::cell('conversations.messages.report', 'mO', 'form', ['guest' => 'LOGIN', 'T' => '404', 'B' => 'OK', 'A' => '403'], ['T']),
            self::cell('conversations.messages.report', 'mB', 'form', ['B' => '403'], ['B']),
        ];

        $resolve = ['guest' => 'LOGIN', 'T' => '403', 'B' => '403', 'A' => 'OK'];
        $rows['14'] = [
            self::cell('admin.reports', null, 'get', $resolve),
            self::cell('admin.reports.resolve', 'R', 'form', $resolve),
        ];

        $appUserOnly = ['guest' => 'LOGIN', 'T' => 'OK', 'A' => '403'];
        $adminOnly = ['guest' => 'LOGIN', 'T' => '403', 'A' => 'OK'];

        $rows['15'] = [
            self::cell('contact', null, 'get', $appUserOnly),
            self::cell('contact.store', null, 'form', $appUserOnly),
        ];

        $rows['16'] = [
            self::cell('admin.feedback', null, 'get', $adminOnly),
            self::cell('admin.feedback.status', 'F', 'form', $adminOnly),
        ];

        $rows['17a'] = [
            self::cell('profile', null, 'get', $appUserOnly),
            self::cell('profile.password.update', null, 'form', $appUserOnly),
            self::cell('profile.destroy', null, 'form', $appUserOnly),
        ];

        $rows['17b'] = [
            self::cell('admin.account', null, 'get', $adminOnly),
            self::cell('admin.account.password.update', null, 'form', $adminOnly),
        ];

        $users = ['guest' => 'LOGIN', 'T' => '403', 'B' => '403', 'A' => 'OK'];
        $rows['18a'] = [
            self::cell('admin.users', null, 'get', $users),
            self::cell('admin.users.suspend', 'B', 'form', $users),
            self::cell('admin.users.unsuspend', 'Bx', 'form', $adminOnly),
            self::cell('admin.users.unsuspend', 'B', 'form', ['B' => '403']),
            self::cell('admin.users.destroy', 'B', 'form', $users),
        ];

        $rows['18b'] = [];

        foreach (['A2', 'A'] as $target) {
            $rows['18b'][] = self::cell('admin.users.suspend', $target, 'form', ['A' => '403'], ['A']);
            $rows['18b'][] = self::cell('admin.users.unsuspend', $target, 'form', ['A' => '403']);
            $rows['18b'][] = self::cell('admin.users.destroy', $target, 'form', ['A' => '403']);
        }

        $rows['19a'] = [
            self::cell('admin.makers', null, 'get', $adminOnly),
            self::cell('admin.makers.show', 'Mk0', 'get', $adminOnly),
            self::cell('admin.makers.store', null, 'form', $adminOnly),
            self::cell('admin.makers.update', 'Mk0', 'form', $adminOnly),
            self::cell('admin.makers.active', 'Mk0', 'form', $adminOnly),
            self::cell('admin.makers.destroy', 'Mk0', 'form', $adminOnly),
            self::cell('admin.makers.models.store', 'Mk0', 'form', $adminOnly),
            self::cell('admin.makers.models.update', 'Md0', 'form', $adminOnly),
            self::cell('admin.makers.models.active', 'Md0', 'form', $adminOnly),
            self::cell('admin.makers.models.destroy', 'Md0', 'form', $adminOnly),
        ];

        $rows['19b'] = [
            self::cell('admin.makers.destroy', 'Mk', 'form', ['A' => '403']),
            self::cell('admin.makers.models.destroy', 'Md', 'form', ['A' => '403']),
        ];

        return $rows;
    }

    /**
     * @param  array<string, string>  $codes
     * @param  list<string>  $invalid
     * @return array{route: string, target: ?string, arm: string, codes: array<string, string>, invalid: list<string>}
     */
    private static function cell(string $route, ?string $target, string $arm, array $codes, array $invalid = []): array
    {
        return ['route' => $route, 'target' => $target, 'arm' => $arm, 'codes' => $codes, 'invalid' => $invalid];
    }

    /**
     * @param  array{route: string, target: ?string, arm: string, codes: array<string, string>, invalid: list<string>}  $cell
     */
    private function runCell(array $cell, string $actor, bool $invalid): string
    {
        $this->fixtures = [];
        $this->flushSession();

        if ($actor === 'guest') {
            $this->app['auth']->forgetGuards();
        } else {
            $this->actingAs($this->fx($actor));
        }

        if ($cell['target'] !== null) {
            $this->fx($cell['target']);
        }

        $method = ApplicationRoutes::named()[$cell['route']]->methods()[0];
        $uri = route($cell['route'], $this->parameters($cell['route'], $cell['target']));
        $payload = $method === 'GET' ? [] : $this->payload($cell['route'], $cell['target'], $invalid);
        $before = $this->snapshot();

        $response = $cell['arm'] === 'json'
            ? $this->json($method, $uri, $payload)
            : match ($method) {
                'GET' => $this->get($uri),
                'POST' => $this->post($uri, $payload),
                'PUT' => $this->put($uri, $payload),
                'PATCH' => $this->patch($uri, $payload),
                'DELETE' => $this->delete($uri, $payload),
            };

        $expected = $cell['codes'][$actor];

        if (! $this->satisfies($response, $expected, $cell)) {
            return $this->describe($response);
        }

        if (! str_starts_with($expected, 'OK') && $this->snapshot() !== $before) {
            return "{$expected} but wrote";
        }

        if ($invalid && $this->hasValidationErrors($response, $cell['arm'])) {
            return "{$expected} with validation errors";
        }

        return $expected;
    }

    /**
     * @param  array{route: string, target: ?string, arm: string, codes: array<string, string>, invalid: list<string>}  $cell
     */
    private function satisfies(TestResponse $response, string $expected, array $cell): bool
    {
        $content = (string) $response->getContent();

        return match ($expected) {
            'LOGIN' => $response->isRedirect(route('login')),
            '→P' => $response->isRedirect(route('profile')),
            '→AU' => $response->isRedirect(route('admin.users')),
            '403' => $response->status() === 403 && str_contains($content, e(__('app.errors.403.title'))),
            '404' => $response->status() === 404 && str_contains($content, e(__('app.errors.404.title'))),
            'J401', 'J403', 'J404' => $response->status() === (int) substr($expected, 1)
                && str_starts_with((string) $response->headers->get('Content-Type'), 'application/json')
                && is_string($response->json('message')),
            'OK+contact' => $this->isAllowed($response, $cell)
                && str_contains($content, e($this->fx('O')->email))
                && str_contains($content, e($this->fx('O')->phone_number)),
            'OK-C' => $this->isAllowed($response, $cell) && ! str_contains($content, $this->threadUrl()),
            'OK+C' => $this->isAllowed($response, $cell) && str_contains($content, $this->threadUrl()),
            'OK' => $this->isAllowed($response, $cell),
        };
    }

    /**
     * @param  array{route: string, target: ?string, arm: string, codes: array<string, string>, invalid: list<string>}  $cell
     */
    private function isAllowed(TestResponse $response, array $cell): bool
    {
        if ($cell['arm'] === 'json') {
            return $response->status() === ($cell['route'] === 'conversations.messages.store' ? 201 : 200);
        }

        if ($cell['route'] === 'conversations.read') {
            return $response->status() === 204;
        }

        if ($cell['arm'] === 'get') {
            return $response->status() === 200;
        }

        return $response->isRedirect()
            && ! $response->isRedirect(route('login'))
            && ! $this->app['session.store']->has('errors')
            && ! $this->app['session.store']->has('error');
    }

    private function hasValidationErrors(TestResponse $response, string $arm): bool
    {
        return $arm === 'json'
            ? $response->json('errors') !== null
            : $this->app['session.store']->has('errors');
    }

    private function describe(TestResponse $response): string
    {
        $session = $this->app['session.store'];

        return $response->status()
            .($response->isRedirect() ? ' → '.$response->headers->get('Location') : '')
            .($session->has('errors') ? ' (errors)' : '')
            .($session->has('error') ? ' (error: '.$session->get('error').')' : '');
    }

    private function threadUrl(): string
    {
        return '"'.route('conversations.show', $this->fx('C')).'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(string $route, ?string $target): array
    {
        if (in_array($target, ['Mk', 'Md'], true)) {
            $this->fx('L');
        }

        return match ($route) {
            'listings.show', 'listings.edit', 'listings.update', 'listings.destroy', 'listings.images.edit', 'listings.images.store',
            'bookmarks.store', 'bookmarks.destroy', 'compare.add', 'compare.remove', 'conversations.create', 'conversations.store',
            'admin.listings.approve', 'admin.listings.reject', 'admin.listings.promote' => ['listing' => $this->fx($target)],
            'listings.images.primary', 'listings.images.destroy' => [
                'listing' => $this->fx($target),
                'image' => $this->fx($target)->images()->where('position', 1)->sole(),
            ],
            'conversations.show', 'conversations.read', 'conversations.messages.store' => ['conversation' => $this->fx('C')],
            'conversations.messages.destroy', 'conversations.messages.report' => ['conversation' => $this->fx('C'), 'message' => $this->fx($target)],
            'admin.reports.resolve' => ['report' => $this->fx('R')],
            'admin.feedback.status' => ['contactMessage' => $this->fx('F')],
            'users.listings', 'admin.users.suspend', 'admin.users.unsuspend', 'admin.users.destroy' => ['user' => $this->fx($target)],
            'admin.makers.show', 'admin.makers.update', 'admin.makers.active', 'admin.makers.destroy', 'admin.makers.models.store' => ['maker' => $this->fx($target)],
            'admin.makers.models.update', 'admin.makers.models.active', 'admin.makers.models.destroy' => [
                'maker' => $this->fx($target)->car_maker_id,
                'model' => $this->fx($target),
            ],
            'password.reset' => ['token' => 'junk-token', 'email' => 'someone@example.com'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $route, ?string $target, bool $invalid): array
    {
        if ($invalid) {
            return match ($route) {
                'conversations.store', 'conversations.messages.store' => ['message' => 'Hi'],
                'conversations.messages.report' => ['reason' => 'not-a-reason'],
                default => [],
            };
        }

        $password = ['old_password' => 'password', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
        $modelYears = ['year_from' => 1950, 'year_to' => 1960];

        return match ($route) {
            'listings.store' => $this->createPayload(),
            'listings.update' => ListingFormValues::for($this->fx($target)),
            'listings.images.store' => ['images' => [UploadedFile::fake()->image('added.jpg', 40, 30)]],
            'conversations.store', 'conversations.messages.store' => ['message' => self::TEXT],
            'conversations.messages.report' => ['reason' => 'spam'],
            'admin.listings.approve' => ['version' => $this->fx($target)->moderationVersion()],
            'admin.listings.reject' => ['version' => $this->fx($target)->moderationVersion(), 'rejection_reason' => 'Photos do not match the description.'],
            'admin.listings.promote' => ['version' => $this->fx($target)->moderationVersion(), 'name' => 'Promoted Model', ...$modelYears],
            'admin.reports.resolve' => ['outcome' => 'dismissed'],
            'admin.feedback.status' => ['target_status' => 'handled'],
            'admin.users.suspend' => ['reason' => 'Repeated abusive messages.', 'duration' => '30_days'],
            'admin.makers.store' => ['name' => 'Matrix Motors'],
            'admin.makers.update' => ['name' => 'Renamed Motors'],
            'admin.makers.active', 'admin.makers.models.active' => ['active' => '0'],
            'admin.makers.models.store' => ['name' => 'Matrix Roadster', ...$modelYears],
            'admin.makers.models.update' => ['name' => 'Renamed Roadster', ...$modelYears],
            'contact.store' => ['subject' => 'A question about listings', 'message' => 'How long does approval usually take?'],
            'profile.password.update', 'admin.account.password.update' => $password,
            'profile.destroy' => ['delete_password' => 'password'],
            'register.store' => [
                'username' => 'new seller',
                'email' => 'new.seller@example.com',
                'phone_number' => '+5355512345',
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
                'terms_and_conditions' => '1',
                'privacy_policy' => '1',
            ],
            'login.store' => ['email' => 'someone@example.com', 'password' => 'password'],
            'password.email' => ['email' => 'someone@example.com'],
            'password.update' => ['token' => 'junk-token', 'email' => 'someone@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        $model = $this->fx('Md');

        return [
            'name' => 'Matrix Bel Air',
            'car_maker_id' => $model->car_maker_id,
            'car_model_id' => $model->id,
            'price' => 25000,
            'year' => 1957,
            'mileage' => 120000,
            'transmission' => Transmission::Manual->value,
            'fuel_type' => FuelType::Gasoline->value,
            'engine_size' => '4.6L',
            'engine_type' => 'V8',
            'engine_history' => EngineHistory::cases()[0]->value,
            'engine_condition' => EngineCondition::cases()[0]->value,
            'restoration_history' => RestorationHistory::cases()[0]->value,
            'original_parts_percentage' => 80,
            'odometer' => Odometer::cases()[0]->value,
            'documentation_status' => DocumentationStatus::cases()[0]->value,
            'location_city' => 'Havana',
            'location_state' => 'La Habana',
            'location_zipcode' => '10400',
            'description' => 'Two-tone paint, original interior.',
            'images' => [UploadedFile::fake()->image('front.jpg', 40, 30)],
        ];
    }

    private function fx(?string $name): Model
    {
        return $this->fixtures[(string) $name] ??= match ($name) {
            'O', 'B', 'T' => User::factory()->create(),
            'A', 'A2' => User::factory()->admin()->create(),
            'X' => User::factory()->suspended()->create(),
            'Bx' => User::factory()->suspended(now()->addDays(30))->create(),
            'Mk', 'Mk0' => CarMaker::factory()->create(),
            'Md', 'Md0' => CarModel::factory()->create(['car_maker_id' => $this->fx('Mk')->id, 'year_from' => 1950, 'year_to' => 1960]),
            'L' => $this->listing(ListingStatus::Approved, 'O'),
            'Lp' => $this->listing(ListingStatus::Pending, 'O'),
            'Lr' => $this->listing(ListingStatus::Rejected, 'O'),
            'Ls' => $this->listing(ListingStatus::Approved, 'X'),
            'Lc' => CarListing::factory()->customModel()->for($this->fx('O'), 'owner')->create(['car_maker_id' => $this->fx('Mk')->id, 'year' => 1957]),
            'C' => Conversation::factory()->for($this->fx('L'), 'listing')->for($this->fx('B'), 'sender')->create(),
            'mB' => Message::factory()->for($this->fx('C'))->create(['sender_id' => $this->fx('B')->id]),
            'mO' => Message::factory()->for($this->fx('C'))->create(['sender_id' => $this->fx('O')->id]),
            'R' => MessageReport::factory()->create(['message_id' => $this->fx('mO')->id]),
            'F' => ContactMessage::factory()->create(['user_id' => $this->fx('T')->id]),
        };
    }

    private function listing(ListingStatus $status, string $owner): CarListing
    {
        $factory = match ($status) {
            ListingStatus::Approved => CarListing::factory()->approved(),
            ListingStatus::Rejected => CarListing::factory()->rejected(),
            ListingStatus::Pending => CarListing::factory(),
        };

        $listing = $factory->forCarModel($this->fx('Md'))->withImages(2)->for($this->fx($owner), 'owner')->create(['year' => 1957]);

        foreach ($listing->images as $image) {
            foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
                Storage::disk('public')->put($path, 'stub');
            }
        }

        return $listing;
    }

    /**
     * @return array{tables: array<string, list<array<string, mixed>>>, files: list<string>, compare: mixed}
     */
    private function snapshot(): array
    {
        $tables = [];

        foreach (self::TABLES as $table => $keys) {
            $query = DB::table($table);

            foreach ($keys as $key) {
                $query->orderBy($key);
            }

            $tables[$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
        }

        $files = Storage::disk('public')->allFiles();
        sort($files);

        return ['tables' => $tables, 'files' => $files, 'compare' => $this->app['session.store']->get(CompareService::SESSION_KEY)];
    }
}
