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
