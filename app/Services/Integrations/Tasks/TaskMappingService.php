<?php

namespace App\Services\Integrations\Tasks;

use App\Models\IntegrationTask;

class TaskMappingService
{
    /**
     * Save mappings for a task
     */
    public function save(IntegrationTask $task, array $mappings): IntegrationTask
    {
        $transformedMappings = $this->transformFromFrontend($mappings);

        $task->update([
            'mappings' => $transformedMappings,
        ]);

        return $task->refresh();
    }

    /**
     * Transform mappings from frontend format to database format
     */
    protected function transformFromFrontend(array $mappings): array
    {
        $result = [];

        foreach ($mappings as $targetType => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $targetField => $sourceField) {
                if (empty($sourceField)) {
                    continue; // Skip empty mappings
                }

                $result[] = [
                    'source_field' => $sourceField,
                    'target_field' => $targetField,
                    'target_type' => $targetType,
                    'transform' => null,
                ];
            }
        }

        return $result;
    }
}
