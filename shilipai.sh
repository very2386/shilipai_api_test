#!/bin/bash
cd /var/www/html/shilipai_test_api
php artisan queue:work --tries=3
exec /var/www/html/shilipai_test_api/shilipai.sh
