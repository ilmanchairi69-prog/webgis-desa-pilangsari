<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordWebsiteVisit;
use Tests\TestCase;

class PublicSeoPagesTest extends TestCase
{
    public function test_all_requested_public_urls_are_implemented(): void
    {
        config(['app.url' => 'https://desa.pilangsari.id']);
        $this->withoutMiddleware(RecordWebsiteVisit::class);

        $redirects = [
            '/profil-desa' => '/profil',
            '/pemerintahan-desa' => '/pemerintahan',
            '/peta-desa' => '/peta',
        ];

        foreach ($redirects as $source => $destination) {
            $this->get($source)->assertStatus(301)->assertRedirect($destination);
        }

        $this->get('/agenda')->assertOk();

        foreach (array_keys(config('public-pages.pages')) as $slug) {
            $this->get('/'.$slug)
                ->assertOk()
                ->assertSee('<h1', false)
                ->assertSee('<link rel="canonical" href="https://desa.pilangsari.id/'.$slug.'">', false)
                ->assertSee('<meta name="description"', false)
                ->assertSee('application/ld+json', false);
        }
    }

    public function test_verified_pages_are_indexable_and_pending_pages_are_not(): void
    {
        $this->withoutMiddleware(RecordWebsiteVisit::class);

        foreach (config('public-pages.pages') as $slug => $page) {
            $response = $this->get('/'.$slug);

            if ($page['index']) {
                $response->assertSee('<meta name="robots" content="index, follow">', false);
            } else {
                $response->assertSee('<meta name="robots" content="noindex, follow">', false);
            }
        }
    }

    public function test_structured_data_is_valid_json_and_matches_visible_page(): void
    {
        $this->withoutMiddleware(RecordWebsiteVisit::class);

        $content = $this->get('/sejarah-desa')->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        $this->assertArrayHasKey(1, $matches);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertContains('GovernmentOrganization', array_column($schema['@graph'], '@type'));
        $this->assertContains('WebPage', array_column($schema['@graph'], '@type'));
        $this->assertContains('BreadcrumbList', array_column($schema['@graph'], '@type'));
        $this->assertStringContainsString('Asal-usul nama Pilangsari', $content);
    }

    public function test_page_metadata_is_unique_and_internal_links_resolve(): void
    {
        $this->withoutMiddleware(RecordWebsiteVisit::class);
        $pages = config('public-pages.pages');

        $this->assertCount(count($pages), array_unique(array_column($pages, 'title')));
        $this->assertCount(count($pages), array_unique(array_column($pages, 'description')));

        foreach (array_keys($pages) as $slug) {
            $content = $this->get('/'.$slug)->assertOk()->getContent();
            preg_match_all('/href="(\/[^"#?]*)/', $content, $matches);

            foreach (array_unique($matches[1]) as $path) {
                if (str_starts_with($path, '/css/') || str_starts_with($path, '/js/') || str_starts_with($path, '/images/')) {
                    continue;
                }

                $this->get($path)->assertStatus(fn (int $status): bool => $status >= 200 && $status < 400);
            }
        }
    }
}
