<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\InventoryItemIdentityService;
use Illuminate\Console\Command;

class PreflightInventoryItemCodeMapping extends Command
{
    protected $signature = 'inventory:items:preflight-code-mapping {mapping? : Optional CSV with item_id and item_code columns}';

    protected $description = 'Read-only report of missing, invalid, and colliding inventory item codes';

    public function handle(): int
    {
        $items = InventoryItem::query()->orderBy('id')->get(['id', 'name', 'item_code'])->keyBy('id');
        $mapping = $this->argument('mapping');
        $proposedCodes = [];
        $sourceCodes = [];
        $issues = [];

        if ($mapping !== null) {
            $parsedMapping = $this->readMapping($mapping);
            if ($parsedMapping === null) {
                return self::FAILURE;
            }
            if ($parsedMapping['issues'] !== []) {
                $this->table(['Temuan', 'ID item', 'Item', 'Kode'], $parsedMapping['issues']);
                $this->error('File mapping ditolak karena memiliki baris kosong atau malformed; tidak ada perubahan data yang dilakukan.');

                return self::FAILURE;
            }
            $proposedCodes = $parsedMapping['codes'];
            $sourceCodes = $parsedMapping['sources'];
            $issues = [...$issues, ...$parsedMapping['issues']];
        }

        foreach ($items as $item) {
            if ($item->item_code === null && ! array_key_exists($item->id, $proposedCodes)) {
                $issues[] = [
                    'Perlu mapping',
                    (string) $item->id,
                    (string) $item->name,
                    '',
                ];
            }
        }

        foreach ($proposedCodes as $itemId => $itemCode) {
            $item = $items->get($itemId);
            if ($item === null) {
                $issues[] = ['ID item tidak ditemukan', (string) $itemId, '', $itemCode];

                continue;
            }

            if ($item->item_code !== null && $item->item_code !== $itemCode) {
                $issues[] = ['Kode sudah ditetapkan; gunakan alur koreksi teraudit', (string) $itemId, (string) $item->name, $itemCode];
            }
        }

        $codesByCanonicalValue = [];
        foreach ($items as $item) {
            $itemCode = $proposedCodes[$item->id] ?? $item->item_code;
            if ($itemCode === null) {
                continue;
            }

            $canonical = InventoryItemIdentityService::canonicalize($itemCode);
            $codesByCanonicalValue[$canonical][] = [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'source_code' => $sourceCodes[$item->id] ?? $item->item_code,
            ];
        }

        foreach ($codesByCanonicalValue as $code => $mappedItems) {
            if (count($mappedItems) > 1) {
                $issues[] = [
                    'Collision kode canonical',
                    implode(', ', array_column($mappedItems, 'id')),
                    implode(' | ', array_column($mappedItems, 'name')),
                    implode(' | ', array_map(
                        static fn (array $mappedItem): string => $mappedItem['source_code'].' → '.$code,
                        $mappedItems,
                    )),
                ];
            }
        }

        if ($issues !== []) {
            $this->table(['Temuan', 'ID item', 'Item', 'Kode'], $issues);
            $this->warn('Tidak ada perubahan data. Pemilik inventori harus menyelesaikan semua temuan sebelum backfill final.');

            return self::FAILURE;
        }

        $this->info('Preflight kode item lulus. Tidak ada perubahan data yang dilakukan.');

        return self::SUCCESS;
    }

    /**
     * @return array{codes: array<int, string>, sources: array<int, string>, issues: list<array{string, string, string, string}>}|null
     */
    private function readMapping(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('File mapping tidak ditemukan atau tidak dapat dibaca.');

            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('File mapping gagal dibuka.');

            return null;
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header)) {
                $this->error('File mapping kosong; kolom wajib: item_id,item_code.');

                return null;
            }

            $header[0] = ltrim((string) ($header[0] ?? ''), "\xEF\xBB\xBF");
            $columns = array_flip(array_map(static fn ($column): string => trim((string) $column), $header));
            if (! isset($columns['item_id'], $columns['item_code'])) {
                $this->error('Kolom file mapping harus memuat item_id dan item_code.');

                return null;
            }

            $codes = [];
            $sources = [];
            $issues = [];
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count($row) !== count($header) || collect($row)->every(fn ($value): bool => trim((string) $value) === '')) {
                    $issues[] = ['Baris kosong atau jumlah kolom tidak sesuai pada baris '.$line, '', '', ''];

                    continue;
                }

                $itemIdValue = $row[$columns['item_id']] ?? '';
                $itemCodeValue = $row[$columns['item_code']] ?? '';
                if (! ctype_digit((string) $itemIdValue) || (int) $itemIdValue < 1) {
                    $issues[] = ['ID tidak valid pada baris '.$line, (string) $itemIdValue, '', (string) $itemCodeValue];

                    continue;
                }

                $itemId = (int) $itemIdValue;
                if (array_key_exists($itemId, $codes)) {
                    $issues[] = ['ID item duplikat pada baris '.$line, (string) $itemId, '', (string) $itemCodeValue];

                    continue;
                }

                $canonical = InventoryItemIdentityService::canonicalize((string) $itemCodeValue);
                if ($canonical === '' || strlen($canonical) > 64 || preg_match('/\A[A-Z0-9._-]+\z/', $canonical) !== 1) {
                    $issues[] = ['Format kode tidak valid pada baris '.$line, (string) $itemId, '', $canonical];

                    continue;
                }

                $codes[$itemId] = $canonical;
                $sources[$itemId] = (string) $itemCodeValue;
            }

            return ['codes' => $codes, 'sources' => $sources, 'issues' => $issues];
        } finally {
            fclose($handle);
        }
    }
}
