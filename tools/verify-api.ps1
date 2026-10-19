param(
    [string]$BaseUrl = 'http://localhost:8000',
    [switch]$GeneratePdf
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')

foreach ($route in @('/api/employees', '/api/reports/payroll', '/api/payslips/1')) {
    $response = Invoke-RestMethod -Uri ($BaseUrl + $route) -Method Get
    if ($response.success -ne $true) { throw "Request failed: $route" }
    Write-Output "PASS GET $route"
    if ($route -ne '/api/employees') { $response | ConvertTo-Json -Depth 8 }
}

if ($GeneratePdf) {
    # This switch explicitly requests a real S3 upload through the API.
    $response = Invoke-RestMethod -Uri ($BaseUrl + '/api/payslips/1/generate') -Method Post
    if ($response.success -ne $true) { throw 'PDF upload failed.' }
    Write-Output 'PASS POST /api/payslips/1/generate'
    $response | ConvertTo-Json -Depth 8
}
