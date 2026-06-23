FalconEyeGPS — Clean ZIP v2 (Full Blade Views)
=============================================

This archive contains only the custom application files for the FalconEyeGPS project.
DO NOT copy Laravel core files from this ZIP. Instead, create a fresh Laravel 12 project
and then copy these files into it (overwriting the matching app files).

Quick install:
1. Create fresh Laravel 12 project:
   composer create-project laravel/laravel:^12 gps-app
2. Copy files from this ZIP into the project root.
3. Run composer install, npm install, npm run dev
4. Configure .env, run migrations and seeders:
   php artisan migrate
   php artisan db:seed --class=DemoSeeder

Admin login: admin@demo.test / password
User login: user@demo.test / password
