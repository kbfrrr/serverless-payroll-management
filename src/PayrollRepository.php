<?php

declare(strict_types=1);

namespace Payroll;

use RuntimeException;

final class PayrollRepository
{
    public function __construct(private readonly string $dataDirectory)
    {
    }

    public function employees(): array
    {
        return $this->read('employees.csv', [
            'id', 'full_name', 'email', 'position_id', 'department_id', 'manager_id',
        ]);
    }

    public function payroll(): array
    {
        return $this->read('payroll.csv', [
            'id', 'employee_id', 'pay_period_start', 'pay_period_end', 'gross_pay', 'net_pay',
        ]);
    }

    public function summary(): array
    {
        $records = $this->payroll();
        $gross = $net = $deductions = 0;

        foreach ($records as $record) {
            $gross += self::cents($record['gross_pay']);
            $net += self::cents($record['net_pay']);
            $deductions += self::deductions($record);
        }

        return [
            'total_payroll_records' => count($records),
            'total_gross_pay' => $gross / 100,
            'total_deductions' => $deductions / 100,
            'total_net_pay' => $net / 100,
        ];
    }

    public function payslip(int $payrollId): ?array
    {
        foreach ($this->payroll() as $record) {
            if ($record['id'] !== $payrollId) {
                continue;
            }

            foreach ($this->employees() as $employee) {
                if ($employee['id'] !== $record['employee_id']) {
                    continue;
                }

                return [
                    'payroll_id' => $record['id'],
                    'employee_name' => $employee['full_name'],
                    'employee' => $employee,
                    'pay_period_start' => $record['pay_period_start'],
                    'pay_period_end' => $record['pay_period_end'],
                    'gross_pay' => self::cents($record['gross_pay']) / 100,
                    'deductions' => self::deductions($record) / 100,
                    'net_pay' => self::cents($record['net_pay']) / 100,
                ];
            }

            throw new RuntimeException('Payroll references a missing employee.');
        }

        return null;
    }

    // Parse money as integer cents rather than adding floating-point values.
    public static function cents(mixed $value): int
    {
        if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim((string) $value), $parts)) {
            throw new RuntimeException('Invalid amount in payroll CSV.');
        }

        $cents = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');
        return $parts[1] === '-' ? -$cents : $cents;
    }

    private static function deductions(array $record): int
    {
        if (isset($record['deductions'])) {
            return self::cents($record['deductions']);
        }

        return self::cents($record['gross_pay']) - self::cents($record['net_pay']);
    }

    private function read(string $filename, array $requiredColumns): array
    {
        $path = $this->dataDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!is_readable($path)) {
            throw new RuntimeException("Cannot read {$filename}.");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$filename}.");
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers === false || $headers === [null]) {
                throw new RuntimeException("Missing CSV header in {$filename}.");
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(static fn ($value): string => trim((string) $value), $headers);
            if (count(array_unique($headers)) !== count($headers) || in_array('', $headers, true)) {
                throw new RuntimeException("Invalid column names in {$filename}.");
            }
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $headers, true)) {
                    throw new RuntimeException("Missing column {$column} in {$filename}.");
                }
            }

            $records = [];
            $seenIds = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException("Malformed CSV row in {$filename}.");
                }
                $record = array_combine($headers, $values);
                foreach ($record as $column => $value) {
                    $value = trim((string) $value);
                    if ($value === '' || strtolower($value) === 'null' || $value === '\\N') {
                        $record[$column] = null;
                    } elseif ($column === 'id' || str_ends_with($column, '_id')) {
                        if (!ctype_digit($value)) {
                            throw new RuntimeException("Invalid ID in {$filename}.");
                        }
                        $record[$column] = (int) $value;
                    } else {
                        $record[$column] = $value;
                    }
                }
                if ($record['id'] === null || isset($seenIds[$record['id']])) {
                    throw new RuntimeException("Missing or duplicate record ID in {$filename}.");
                }
                $seenIds[$record['id']] = true;
                $records[] = $record;
            }

            return $records;
        } finally {
            fclose($handle);
        }
    }
}
