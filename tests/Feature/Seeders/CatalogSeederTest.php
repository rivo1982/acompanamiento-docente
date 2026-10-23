<?php

namespace Tests\Feature\Seeders;

use App\Models\Indicator;
use App\Models\RubricSection;
use App\Models\RubricTemplate;
use App\Models\TeachingTip;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_ten_templates_and_sixty_numbered_indicators(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(10, RubricTemplate::count());
        $this->assertSame(range(1, 10), RubricTemplate::orderBy('number')->pluck('number')->all());
        $this->assertSame(range(1, 60), Indicator::orderBy('number')->pluck('number')->all());
        $this->assertSame(0, TeachingTip::count());
    }

    public function test_first_template_keeps_its_sections_in_file_order(): void
    {
        $this->seed(CatalogSeeder::class);

        $template = RubricTemplate::where('number', 1)->firstOrFail();

        $this->assertSame([
            'Ambiente propicio para el aprendizaje',
            'Desarrollo personal y social',
            'Enseñanza para el aprendizaje de todos los estudiantes',
            'Evaluación y retroalimentación en el aula',
        ], $template->sections()->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4], $template->sections()->pluck('position')->all());
    }

    public function test_indicator_texts_match_the_catalog_file(): void
    {
        $this->seed(CatalogSeeder::class);

        $source = $this->catalogIndicator(1);
        $indicator = Indicator::where('number', 1)->firstOrFail();

        $this->assertSame('Trato respetuoso e inclusivo', $indicator->name);
        $this->assertSame($source['levels'][0], $indicator->level_beginning);
        $this->assertSame($source['levels'][1], $indicator->level_developing);
        $this->assertSame($source['levels'][2], $indicator->level_achieved);
        $this->assertSame($source['levels'][3], $indicator->level_outstanding);
        $this->assertSame($source['meaning'], $indicator->feedback_meaning);
        $this->assertSame($source['to_achieved'], $indicator->feedback_to_achieved);
        $this->assertSame($source['to_outstanding'], $indicator->feedback_to_outstanding);
        $this->assertSame($source['sources'], $indicator->sources);
        $this->assertSame('Ambiente propicio para el aprendizaje', $indicator->rubricSection->name);
    }

    public function test_ethics_template_carries_its_usage_warning(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertNotNull(RubricTemplate::where('number', 6)->value('usage_warning'));
    }

    public function test_seeding_twice_does_not_duplicate_the_catalog(): void
    {
        $this->seed(CatalogSeeder::class);
        $sectionCount = RubricSection::count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame(10, RubricTemplate::count());
        $this->assertSame($sectionCount, RubricSection::count());
        $this->assertSame(60, Indicator::count());
    }

    public function test_reseeding_restores_edited_catalog_texts(): void
    {
        $this->seed(CatalogSeeder::class);
        Indicator::where('number', 1)->update(['name' => 'Editado']);

        $this->seed(CatalogSeeder::class);

        $this->assertSame('Trato respetuoso e inclusivo', Indicator::where('number', 1)->value('name'));
    }

    public function test_factories_do_not_collide_with_seeded_catalog_numbers(): void
    {
        $this->seed(CatalogSeeder::class);

        $section = RubricSection::factory()->create();
        RubricTemplate::factory()->count(9)->create();
        Indicator::factory()->count(60)->for($section)->create();

        $this->assertSame(20, RubricTemplate::count());
        $this->assertSame(120, Indicator::count());
    }

    public function test_reseeding_a_renamed_section_updates_it_in_place_without_orphans(): void
    {
        $this->seed(CatalogSeeder::class);
        $section = RubricTemplate::where('number', 1)->firstOrFail()->sections()->firstOrFail();
        $indicatorIds = $section->indicators()->pluck('id')->all();
        $sectionCount = RubricSection::count();
        $catalog = $this->catalog();
        $catalog['templates'][0]['sections'][0]['name'] = 'Ámbito renombrado';

        (new CatalogSeeder)->seedCatalog($catalog);

        $this->assertSame($sectionCount, RubricSection::count());
        $this->assertSame('Ámbito renombrado', $section->fresh()->name);
        $this->assertSame($indicatorIds, $section->indicators()->pluck('id')->all());
        $this->assertFalse(RubricSection::where('name', 'Ambiente propicio para el aprendizaje')->exists());
    }

    public function test_reseeding_deletes_sections_left_empty_after_their_indicators_move(): void
    {
        $this->seed(CatalogSeeder::class);
        $template = RubricTemplate::where('number', 1)->firstOrFail();
        $lastSection = $template->sections()->get()->last();
        $movedIds = $lastSection->indicators()->pluck('id')->all();
        $catalog = $this->catalog();
        $removed = array_pop($catalog['templates'][0]['sections']);
        array_push($catalog['templates'][0]['sections'][2]['indicators'], ...$removed['indicators']);

        (new CatalogSeeder)->seedCatalog($catalog);

        $this->assertNull($lastSection->fresh());
        $this->assertSame([1, 2, 3], $template->sections()->pluck('position')->all());
        $this->assertSame(60, Indicator::count());
        $thirdSection = $template->sections()->where('position', 3)->firstOrFail();
        $this->assertEmpty(array_diff($movedIds, $thirdSection->indicators()->pluck('id')->all()));
    }

    public function test_reseeding_keeps_a_removed_section_that_still_has_indicators(): void
    {
        $this->seed(CatalogSeeder::class);
        $template = RubricTemplate::where('number', 1)->firstOrFail();
        $lastSection = $template->sections()->get()->last();
        $catalog = $this->catalog();
        array_pop($catalog['templates'][0]['sections']);

        (new CatalogSeeder)->seedCatalog($catalog);

        $this->assertNotNull($lastSection->fresh());
        $this->assertSame(60, Indicator::count());
    }

    /**
     * @return array{templates: list<array<string, mixed>>}
     */
    private function catalog(): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/catalog.json')), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogIndicator(int $number): array
    {
        $catalog = json_decode(file_get_contents(database_path('seeders/data/catalog.json')), true);

        return collect($catalog['templates'])
            ->flatMap(fn (array $template): array => $template['sections'])
            ->flatMap(fn (array $section): array => $section['indicators'])
            ->firstWhere('number', $number);
    }
}
