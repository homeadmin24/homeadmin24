#!/bin/bash

# homeadmin24 Project Initialization Script
# This script sets up the development environment for the homeadmin24 WEG management system

set -e  # Exit on error

echo "🏠 homeadmin24 Project Initialization"
echo "================================="

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]); then
    echo "❌ Error: PHP 8.2 or higher is required. Current version: $PHP_VERSION"
    exit 1
fi
echo "✅ PHP version $PHP_VERSION is compatible"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Error: Composer is not installed. Please install Composer first."
    exit 1
fi
echo "✅ Composer is installed"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ Error: npm is not installed. Please install Node.js first."
    exit 1
fi
echo "✅ npm is installed"

# Check if MySQL is running
if ! command -v mysql &> /dev/null; then
    echo "⚠️  Warning: MySQL client not found. Make sure MySQL server is running."
else
    echo "✅ MySQL client is available"
fi

# Create .env.local if it doesn't exist
if [ ! -f .env.local ]; then
    echo ""
    echo "📝 Creating .env.local for local configuration..."
    cat > .env.local << EOL
# Local environment configuration
# This file overrides .env settings for your local development

###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=\$(openssl rand -hex 32)
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
# Configure your database connection here
DATABASE_URL="mysql://app:changeme@127.0.0.1:3306/homeadmin24?serverVersion=8.0.32&charset=utf8mb4"
###< doctrine/doctrine-bundle ###
EOL
    echo "✅ Created .env.local (please update DATABASE_URL with your credentials)"
else
    echo "✅ .env.local already exists"
fi

# Install PHP dependencies
echo ""
echo "📦 Installing PHP dependencies..."
composer install --no-interaction

# Install JavaScript dependencies
echo ""
echo "📦 Installing JavaScript dependencies..."
npm install

# Database setup
echo ""
echo "🗄️  Setting up database..."
echo "Please make sure your MySQL server is running and accessible."
echo ""
read -p "Do you want to create the database? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php bin/console doctrine:database:create --if-not-exists
    echo "✅ Database created (or already exists)"
fi

# Run migrations
echo ""
read -p "Do you want to run database migrations? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php bin/console doctrine:migrations:migrate --no-interaction
    echo "✅ Database migrations completed"
fi

# Load fixtures
echo ""
read -p "Do you want to load sample data (fixtures)? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php bin/console doctrine:fixtures:load --no-interaction
    echo "✅ Sample data loaded"
fi

# Build assets
echo ""
echo "🎨 Building frontend assets..."
npm run build
echo "✅ Frontend assets built"

# Clear cache
echo ""
echo "🧹 Clearing cache..."
php bin/console cache:clear
echo "✅ Cache cleared"

# Make backup script executable
if [ -f bin/backup_db.sh ]; then
    chmod +x bin/backup_db.sh
    echo "✅ Database backup script is executable"
fi

echo ""
echo "🎉 homeadmin24 project initialization complete!"
echo ""
echo "📋 Next steps:"
echo "1. Update database credentials in .env.local if needed"
echo "2. Start the development server:"
echo "   - With Symfony CLI: symfony server:start"
echo "   - Without Symfony CLI: php -S localhost:8000 -t public"
echo "3. In another terminal, run: npm run watch (for auto-rebuilding assets)"
echo "4. Open http://localhost:8000 in your browser"
echo ""
echo "📚 Useful commands:"
echo "- Generate Hausgeldabrechnung: php bin/console app:generate-hausgeldabrechnung <unit_id> <year>"
echo "- Backup database: ./bin/backup_db.sh [description]"
echo "- Run tests: php bin/phpunit"
echo "- Check code style: vendor/bin/php-cs-fixer fix --dry-run"
echo ""