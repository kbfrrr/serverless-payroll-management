# Serverless Payroll Management API

A complete laboratory project using PHP, Bref, AWS Lambda, API Gateway, CSV,
Dompdf and Amazon S3. All four required endpoints are implemented.

## 1. What is already in this folder?

| File or folder | Purpose |
| --- | --- |
| `index.php` | Receives requests and returns JSON |
| `src/PayrollRepository.php` | Reads CSVs, joins employee records and calculates amounts |
| `src/PayslipPdf.php` | Builds a readable A4 payslip PDF |
| `data/employees.csv`, `data/payroll.csv` | Original official version 3 dataset files |
| `composer.json`, `composer.lock` | PHP dependencies and their reproducible versions |
| `serverless.yml` | Lambda/API Gateway deployment configuration |
| `policies/` | Instructions for your Lambda role |
| `tools/` | Local data, PDF and HTTP checks |
| `postman_collection.json` | Importable requests for all four endpoints |
| `screenshots/` | Instructions for your own evidence screenshots |
| `SUBMISSION.md` | Worksheet for your actual results and reflection |

The local runtime is PHP 8.2, matching the XAMPP installation found on this
computer. Lambda is therefore configured as `php-82-fpm`. Composer's platform
setting keeps dependency resolution compatible with that runtime.

There is no database and no web page frontend. Seeing JSON in the browser is
the intended result. PHP is run by API Gateway -> Lambda -> Bref on AWS.

## 2. Open the project

Open this folder in VS Code:

`C:\Users\katri\Documents\ChatGPT\sam10m lab activity`

Choose Terminal -> New Terminal. All commands below run in this project folder.
If necessary, change into it in PowerShell:

```powershell
Set-Location 'C:\Users\katri\Documents\ChatGPT\sam10m lab activity'
```

Verify your installed tools:

```powershell
php -v
composer --version
node -v
npm.cmd -v
git --version
```

PHP, Composer, Node.js and Git were found when preparing this project. If you
copy it to a different computer, install those tools first.

## 3. Install PHP dependencies

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs
```

The preparation process installs dependencies locally. On another computer,
`composer install` recreates `vendor/` from `composer.lock`.

Do not upload `vendor/` to GitHub; it is intentionally ignored. The deployment
tool DOES include the installed `vendor/` directory in the Lambda package.

### Existing XAMPP warning

This computer currently reports `Module "openssl" is already loaded` at PHP
startup. This is an existing XAMPP configuration issue. To fix it yourself:

1. Run `php --ini` and locate the loaded `php.ini`.
2. Open that file and search for active `extension=openssl` or
   `extension=php_openssl.dll` lines.
3. Keep one active OpenSSL extension entry and comment out the duplicate with `;`.
4. If another INI file also loads OpenSSL, remove the duplicate there instead.
5. Save and restart any running PHP development server.

Do not disable OpenSSL completely. It is needed for HTTPS connections. The project
setup does not modify your system PHP configuration.

## 4. Check data, amounts and PDF generation locally

```powershell
composer check-data
composer test
```

`check-data` prints the actual report totals, source-data notes and an example
payslip. `test` checks the source fixture, joining, amounts and PDF generation.
It writes `artifacts/sample-payslip-1.pdf`, which you can open in File Explorer.
This sample is a LOCAL preview; it is not evidence of an S3 upload.

## 5. Run the API on your computer

```powershell
composer start
```

Leave the terminal open. Visit these browser addresses:

- http://localhost:8000/
- http://localhost:8000/api/employees
- http://localhost:8000/api/reports/payroll
- http://localhost:8000/api/payslips/1

The first returns a list of supported endpoints. The other three return the
employee list, actual payroll totals and payroll 1's payslip.

Optional: open a SECOND PowerShell terminal and check the three GET routes:

```powershell
& .\tools\verify-api.ps1
```

If PowerShell blocks that script, use Postman or the following commands instead
of changing your machine-wide execution policy:

```powershell
curl.exe -i http://localhost:8000/api/employees
curl.exe -i http://localhost:8000/api/reports/payroll
curl.exe -i http://localhost:8000/api/payslips/1
```

Press Ctrl+C in the server terminal when finished.

Local GET requests and PDF checks need no AWS credentials. The POST generate
route performs a real S3 upload, even if you call it locally. Test that route on
AWS after configuring your execution role.

## 6. Prepare AWS

### A. Create the S3 bucket

1. Sign in to the class AWS console.
2. Open S3 -> Create bucket -> General purpose.
3. Use a globally unique name for YOUR project.
4. Use Singapore (`ap-southeast-1`), or the region your class permits.
5. Keep Block all public access enabled and ACLs disabled.
6. Keep default SSE-S3 encryption using Amazon S3 managed keys.
7. Create the bucket and copy its exact name.

### B. Finish the Lambda role

Follow `policies/README.md`. For the requested full-S3-access class setup, use:

- Trusted service: Lambda (`lambda.amazonaws.com`).
- `AWSLambdaBasicExecutionRole` for logs.
- `AmazonS3FullAccess` for S3.

Copy the role's complete ARN from IAM -> Roles -> your role. It resembles
`arn:aws:iam::123456789012:role/katri-payroll-lambda-role`.

### C. Get approved deployment credentials

The Lambda role is used by your running application. It does not supply login
credentials for your computer. Ask your instructor for the approved deployment
method for the shared account. You do not need to create another IAM user.

Your deployment identity must be allowed to deploy the project's CloudFormation,
Lambda, API Gateway, logs and deployment storage, and pass the existing role to
Lambda (`iam:PassRole`). Runtime S3 permissions do not grant deployment access.

If your instructor supplies TEMPORARY credentials, set all three values in your
PowerShell terminal, replacing the examples privately:

```powershell
$env:AWS_ACCESS_KEY_ID = 'YOUR-TEMPORARY-ACCESS-KEY-ID'
$env:AWS_SECRET_ACCESS_KEY = 'YOUR-TEMPORARY-SECRET-ACCESS-KEY'
$env:AWS_SESSION_TOKEN = 'YOUR-TEMPORARY-SESSION-TOKEN'
```

Temporary credentials expire; replace all three when your class session expires.
Do not save them in project files, screenshots, GitHub or chat messages.

If you already have an instructor-approved AWS credentials profile instead:

```powershell
$env:AWS_PROFILE = 'YOUR-EXISTING-PROFILE-NAME'
```

Use the credential method your instructor provides. An AWS console password alone
is not a deployment access key. AWS CLI is optional for this project.

## 7. Configure your bucket and role

In the SAME PowerShell terminal that you will deploy from, run:

```powershell
$env:PAYSLIP_BUCKET = 'YOUR-EXACT-BUCKET-NAME'
$env:PAYROLL_ROLE_ARN = 'YOUR-COMPLETE-LAMBDA-ROLE-ARN'
```

These are configuration values, not credentials. Replace both placeholders.
`serverless.yml` reads them. `.env.example` is a reference only: the application
does not load `.env` automatically. Set these variables again in new terminals.

Open `serverless.yml` and verify:

1. `service: payroll-api-katri` is unique among classmates. Change its suffix if
   another student is using it. The same service/stage/region can target the same stack.
2. `region` matches your class region and bucket region.
3. `iam.role` references `${env:PAYROLL_ROLE_ARN}`; the actual role already exists.
4. The runtime remains `php-82-fpm` for these dependencies.

Do not manually create another Lambda or API Gateway for this deployment.

## 8. Deploy

Install the OSS Serverless command used by Bref's current documentation:

```powershell
npm.cmd install -g osls
serverless.cmd --version
```

Then:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
serverless.cmd deploy
```

The tool creates your application's AWS resources. It prints an API base URL
similar to `https://abc123.execute-api.ap-southeast-1.amazonaws.com`.
Copy YOUR actual URL; do not add `/dev` unless it is part of the printed URL.

To view the URL again:

```powershell
serverless.cmd info
```

An error about `PAYSLIP_BUCKET` or `PAYROLL_ROLE_ARN` means those variables are
missing in this terminal. An expired-token error means class credentials expired.
AccessDenied for `iam:PassRole` or resource creation requires your instructor's
permission configuration; adding more policies to the Lambda role will not fix it.

## 9. Test ALL FOUR deployed endpoints

In Postman choose Import -> Files -> `postman_collection.json`. Open the imported
collection's Variables section and replace `baseUrl` with your deployed base URL,
without a trailing slash. `payrollId` defaults to 1.

| Method | Path | Expected result |
| --- | --- | --- |
| GET | `/api/employees` | 200; employee records using original CSV column names |
| GET | `/api/reports/payroll` | 200; actual record count and monetary totals |
| GET | `/api/payslips/1` | 200; employee details, pay period and figures |
| POST | `/api/payslips/1/generate` | 200; PDF uploaded and S3 object key returned |

The POST request needs no body. The address bar in your browser sends GET, so
use Postman for this POST request.

PowerShell alternative:

```powershell
$base = 'PASTE-YOUR-ACTUAL-DEPLOYED-BASE-URL'
curl.exe -i "$base/api/employees"
curl.exe -i "$base/api/reports/payroll"
curl.exe -i "$base/api/payslips/1"
curl.exe -i -X POST "$base/api/payslips/1/generate"
```

Example POST response (your bucket name will differ):

```json
{
  "success": true,
  "message": "Payslip generated and uploaded.",
  "data": {
    "payroll_id": 1,
    "bucket": "your-bucket",
    "payslip_key": "payslips/1.pdf",
    "payslip_file": "s3://your-bucket/payslips/1.pdf"
  }
}
```

`payslip_key` satisfies the object-key requirement. `s3://...` is a storage URI,
not a public browser download URL. Keep the bucket private and verify the PDF
using the signed-in S3 console. Repeating POST overwrites the same object's PDF.

Unknown payroll IDs return 404; the wrong method on a known route returns 405.
Internal failures return JSON with status 500 and log details on the server.

For AWS application errors:

```powershell
serverless.cmd logs -f api
```

Or open Lambda -> your function -> Monitor -> View CloudWatch logs.

## 10. Verify S3 and capture evidence

1. After a successful POST, open S3 -> your bucket -> `payslips/`.
2. Confirm `1.pdf` exists.
3. Select it and choose Open or Download.
4. Verify readable employee details, pay period, gross, deductions and net.
5. Save screenshots for all four requests, the S3 object and the opened PDF.

The API is a public lab API with no authentication. Use only the supplied
synthetic data. Real payroll data would require authentication and authorization.

## 11. Source-data details

The downloaded version 3 dataset has 500 employees and 600 payroll rows.
Employee primary key: `employees.id`; payroll primary key: `payroll.id`.
`payroll.employee_id` joins to `employees.id`.

The current payroll file has no deductions field. Deductions are gross minus net.
Some synthetic records have net above gross or end dates before start dates.
This project preserves those records instead of silently modifying them.
Negative deductions are possible. Do not present this dataset as real payroll.
Currency is unspecified; the PDF does not invent a peso or dollar currency.

Totals use integer cents during arithmetic. JSON may show `100.0` rather than
`100.00`; that is normal number formatting. PDFs show two decimal places.

Verified totals for these downloaded files:

| Measure | Amount |
| --- | ---: |
| Payroll records | 600 |
| Gross pay | 4,981,004.63 |
| Deductions | 849,478.70 |
| Net pay | 4,131,525.93 |

The source has 245 records with net above gross and 311 reversed pay periods.

## 12. Submit on GitHub

Complete `SUBMISSION.md` with actual URLs, evidence, issue and reflection.
Create an empty GitHub repository using the website, then run:

```powershell
git status
git add .
git diff --cached --stat
git commit -m "Complete serverless payroll API activity"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/serverless-payroll-api.git
git push -u origin main
```

The folder already contains a Git repository. If `origin` already exists, inspect
it with `git remote -v` before changing it. Configure your Git name/email if asked.
Review staged files for credentials before committing. Include both CSVs,
composer.json, composer.lock, PHP source and serverless.yml. `vendor/`, local
preview artifacts and credentials are excluded by `.gitignore`.

Do not delete the AWS application before your instructor has graded it.
After grading, `serverless.cmd remove` removes the app stack. Your separately
created S3 bucket and IAM role require separate cleanup; remove only your own
resources in the shared account.

## References

- Bref: https://bref.sh/docs/default/getting-started
- Deployment tools: https://bref.sh/docs/setup
- Dataset: https://databasesample.com/datasets/employees-and-payroll
- Dompdf: https://github.com/dompdf/dompdf
- AWS SDK for PHP: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/
