<?php

declare(strict_types=1);

namespace Payroll;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PayslipPdf
{
    public static function render(array $payslip): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        // Lambda permits temporary writes here, not inside the deployed package.
        $options->set('tempDir', sys_get_temp_dir());
        $options->set('fontCache', sys_get_temp_dir());
        $pdf = new Dompdf($options);

        $details = '';
        foreach ($payslip['employee'] as $column => $value) {
            $details .= '<tr><td>' . self::escape($column) . '</td><td>'
                . self::escape($value) . '</td></tr>';
        }
        $id = self::escape($payslip['payroll_id']);
        $name = self::escape($payslip['employee_name']);
        $start = self::escape($payslip['pay_period_start']);
        $end = self::escape($payslip['pay_period_end']);
        $gross = number_format($payslip['gross_pay'], 2, '.', ',');
        $deductions = number_format($payslip['deductions'], 2, '.', ',');
        $net = number_format($payslip['net_pay'], 2, '.', ',');

        $pdf->loadHtml(<<<HTML
        <!doctype html>
        <html><head><meta charset="UTF-8"><style>
        @page { margin: 36pt; }
        body { font-family: "DejaVu Sans"; font-size: 10pt; color: #243247; }
        h1 { color: #17365d; font-size: 24pt; margin-bottom: 4pt; }
        h2 { font-size: 12pt; margin-top: 20pt; }
        .subtitle { color: #617086; margin-bottom: 20pt; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #d5dce6; padding: 8pt; }
        td:first-child { width: 40%; background: #f2f5f9; }
        .net td { background: #e8f3ec; font-weight: bold; }
        .note { font-size: 8pt; color: #617086; margin-top: 22pt; }
        </style></head><body>
        <h1>Employee Payslip</h1>
        <p class="subtitle">Payroll record #{$id} &nbsp; | &nbsp; {$name}</p>
        <h2>Employee details</h2><table>{$details}</table>
        <h2>Pay period</h2><table>
        <tr><td>Start date</td><td>{$start}</td></tr>
        <tr><td>End date</td><td>{$end}</td></tr></table>
        <h2>Payroll figures</h2><table>
        <tr><td>Gross pay</td><td>{$gross}</td></tr>
        <tr><td>Deductions</td><td>{$deductions}</td></tr>
        <tr class="net"><td>Net pay</td><td>{$net}</td></tr></table>
        <p class="note">Laboratory demonstration using synthetic data. Dates and amounts
        are preserved from the source dataset. Deductions are gross pay minus net pay
        when no separate deductions field exists. The source does not specify currency.</p>
        </body></html>
        HTML, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }

    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? 'N/A'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
