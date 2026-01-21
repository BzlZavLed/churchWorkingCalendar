<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Department;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('inventario.xlsx');
        if (!is_file($path)) {
            $this->command?->warn('inventario.xlsx not found. Skipping inventory seed.');
            return;
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $this->command?->info('Inventory spreadsheet loaded.');
        $this->command?->info('Inventory rows detected: ' . count($rows));

        $headerIndex = $this->findHeaderRow($rows);
        if ($headerIndex === null) {
            $this->command?->warn('Inventory header row not found.');
            return;
        }
        $this->command?->info('Inventory header row index: ' . $headerIndex);

        $churchName = $this->extractChurchName($rows[0][0] ?? '');
        $church = $this->resolveChurch($churchName);
        if (!$church) {
            $this->command?->warn('Unable to match church from inventario.xlsx.');
            return;
        }
        $this->command?->info('Inventory church resolved: ' . $church->name . ' (from "' . $churchName . '")');

        $columnMap = $this->mapColumns($rows[$headerIndex]);
        $this->command?->info('Inventory column map: ' . json_encode($columnMap));

        $created = 0;
        $missingDepartment = 0;
        $createdDepartments = 0;
        $missingRequired = 0;
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($this->rowEmpty($row)) {
                continue;
            }

            $description = $this->valueFor($row, $columnMap['description'] ?? null);
            $departmentName = $this->valueFor($row, $columnMap['department'] ?? null);
            if (!$description || !$departmentName) {
                $missingRequired++;
                continue;
            }

            $department = Department::query()
                ->where('church_id', $church->id)
                ->where('name', $departmentName)
                ->first();

            if (!$department) {
                $missingDepartment++;
                $department = Department::create([
                    'church_id' => $church->id,
                    'name' => $departmentName,
                ]);
                $createdDepartments++;
            }

            InventoryItem::create([
                'department_id' => $department->id,
                'quantity' => (int) ($this->valueFor($row, $columnMap['quantity'] ?? null) ?: 1),
                'value' => null,
                'total_value' => null,
                'description' => $description,
                'brand' => $this->valueFor($row, $columnMap['brand'] ?? null),
                'model' => $this->valueFor($row, $columnMap['model'] ?? null),
                'serial_number' => $this->valueFor($row, $columnMap['serial'] ?? null),
                'location' => $this->valueFor($row, $columnMap['location'] ?? null),
            ]);

            $created++;
        }

        $this->command?->info("Seeded {$created} inventory items.");
        $this->command?->info("Rows missing required fields: {$missingRequired}");
        $this->command?->info("Rows with unmatched department: {$missingDepartment}");
        $this->command?->info("Departments created: {$createdDepartments}");
    }

    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && strtoupper(trim($cell)) === 'QTY') {
                    return $index;
                }
            }
        }

        return null;
    }

    private function mapColumns(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            if (!is_string($cell)) {
                continue;
            }
            $normalized = strtolower(trim($cell));
            if ($normalized === 'qty') {
                $map['quantity'] = $index;
            } elseif (str_contains($normalized, 'descrip')) {
                $map['description'] = $index;
            } elseif ($normalized === 'marca') {
                $map['brand'] = $index;
            } elseif ($normalized === 'modelo') {
                $map['model'] = $index;
            } elseif (str_contains($normalized, 'serial')) {
                $map['serial'] = $index;
            } elseif (str_contains($normalized, 'departamento')) {
                $map['department'] = $index;
            } elseif (str_contains($normalized, 'ubicaci')) {
                $map['location'] = $index;
            }
        }

        return $map;
    }

    private function valueFor(array $row, ?int $index): ?string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return null;
        }
        $value = $row[$index];
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function extractChurchName(string $headerValue): string
    {
        $value = trim($headerValue);
        if ($value === '') {
            return '';
        }
        if (preg_match('/Iglesia\s+(.+?)\s+\.::\./i', $value, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/Iglesia\s+(.+)/i', $value, $matches)) {
            return trim($matches[1]);
        }

        return $value;
    }

    private function resolveChurch(string $name): ?Church
    {
        if ($name === '') {
            return Church::query()->orderBy('id')->first();
        }

        $exact = Church::query()->where('name', $name)->first();
        if ($exact) {
            return $exact;
        }

        return Church::query()->where('name', 'like', "%{$name}%")->first();
    }
}
