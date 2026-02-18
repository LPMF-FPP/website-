<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ChangelogService
{
    public function getChangelogs(): array
    {
        $path = base_path('WALKTHROUGH.md');

        if (! File::exists($path)) {
            return [];
        }

        $cacheKey = 'changelogs_parsed_'.md5((string) File::lastModified($path));

        // Cache for 1 hour to avoid file reading on every request
        return Cache::remember($cacheKey, 3600, function () use ($path) {
            $content = File::get($path);

            // Normalize line endings
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            return $this->parseMarkdown($content);
        });
    }

    private function parseMarkdown(string $content): array
    {
        $lines = explode("\n", $content);
        $changelogs = [];
        $currentVersion = null;
        $isArchived = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Stop parsing if we hit the archive section (optional, or we parse it too)
            if (Str::contains($line, 'Changelog Archive')) {
                $isArchived = true;

                continue;
            }

            // Parse Version Header: ### v2.4.0 (12 Februari 2026) - Title
            // Relaxed Regex to handle various formats
            if (preg_match('/^###\s+(v[^\s]+)(?:\s+\((.*?)\))?\s*(?:-|–)?\s*(.*)$/i', $line, $matches)) {

                // Save previous version if exists
                if ($currentVersion) {
                    $changelogs[] = $currentVersion;
                }

                $version = $matches[1];
                $date = $matches[2] ?? '';
                $title = trim($matches[3]);

                // Handle Archive headers specially or treat as normal
                if (empty($title) && ! empty($date)) {
                    // Case: ### v2.4.0 (Title was empty, date might be title)
                    $title = $date;
                    $date = '';
                }

                $currentVersion = [
                    'id' => Str::slug($version),
                    'version' => $version,
                    'date' => $date,
                    'title' => $title ?: 'Update',
                    'content' => [],
                    'is_archived' => $isArchived,
                    'type' => $this->determineType($title.' '.$version),
                ];

                continue;
            }

            // Parse Bullet Points
            if ($currentVersion && (str_starts_with($line, '-') || str_starts_with($line, '*'))) {
                // Clean up markdown bold syntax **Text** -> <strong>Text</strong>
                $text = Str::markdown(trim(substr($line, 1)));
                // Remove wrapping <p> from Str::markdown
                $text = preg_replace('/^<p>|<\/p>$/', '', trim($text));

                $currentVersion['content'][] = $text;
            }
        }

        // Add the last one
        if ($currentVersion) {
            $changelogs[] = $currentVersion;
        }

        return $changelogs;
    }

    private function determineType(string $title): string
    {
        $title = strtolower($title);
        if (str_contains($title, 'fix') || str_contains($title, 'bug')) {
            return 'fix';
        }
        if (str_contains($title, 'security')) {
            return 'security';
        }
        if (str_contains($title, 'feature') || str_contains($title, 'add') || str_contains($title, 'new')) {
            return 'feature';
        }
        if (str_contains($title, 'redesign') || str_contains($title, 'ui') || str_contains($title, 'ux')) {
            return 'design';
        }

        return 'update';
    }
}
