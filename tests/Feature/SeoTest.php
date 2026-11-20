<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordWebsiteVisit;
use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_sitemap_contains_only_canonical_public_pages_on_the_configured_domain(): void
    {
        config(['app.url' => 'https://example.test/subdirectory']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $locations = array_map(static fn ($url): string => (string) $url->loc, iterator_to_array($xml->url, false));
        $this->assertSame([
            'https://example.test/subdirectory/',
            'https://example.test/subdirectory/profil',
            'https://example.test/subdirectory/pemerintahan',
            'https://example.test/subdirectory/agenda',
            'https://example.test/subdirectory/peta',
            'https://example.test/subdirectory/sejarah-desa',
            'https://example.test/subdirectory/letak-geografis',
            'https://example.test/subdirectory/perangkat-desa',
            'https://example.test/subdirectory/struktur-organisasi',
            'https://example.test/subdirectory/kepala-desa',
            'https://example.test/subdirectory/wilayah-administratif',
            'https://example.test/subdirectory/transparansi',
            'https://example.test/subdirectory/potensi-desa',
            'https://example.test/subdirectory/pertanian',
            'https://example.test/subdirectory/wisata-desa',
            'https://example.test/subdirectory/dusun',
            'https://example.test/subdirectory/fasilitas-umum',
        ], $locations);

        $this->assertNotContains('https://example.test/subdirectory/visi-misi', $locations);
        $this->assertNotContains('https://example.test/subdirectory/profil-desa', $locations);
    }

    public function test_public_page_has_metadata_and_alias_uses_canonical_url(): void
    {
        config(['app.url' => 'https://example.test']);
        $this->withoutMiddleware(RecordWebsiteVisit::class);

        $this->get('/profil?source=example')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertDontSee('noindex, nofollow', false)
            ->assertSee('<link rel="canonical" href="https://example.test/profil">', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta name="twitter:card"', false)
            ->assertSee('Mengenal sejarah, wilayah administratif, kehidupan masyarakat', false);

        $this->get('/agenda')->assertOk()
            ->assertSee('<link rel="canonical" href="https://example.test/agenda">', false);

        $this->get('/informasi')->assertStatus(301)->assertRedirect('/agenda');
    }

    public function test_login_is_not_indexable(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);
    }
}
