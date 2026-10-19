<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Payroll\PayrollRepository;
use Payroll\PayslipPdf;

function check(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    echo 'PASS: ', $description, PHP_EOL;
}

$repository = new PayrollRepository(dirname(__DIR__) . '/data');
$payroll = $repository->payroll();
$employees = $repository->employees();
check(count($employees) === 500 && count($payroll) === 600, 'Official v3 dataset row counts');
check(PayrollRepository::cents('100.01') === 10001, 'Money retains fractional cents correctly');
check(PayrollRepository::cents('-0.25') === -25, 'Negative derived amounts remain signed');

// This expected fixture comes directly from the first official payroll CSV row.
$first = $repository->payslip(1);
check($first !== null && $first['employee']['id'] === 319, 'Payroll 1 joins employee 319');
check($first['gross_pay'] === 14131.54 && $first['net_pay'] === 1505.18
    && $first['deductions'] === 12626.36, 'Payroll 1 amounts match the source fixture');
check($repository->payslip(999999) === null, 'Unknown payroll ID is absent');

$summary = $repository->summary();
check(abs($summary['total_gross_pay'] - $summary['total_deductions']
    - $summary['total_net_pay']) < 0.00001, 'Report totals reconcile');

$pdf = PayslipPdf::render($first);
check(str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000, 'Dompdf produces a PDF');
$outputDirectory = dirname(__DIR__) . '/artifacts';
if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}
file_put_contents($outputDirectory . '/sample-payslip-1.pdf', $pdf);
echo 'Local sample: artifacts/sample-payslip-1.pdf', PHP_EOL;
echo 'S3 upload and deployed endpoints require your AWS configuration.', PHP_EOL;
