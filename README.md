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
