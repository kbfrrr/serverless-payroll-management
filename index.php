<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Payroll\PayrollRepository;
use Payroll\PayslipPdf;

ini_set('display_errors', '0');

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    exit;
}

try {
    require __DIR__ . '/vendor/autoload.php';
    $repository = new PayrollRepository(__DIR__ . '/data');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

    if ($path === '/') {
        if ($method !== 'GET') {
            header('Allow: GET');
            respond(['success' => false, 'message' => 'Use GET for this endpoint.'], 405);
        }
        respond(['success' => true, 'message' => 'Payroll API is running.', 'endpoints' => [
            'GET /api/employees',
            'GET /api/reports/payroll',
            'GET /api/payslips/{payroll_id}',
            'POST /api/payslips/{payroll_id}/generate',
        ]]);
    }

    if ($path === '/api/employees' || $path === '/api/reports/payroll') {
        if ($method !== 'GET') {
            header('Allow: GET');
            respond(['success' => false, 'message' => 'Use GET for this endpoint.'], 405);
        }
        $data = $path === '/api/employees' ? $repository->employees() : $repository->summary();
        respond(['success' => true, 'data' => $data]);
    }

    if (preg_match('#^/api/payslips/([1-9][0-9]*)(/generate)?$#', $path, $matches)) {
        $generate = isset($matches[2]);
        $expectedMethod = $generate ? 'POST' : 'GET';
        if ($method !== $expectedMethod) {
            header('Allow: ' . $expectedMethod);
            respond(['success' => false, 'message' => "Use {$expectedMethod} for this endpoint."], 405);
        }
        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ($payslip = $repository->payslip($id)) === null) {
            respond(['success' => false, 'message' => 'Payroll record not found.'], 404);
        }
        if (!$generate) {
            respond(['success' => true, 'data' => $payslip]);
        }

        $bucket = getenv('PAYSLIP_BUCKET');
        if (!$bucket || str_contains($bucket, 'REPLACE_')) {
            throw new RuntimeException('Set PAYSLIP_BUCKET to your existing S3 bucket name.');
        }
        $region = getenv('AWS_REGION') ?: getenv('AWS_DEFAULT_REGION') ?: 'ap-southeast-1';
        // On Lambda, the SDK automatically obtains the execution role's credentials.
        $s3 = new S3Client(['version' => 'latest', 'region' => $region]);
        $key = 'payslips/' . $id . '.pdf';
        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => PayslipPdf::render($payslip),
            'ContentType' => 'application/pdf',
            'ContentDisposition' => 'inline; filename="' . $id . '.pdf"',
        ]);
        respond([
            'success' => true,
            'message' => 'Payslip generated and uploaded.',
            'data' => [
                'payroll_id' => $id,
                'bucket' => $bucket,
                'payslip_key' => $key,
                'payslip_file' => "s3://{$bucket}/{$key}",
            ],
        ]);
    }

    respond(['success' => false, 'message' => 'Endpoint not found.'], 404);
} catch (Throwable $exception) {
    error_log((string) $exception);
    respond(['success' => false, 'message' => 'An internal error occurred. Check application logs.'], 500);
}
