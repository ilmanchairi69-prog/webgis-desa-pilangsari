<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationRenderingTest extends TestCase
{
    private function paginator(int $page = 1, int $total = 38): LengthAwarePaginator
    {
        $first = ($page - 1) * 15 + 1;
        $last = min($page * 15, $total);

        return (new LengthAwarePaginator(
            $first <= $last ? range($first, $last) : [],
            $total,
            15,
            $page,
            ['path' => 'http://localhost/admin/houses'],
        ))->appends(['q' => 'RT 01', 'status' => 'pending']);
    }

    public function test_default_pagination_uses_admin_markup_and_fixed_size_arrows(): void
    {
        $html = $this->paginator()->toHtml();

        $this->assertStringContainsString('class="app-pagination"', $html);
        $this->assertStringContainsString('Menampilkan <strong>1&ndash;15</strong>', $html);
        $this->assertStringContainsString('aria-disabled="true" aria-label="Halaman sebelumnya"', $html);
        $this->assertStringContainsString('width="16" height="16"', $html);
        $this->assertStringNotContainsString('sm:hidden', $html);
        $this->assertStringNotContainsString('w-5 h-5', $html);
    }

    public function test_navigation_keeps_search_filters_and_marks_the_current_page(): void
    {
        $html = $this->paginator(2)->toHtml();

        $this->assertStringContainsString('aria-current="page" aria-label="Halaman 2"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        preg_match_all('/href="([^"]+)"/', $html, $links);
        $this->assertNotEmpty($links[1]);
        foreach ($links[1] as $url) {
            parse_str(parse_url(html_entity_decode($url), PHP_URL_QUERY), $query);
            $this->assertSame('RT 01', $query['q']);
            $this->assertSame('pending', $query['status']);
            $this->assertContains($query['page'], ['1', '3']);
        }
    }

    public function test_last_page_disables_next_and_shows_the_correct_item_range(): void
    {
        $html = $this->paginator(3)->toHtml();

        $this->assertStringContainsString('Menampilkan <strong>31&ndash;38</strong>', $html);
        $this->assertStringContainsString('aria-disabled="true" aria-label="Halaman berikutnya"', $html);
        $this->assertStringNotContainsString('rel="next"', $html);
    }

    public function test_one_page_or_empty_results_do_not_render_navigation(): void
    {
        $this->assertSame('', trim($this->paginator(1, 10)->toHtml()));
        $this->assertSame('', trim($this->paginator(1, 0)->toHtml()));
    }
}
