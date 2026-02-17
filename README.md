# CloudPanel Backup Manager

A lightweight and professional backup management panel for CloudPanel environments.

CloudPanel Backup Manager enables system administrators and hosting professionals to manage manual and scheduled backups across multiple hosted websites from a centralized PHP interface.

---

## Features

- CloudPanel site synchronization via helper script (`list_cloudpanel_sites.sh`)
- Manual backups: site ZIP and database dump
- Scheduled backups (cron-based), with backup time and timezone configuration
- Backup management: list, download, and delete backups
- Admin login with “remember me” and optional 2FA
- General settings: system title, backup time, timezone

---

## Requirements

- CloudPanel (Generic PHP site)
- PHP 8.x
- MySQL or MariaDB
- Shell access (if required for site synchronization)
- Cron (if automated backups are supported)

---

## Installation

1. Create a Generic PHP site in CloudPanel.
2. Upload project files to the document root.
3. Import the database using `backup-admin-schema.sql`.
4. Copy `backup-admin/config.example.php` to `backup-admin/config.php` and configure credentials.
5. Create the backup directory and ensure proper permissions.
6. Configure cron to execute `auto_backup_cron.php` (if applicable).

---

## CloudPanel Integration

If the helper script `list_cloudpanel_sites.sh` is present, the application can synchronize CloudPanel sites into the panel:

1. Install the script on the server:
   - Copy the file to `/usr/local/bin/list_cloudpanel_sites.sh`
   - `chmod 755 /usr/local/bin/list_cloudpanel_sites.sh`
   - `chown root:root /usr/local/bin/list_cloudpanel_sites.sh`
2. Allow the site user to run the script via sudo without a password:
   - Create `/etc/sudoers.d/backup-admin-sync` with:
     - `backup-admin ALL=(root) NOPASSWD:/usr/local/bin/list_cloudpanel_sites.sh`
   - `chmod 440 /etc/sudoers.d/backup-admin-sync`
3. Test:
   - `sudo /usr/local/bin/list_cloudpanel_sites.sh`
   - Expected output format per line:
     - `site_user|domain|docroot|db_host|db_name|db_user|db_pass`
4. In the web UI, use the Sync action to import/update sites.

---

## Security Notes

- Never commit `config.php` with real credentials.
- Do not store production backups inside the repository.
- Restrict sudo permissions to the exact helper script path.
- Always use HTTPS in production environments.

---

## Roadmap

- Encrypted backups
- Remote storage integration (S3 / Wasabi / Backblaze)
- One-click restore
- Multi-server support
- Enterprise monitoring

---

## Author

CloudPanel Backup Manager was created and is maintained by Weslley Harakawa.

Entrepreneur and software engineer. Active CloudPanel user and builder of self-managed infrastructure projects across multiple production environments. This project was created from real-world operational needs while managing CloudPanel-based servers.

If this project helps you, consider supporting its development:

Buy me a coffee:  
`https://buymeacoffee.com/weslleyaharakawa`

Schedule a meeting:  
`https://meet.harakawa.tech`

Connect on LinkedIn:  
`https://www.linkedin.com/in/weslleyharakawa/`

---

This project is not affiliated with or endorsed by CloudPanel.
