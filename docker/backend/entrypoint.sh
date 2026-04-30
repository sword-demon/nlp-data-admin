#!/bin/sh
set -e

cd /opt/www

# Always re-install if vendor/autoload.php is missing (e.g. first run or host OS mismatch)
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader --ignore-platform-reqs
fi

# Run migrations
echo "Running database migrations..."
php bin/hyperf.php migrate || echo "Migration warning: DB may not be ready, will retry on next start"

# Start Hyperf
echo "Starting Hyperf server..."
php bin/hyperf.php start
