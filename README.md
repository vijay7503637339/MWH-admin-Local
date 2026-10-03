# MWH Local Admin & Backend

Standalone backend and admin panel for the Maan World Local app.

## Production layout

- Admin: `/admin`
- API: `/api`
- Database: dedicated MySQL database `sywwzxsv_mwh_local`
- Database user: `sywwzxsv_mwh_local`

## Deployment

Copy `api/config/database.php.example` to `api/config/database.php` on the server and add the production database credentials.

Copy `api/config/security.php.example` to `api/config/security.php` and set a long random encryption key.

Do not commit either production config file.

## Database

Import `database/schema.sql` into the new Local database.

Create the first Local admin using the documented bootstrap flow in `database/README.md`.

## Flutter

The Local Flutter app should point to this backend root:

`https://webstripetechnologies.com/MWH-admin-Local/api`


## Current Local business flow

The Local module is intentionally standalone from MWH Hospitality.

### Production database migrations

After pulling the repository, apply the current migrations to the dedicated Local database:

```bash
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/015_payment_settings.sql
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/016_staff_payouts.sql
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/017_duty_attendance.sql
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/018_job_role_pricing.sql
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/019_assignment_payout_split.sql
mysql -u YOUR_LOCAL_DB_USER -p YOUR_LOCAL_DB_NAME < database/migrations/020_fcm_tokens.sql
```

These add company payment settings (UPI, bank details and QR storage), the staff payout ledger, the duty arrival attendance timestamp, job-role pricing defaults, and the split between gross contractor assignment amount and net staff payout.

### Finance workflow

Contractors see the company UPI/bank/QR details after staff selection and submit a payment reference. Finance/super admins approve or reject that payment from the Local admin panel. The staff duty cannot be started until the contractor payment has an approved amount covering the assignment payout.

When a staff member completes an active duty, a pending staff payout record is created. Finance/super admins process the payout from:

`admin/payouts.php`

### Local admin pages

- `admin/payments.php` — company payment details and contractor payment approval
- `admin/payouts.php` — staff payout processing
- `admin/duty.php` — staff duty attendance (arrival, start and completion)
- `admin/requirements.php` — contractor requirements
- `admin/contractors.php` — contractor accounts
- `admin/staff.php` — staff accounts


## Firebase push notifications

The Flutter app uses Firebase Cloud Messaging (FCM) for push notifications. The Android Firebase config is kept in the Flutter app at:

`android/app/google-services.json`

The backend sends notifications through the FCM HTTP v1 API. Keep the Firebase service-account JSON outside Git and outside the public web root when possible. The backend looks for `api/config/firebase-service-account.json`. A dummy template is included at `api/config/firebase-service-account.json.example`; copy it to `firebase-service-account.json` on the server and replace the placeholder values with the real Firebase service-account JSON. The real credential is ignored by Git.

The Firebase service account needs permission to send FCM messages. After placing the credential on the server, use `admin/notifications.php` to send a manual push.

Automatic pushes included:
- New contractor job posted -> all verified staff with registered FCM tokens
- Staff selected for a job -> selected staff
- New staff application -> contractor
- Admin manual notification -> selected user group or individual user

The in-app notification record is also stored in `local_notifications`, so the message remains visible in the app when a device has no active push token.
