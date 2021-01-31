#!/bin/sh

echo "Time: $(date -Iseconds)" >> /var/log/httpd/uesppatreon.log
php /home/uesp/www/w/extensions/UespPatreon/UespPatreonUpdate.php >> /var/log/httpd/uesppatreon.log 2>&1
