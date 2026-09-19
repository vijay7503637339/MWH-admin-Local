# MWH Local Database

Import `schema.sql` into the dedicated Local database.

Expected cPanel credentials:

- Database: `sywwzxsv_mwh_local`
- User: `sywwzxsv_mwh_local`

The production password is not stored in GitHub.

## First admin

After deployment, create the first row in `local_admin_users` using a password hash generated with PHP:

`php -r "echo password_hash('YOUR_ADMIN_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`

Then insert the email/name/hash with role `super_admin` through the admin bootstrap SQL or the server's secure DB tool.

