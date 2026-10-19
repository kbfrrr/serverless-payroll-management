# Lambda role for this class activity

Create a new role in IAM, selecting AWS service -> Lambda as the trusted entity.
Use a unique role name such as katri-payroll-lambda-role.

Attach these managed policies for the full-S3-access setup requested for the lab:

1. AWSLambdaBasicExecutionRole
   arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole
2. AmazonS3FullAccess
   arn:aws:iam::aws:policy/AmazonS3FullAccess

AmazonS3FullAccess grants broad S3 permissions, including access to other buckets
in the shared account, subject to other account controls. Use the class-approved
role only for your own project. The assignment's narrower alternative is
s3:PutObject on arn:aws:s3:::YOUR-BUCKET/payslips/*.

The trust relationship should match lambda-trust.json. Copy the actual role ARN
from IAM, including any path, into the PAYROLL_ROLE_ARN environment variable.

This is the application's execution role. It does not provide deployment
credentials for your computer. Obtain approved deployment credentials from your
instructor. That deployment identity also needs iam:PassRole for this role and
permission to deploy CloudFormation, Lambda, API Gateway, logs and deployment
storage. AWSLambda_FullAccess alone does not supply every deployment permission.
