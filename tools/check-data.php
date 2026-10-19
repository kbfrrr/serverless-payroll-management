<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$repository = new Payroll\PayrollRepository(dirname(__DIR__) . '/data');
$employees = $repository->employees();
$payroll = $repository->payroll();
$employeeIds = array_column($employees, null, 'id');
$negativeDeductions = $reversedPeriods = 0;

foreach ($payroll as $record) {
    if (!isset($employeeIds[$record['employee_id']])) {
        throw new RuntimeException('Payroll references an unknown employee.');
    }
    $negativeDeductions += Payroll\PayrollRepository::cents($record['net_pay'])
        > Payroll\PayrollRepository::cents($record['gross_pay']) ? 1 : 0;
    $reversedPeriods += $record['pay_period_start'] > $record['pay_period_end'] ? 1 : 0;
}

echo json_encode([
    'employee_records' => count($employees),
    'payroll_summary' => $repository->summary(),
    'source_data_notes' => [
        'records_with_net_above_gross' => $negativeDeductions,
        'records_with_reversed_pay_period' => $reversedPeriods,
        'action' => 'Source records are preserved without correction.',
    ],
    'example_payslip' => $repository->payslip((int) $payroll[0]['id']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
