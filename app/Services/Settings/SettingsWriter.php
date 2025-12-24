<?php

namespace App\Services\Settings;

use App\Repositories\SettingsRepository;
use App\Support\Audit;
use Illuminate\Contracts\Auth\Authenticatable;

class SettingsWriter
{
    public function __construct(
        private readonly SettingsRepository $repository
    ) {
    }

    /**
     * Persist a set of setting key/value pairs and log the change.
     *
     * @param  array<string,mixed>  $pairs
     */
    public function put(array $pairs, string $action, ?Authenticatable $actor = null): void
    {
        $before = [];
        $after = [];
        $userId = $actor?->getAuthIdentifier();
        
        // First, identify keys that should be deleted (have null values)
        $flattenedAll = $this->flattenPairs($pairs);
        $keysToDelete = [];
        foreach ($flattenedAll as $key => $value) {
            if ($value === null) {
                $keysToDelete[] = $key;
            }
        }
        
        // Then, filter out nulls to prevent constraint violations
        $flattened = $this->flattenPairs($this->removeNullLeaves($pairs));

        foreach ($flattened as $key => $value) {
            $before[$key] = $this->repository->get($key);
            $this->repository->put($key, $value, $userId);
            $after[$key] = $value;
        }
        
        // Delete keys that were explicitly set to null
        foreach ($keysToDelete as $key) {
            if ($this->repository->has($key)) {
                $before[$key] = $this->repository->get($key);
                $this->repository->forget($key);
                $after[$key] = null;
            }
        }

        settings_forget_cache();

        Audit::log($action, implode(',', array_keys($pairs)), $before, $after);
    }

    /**
     * @param  array<string,mixed>  $pairs
     * @return array<string,mixed>
     */
    private function flattenPairs(array $pairs): array
    {
        $flat = [];

        foreach ($pairs as $key => $value) {
            $isDotKey = str_contains((string) $key, '.');
            if (is_array($value) && !$isDotKey) {
                $nested = settings_flatten([$key => $value]);
                foreach ($nested as $nestedKey => $nestedValue) {
                    $flat[$nestedKey] = $nestedValue;
                }
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    /**
     * Recursively remove null values from settings data to prevent database constraint violations.
     * Preserves array values (which are valid setting values) while removing null leaf values.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function removeNullLeaves(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                // Skip null values - they will be tracked for deletion
                continue;
            }
            
            if (is_array($value)) {
                // Check if this array contains ANY non-null values (recursively)
                $cleaned = $this->removeNullLeaves($value);
                
                // Only include this key if the array has content after removing nulls
                // An empty array after cleanup means all values were null
                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } else {
                // Scalar value (string, int, bool, etc.) - keep it
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Retrieve nested settings snapshot.
     */
    public function snapshot(): array
    {
        return settings_nest(settings());
    }
}
