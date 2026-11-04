<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqaQa002HouseRtSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        foreach ([['QA-RT01', '01'], ['QA-RT02', '02'], ['QA-RT10', '10'], ['QA-RT11', '11']] as [$code, $rt]) {
            $house = House::create(['house_code' => $code, 'house_label' => 'Label '.$code, 'rt_code' => $rt,
                'address' => 'Jalan '.$code, 'created_by' => $this->admin->id]);
            FamilyCard::create(['house_id' => $house->id, 'head_name' => 'Kepala '.$code]);
        }
    }

    #[DataProvider('rtKeywords')]
    public function test_prefixed_rt_keywords_match_the_exact_canonical_rt(string $keyword, string $expected, array $excluded): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.houses.index', ['q' => $keyword]))->assertOk();
        $response->assertSee($expected);
        foreach ($excluded as $code) {
            $response->assertDontSee($code);
        }
    }

    public static function rtKeywords(): array
    {
        return [
            'RT 01' => ['RT 01', 'QA-RT01', ['QA-RT02', 'QA-RT10', 'QA-RT11']],
            'RT01' => ['RT01', 'QA-RT01', ['QA-RT02', 'QA-RT10', 'QA-RT11']],
            'RT 1' => ['RT 1', 'QA-RT01', ['QA-RT02', 'QA-RT10', 'QA-RT11']],
            'RT 02' => ['RT 02', 'QA-RT02', ['QA-RT01', 'QA-RT10', 'QA-RT11']],
            'RT02' => ['RT02', 'QA-RT02', ['QA-RT01', 'QA-RT10', 'QA-RT11']],
        ];
    }

    public function test_bare_rt_name_address_whitespace_empty_and_missing_searches_keep_existing_behavior(): void
    {
        $this->actingAs($this->admin)->get(route('admin.houses.index', ['q' => '01']))->assertOk()->assertSee('QA-RT01');
        $this->get(route('admin.houses.index', ['q' => 'Kepala QA-RT02']))->assertOk()->assertSee('QA-RT02');
        $this->get(route('admin.houses.index', ['q' => 'Jalan QA-RT10']))->assertOk()->assertSee('QA-RT10');
        $this->get(route('admin.houses.index', ['q' => '  RT 01  ']))->assertOk()->assertSee('QA-RT01')->assertDontSee('QA-RT10');
        $this->get(route('admin.houses.index', ['q' => 'tidak-ditemukan']))->assertOk()->assertDontSee('QA-RT01');
        $this->get(route('admin.houses.index', ['q' => '']))->assertOk()->assertSee('QA-RT01')->assertSee('QA-RT11');
    }

    public function test_search_pagination_preserves_the_query_string(): void
    {
        foreach (range(1, 16) as $index) {
            House::create(['house_code' => 'QA-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'rt_code' => '01', 'address' => 'Alamat pagination', 'created_by' => $this->admin->id]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.houses.index', ['q' => 'RT 01']))->assertOk();
        $response->assertSee('q=RT%2001', false)->assertSee('page=2', false);
        $this->get(route('admin.houses.index', ['q' => 'RT 01', 'page' => 2]))->assertOk()->assertSee('QA-RT01');
    }
}
