<?php

namespace Database\Factories\ProcessMaker\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use ProcessMaker\Http\Controllers\Api\ExportController;
use ProcessMaker\Models\Process;
use ProcessMaker\Models\ProcessTemplateCategory;
use ProcessMaker\Models\ProcessTemplates;
use ProcessMaker\Models\User;

/**
 * Model factory for process templates
 */
class ProcessTemplatesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $process = Process::factory()->create();
        $manifest = (new ExportController)->manifest('process', $process->id);

        return [
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->unique()->name,
            'manifest' => json_encode($manifest),
            'svg' => $process->svg,
            'process_id' => $process->id,
            'process_template_category_id' => function () {
                return ProcessTemplateCategory::factory()->create()->getKey();
            },
        ];
    }
}
