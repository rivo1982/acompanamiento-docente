<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\RubricSection;
use App\Models\RubricTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Loads the platform rubric catalog (templates, sections, indicators) from catalog.json.
 *
 * Idempotent: templates are keyed by number, sections by (template, name) and indicators by number.
 */
class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $catalog = json_decode(
            file_get_contents(database_path('seeders/data/catalog.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($catalog): void {
            foreach ($catalog['templates'] as $templateData) {
                $this->seedTemplate($templateData);
            }
        });
    }

    /**
     * @param  array{number: int, name: string, evidence: ?string, standards: ?string, protocol: ?string, usage_warning: ?string, sections: list<array<string, mixed>>}  $templateData
     */
    private function seedTemplate(array $templateData): void
    {
        $template = RubricTemplate::updateOrCreate(
            ['number' => $templateData['number']],
            [
                'name' => $templateData['name'],
                'evidence' => $templateData['evidence'],
                'standards' => $templateData['standards'],
                'protocol' => $templateData['protocol'],
                'usage_warning' => $templateData['usage_warning'],
            ],
        );

        foreach ($templateData['sections'] as $index => $sectionData) {
            $section = RubricSection::updateOrCreate(
                ['rubric_template_id' => $template->id, 'name' => $sectionData['name']],
                ['position' => $index + 1],
            );

            foreach ($sectionData['indicators'] as $indicatorData) {
                $this->seedIndicator($section, $indicatorData);
            }
        }
    }

    /**
     * @param  array{number: int, name: string, levels: array{0: string, 1: string, 2: string, 3: string}, meaning: string, to_achieved: string, to_outstanding: ?string, sources: string}  $indicatorData
     */
    private function seedIndicator(RubricSection $section, array $indicatorData): void
    {
        Indicator::updateOrCreate(
            ['number' => $indicatorData['number']],
            [
                'rubric_section_id' => $section->id,
                'name' => $indicatorData['name'],
                'level_beginning' => $indicatorData['levels'][0],
                'level_developing' => $indicatorData['levels'][1],
                'level_achieved' => $indicatorData['levels'][2],
                'level_outstanding' => $indicatorData['levels'][3],
                'feedback_meaning' => $indicatorData['meaning'],
                'feedback_to_achieved' => $indicatorData['to_achieved'],
                'feedback_to_outstanding' => $indicatorData['to_outstanding'],
                'sources' => $indicatorData['sources'],
            ],
        );
    }
}
