<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\ParserService;
use App\Services\Parsers\ParsedPage;
use App\Services\Parsers\ParserInterface;
use App\Services\Parsers\RenderedPhpParser;
use App\Services\Parsers\SpaComponentParser;
use App\Services\Parsers\SsgOutputParser;
use App\Services\Parsers\StaticHtmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParserServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'ps-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, string $type = 'static_html'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'ps-'.uniqid(),
            'repo_url' => 'https://github.com/example/ps',
            'branch' => 'main',
            'project_type' => $type,
        ]);
    }

    private function makeService(
        ?StaticHtmlParser $staticParser = null,
        ?SsgOutputParser $ssgParser = null,
        ?RenderedPhpParser $phpParser = null,
        ?SpaComponentParser $spaParser = null,
    ): ParserService {
        return new ParserService(
            $staticParser ?? \Mockery::mock(StaticHtmlParser::class),
            $ssgParser ?? \Mockery::mock(SsgOutputParser::class),
            $phpParser ?? \Mockery::mock(RenderedPhpParser::class),
            $spaParser ?? \Mockery::mock(SpaComponentParser::class),
        );
    }

    private function mockStaticParser(array $files, array $parsedPages = []): StaticHtmlParser
    {
        /** @var StaticHtmlParser&\Mockery\MockInterface $parser */
        $parser = \Mockery::mock(StaticHtmlParser::class);
        $parser->shouldReceive('name')->andReturn('static_html')->byDefault();
        $parser->shouldReceive('discoverPages')->andReturn($files)->byDefault();
        $parser->shouldReceive('parsePage')->withAnyArgs()->andReturn(null)->byDefault();

        foreach ($parsedPages as $filePath => $page) {
            $parser->shouldReceive('parsePage')
                ->with(\Mockery::any(), $filePath, \Mockery::any())
                ->andReturn($page);
        }

        return $parser;
    }

    private function mockSsgParser(array $files = []): SsgOutputParser
    {
        /** @var SsgOutputParser&\Mockery\MockInterface $parser */
        $parser = \Mockery::mock(SsgOutputParser::class);
        $parser->shouldReceive('name')->andReturn('ssg_output')->byDefault();
        $parser->shouldReceive('discoverPages')->andReturn($files)->byDefault();
        $parser->shouldReceive('parsePage')->withAnyArgs()->andReturn(null)->byDefault();

        return $parser;
    }

    private function mockSpaParser(array $files = []): SpaComponentParser
    {
        /** @var SpaComponentParser&\Mockery\MockInterface $parser */
        $parser = \Mockery::mock(SpaComponentParser::class);
        $parser->shouldReceive('name')->andReturn('spa_component')->byDefault();
        $parser->shouldReceive('discoverPages')->andReturn($files)->byDefault();
        $parser->shouldReceive('parsePage')->withAnyArgs()->andReturn(null)->byDefault();

        return $parser;
    }

    // ── parseSite ────────────────────────────────

    public function test_parse_site_returns_page_count(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $parsedPage = new ParsedPage(
            filePath: 'index.html',
            urlPath: '/',
            title: 'Home',
            contentHash: md5('test'),
        );

        $staticParser = $this->mockStaticParser(['index.html'], ['index.html' => $parsedPage]);

        $service = $this->makeService(staticParser: $staticParser);
        $count = $service->parseSite($site);

        $this->assertSame(1, $count);
    }

    public function test_parse_site_creates_page_in_database(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $parsedPage = new ParsedPage(
            filePath: 'about.html',
            urlPath: '/about',
            title: 'About Us',
            metaDescription: 'Learn about us',
            contentHash: md5('about'),
        );

        $staticParser = $this->mockStaticParser(['about.html'], ['about.html' => $parsedPage]);
        $service = $this->makeService(staticParser: $staticParser);
        $service->parseSite($site);

        $this->assertDatabaseHas('pages', [
            'site_id' => $site->id,
            'file_path' => 'about.html',
            'url_path' => '/about',
            'title' => 'About Us',
            'meta_description' => 'Learn about us',
        ]);
    }

    public function test_parse_site_skips_files_where_parser_returns_null(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $staticParser = $this->mockStaticParser(['empty.html'], []);
        $service = $this->makeService(staticParser: $staticParser);
        $count = $service->parseSite($site);

        $this->assertSame(0, $count);
        $this->assertDatabaseMissing('pages', ['site_id' => $site->id]);
    }

    public function test_parse_site_updates_existing_page(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $site->pages()->create([
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Old Title',
            'content_hash' => 'old-hash',
        ]);

        $parsedPage = new ParsedPage(
            filePath: 'index.html',
            urlPath: '/',
            title: 'New Title',
            contentHash: 'new-hash',
        );

        $staticParser = $this->mockStaticParser(['index.html'], ['index.html' => $parsedPage]);
        $service = $this->makeService(staticParser: $staticParser);
        $service->parseSite($site);

        $this->assertDatabaseHas('pages', ['site_id' => $site->id, 'title' => 'New Title']);
        $this->assertSame(1, $site->pages()->count());
    }

    public function test_parse_site_prunes_pages_no_longer_in_repo(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $site->pages()->create([
            'file_path' => 'old-page.html',
            'url_path' => '/old-page',
            'title' => 'Old Page',
        ]);

        $parsedPage = new ParsedPage(filePath: 'index.html', urlPath: '/', contentHash: 'h');
        $staticParser = $this->mockStaticParser(['index.html'], ['index.html' => $parsedPage]);
        $service = $this->makeService(staticParser: $staticParser);
        $service->parseSite($site);

        $this->assertDatabaseMissing('pages', ['file_path' => 'old-page.html', 'site_id' => $site->id]);
    }

    // ── resolver — correct parser per project type ──

    public function test_uses_static_parser_for_static_html(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'static_html');

        $staticParser = $this->mockStaticParser([]);
        $ssgParser = $this->mockSsgParser();
        $ssgParser->shouldReceive('discoverPages')->never();

        $service = $this->makeService(staticParser: $staticParser, ssgParser: $ssgParser);
        $service->parseSite($site);
    }

    public function test_uses_ssg_parser_for_hugo(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'hugo');

        $ssgParser = $this->mockSsgParser([]);
        $staticParser = $this->mockStaticParser([]);
        $staticParser->shouldReceive('discoverPages')->never();

        $service = $this->makeService(staticParser: $staticParser, ssgParser: $ssgParser);
        $service->parseSite($site);
    }

    public function test_uses_spa_parser_for_react(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'react');

        $spaParser = $this->mockSpaParser([]);
        $staticParser = $this->mockStaticParser([]);
        $staticParser->shouldReceive('discoverPages')->never();

        $service = $this->makeService(staticParser: $staticParser, spaParser: $spaParser);
        $service->parseSite($site);
    }

    // ── parseSinglePage ──────────────────────────

    public function test_parse_single_page_returns_null_when_parser_returns_null(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $staticParser = $this->mockStaticParser([]);
        $staticParser->shouldReceive('parsePage')->andReturn(null);

        $service = $this->makeService(staticParser: $staticParser);
        $result = $service->parseSinglePage($site, 'missing.html');

        $this->assertNull($result);
    }

    public function test_parse_single_page_creates_and_returns_page(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $parsedPage = new ParsedPage(
            filePath: 'contact.html',
            urlPath: '/contact',
            title: 'Contact',
            contentHash: md5('contact'),
        );

        $staticParser = $this->mockStaticParser([]);
        $staticParser->shouldReceive('parsePage')->andReturn($parsedPage);

        $service = $this->makeService(staticParser: $staticParser);
        $page = $service->parseSinglePage($site, 'contact.html');

        $this->assertNotNull($page);
        $this->assertSame('Contact', $page->title);
        $this->assertDatabaseHas('pages', ['file_path' => 'contact.html', 'site_id' => $site->id]);
    }
}
