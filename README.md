# fusionpbx-app-yealink_rps


## Installation
```bash
apt install ffmpeg
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-yealink_rps.git yealink_rps
chown -R www-data:www-data /var/www/fusionpbx
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

The upgrade step creates menu, permisisons and registers the default settings.

## Menu
After updating the menu you will find Yealink RPS under Advanced.

Menu -> Advanced -> Yealink RPS