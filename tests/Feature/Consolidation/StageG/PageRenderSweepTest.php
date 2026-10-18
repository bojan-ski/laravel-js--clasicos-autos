<?php

namespace Tests\Feature\Consolidation\StageG;

use App\Models\CarListing;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\CompareService;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class PageRenderSweepTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string RAW_KEY = '/(?<![A-Za-z0-9@.\/])(app|auth|pagination|passwords|validation)\.[a-z0-9_]+(\.[a-z0-9_]+)*/';

    /** @var list<string> */
    private const array SCANNED_ATTRIBUTES = ['alt', 'aria-label', 'title', 'placeholder'];

    /** @var list<string> */
    private const array SKIPPED_ELEMENTS = ['script', 'style', 'template'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actors(): array
    {
        return [
            'guest' => ['guest'],
            'active app user' => ['appUser'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('actors')]
    public function test_every_get_page_renders_without_a_server_error_or_a_raw_translation_key(string $actor): void
    {
        $user = match ($actor) {
            'guest' => null,
            'appUser' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
        };
        $compareIds = $this->seedFixtures($user);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $failures = [];
        $scanned = [];

        foreach (ApplicationRoutes::named() as $name => $route) {
            if ($route->methods()[0] !== 'GET') {
                continue;
            }

            $this->withSession([CompareService::SESSION_KEY => $compareIds]);
            $this->assertSame($compareIds, $this->app['session.store']->get(CompareService::SESSION_KEY));

            $response = $this->get(route($name, RouteParameterMap::parametersFor($route)));

            if ($response->status() >= 500) {
                $failures[$name] = "status {$response->status()}";

                continue;
            }

            if ($this->isHtmlPage($response)) {
                $scanned[] = $name;
                $rawKeys = $this->rawKeys((string) $response->getContent());

                if ($rawKeys !== []) {
                    $failures[$name] = 'raw keys: '.implode(', ', $rawKeys);
                }
            }
        }

        $this->assertSame([], $failures);
        $this->assertContains('home', $scanned);
        $this->assertContains('listings.show', $scanned);
    }

    public function test_a_raw_key_in_visible_text_or_a_scanned_attribute_is_found_and_urls_and_data_are_ignored(): void
    {
        $html = '<!doctype html><html><head><title>app.seo.title</title><style>.app.x{}</style></head><body>'
            .'<p>Missing: app.listings.card.missing</p><img src="/storage/app.png" alt="validation.attributes.email">'
            .'<a href="https://app.example/auth.login" data-key="app.hidden">mail@app.example</a>'
            .'<script>window.key = "app.script";</script><template><p>app.template</p></template></body></html>';

        $this->assertSame(['app.seo.title', 'app.listings.card.missing', 'validation.attributes.email'], $this->rawKeys($html));
    }

    /**
     * @return list<int>
     */
    private function seedFixtures(?User $user): array
    {
        $approved = CarListing::factory()->approved()->withImages()->count(2)->create();

        if ($user?->isAppUser()) {
            CarListing::factory()->for($user, 'owner')->create();
            CarListing::factory()->rejected('The photos are too dark.')->for($user, 'owner')->create();
            $conversation = Conversation::factory()->for($user, 'sender')->create(['listing_id' => $approved[0]->id]);
            Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id, 'read_at' => null]);
        }

        MessageReport::factory()->create();
        ContactMessage::factory()->create();

        return $approved->modelKeys();
    }

    private function isHtmlPage(TestResponse $response): bool
    {
        return $response->isSuccessful()
            && ! $response->isRedirect()
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * @return list<string>
     */
    private function rawKeys(string $html): array
    {
        $texts = [];
        $this->collectVisibleText(HTMLDocument::createFromString($html, LIBXML_NOERROR), $texts);

        $keys = [];

        foreach ($texts as $text) {
            preg_match_all(self::RAW_KEY, $text, $matches);
            array_push($keys, ...$matches[0]);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $texts
     */
    private function collectVisibleText(Node $node, array &$texts): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof Text) {
                $texts[] = $child->textContent;

                continue;
            }

            if (! $child instanceof Element) {
                continue;
            }

            if (in_array($child->localName, self::SKIPPED_ELEMENTS, true)) {
                continue;
            }

            foreach (self::SCANNED_ATTRIBUTES as $attribute) {
                if ($child->hasAttribute($attribute)) {
                    $texts[] = (string) $child->getAttribute($attribute);
                }
            }

            $this->collectVisibleText($child, $texts);
        }
    }
}
