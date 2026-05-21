# Symfony Project Setup Commands

## 1. Create Symfony Project

```bash
# Create new Symfony 7.4 project
composer create-project symfony/skeleton:"7.4.*" .

# Or if you already have the project structure
composer require symfony/web-app
```

## 2. Install Required Symfony Bundles

```bash
# Web app meta-package (recommended)
composer require symfony/web-app

# Core bundles and components
composer require symfony/http-client symfony/messenger symfony/redis-messenger symfony/serializer symfony/validator symfony/monolog-bundle symfony/mime symfony/uid nyholm/psr7

# Dev tools
composer require --dev symfony/maker-bundle symfony/debug-bundle symfony/var-dumper
```

## 3. Configure Symfony for Docker

```bash
# Create public directory if it doesn't exist
mkdir -p public

# Create storage directories
mkdir -p storage/invoices
mkdir -p storage/temp
mkdir -p storage/processed

# Set permissions (Linux/Mac)
chmod -R 775 storage
chmod -R 775 var
```

## 4. Generate Symfony Configuration

```bash
# Generate controller
php bin/console make:controller DashboardController

# Generate entity (example: Email)
php bin/console make:entity Email

# Generate messenger message
php bin/console make:message ProcessEmail
```

## 5. Configure Messenger (Redis)

Edit `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: 'redis://redis:6379/messages'
                options:
                    auto_setup: false
        routing:
            'App\Message\ProcessEmail': async
```

## 6. Configure Doctrine for Supabase

Edit `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        driver: 'pdo_pgsql'
        server_version: '15.0'
        charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

## 7. Start Docker Services

```bash
# Start all services
docker-compose up -d

# Check services status
docker-compose ps

# View logs
docker-compose logs -f
```

## 8. Install Ollama Model

```bash
# Access Ollama container
docker exec -it ai_email_ollama bash

# Pull Llama 3.1 8B model
ollama pull llama3.1:8b

# Test the model
ollama run llama3.1:8b "Hello, how are you?"

# Exit container
exit
```

## 9. Install Python Dependencies

```bash
# Create requirements.txt in python directory
cat > python/requirements.txt << EOF
pymupdf==1.23.8
Pillow==10.1.0
requests==2.31.0
python-dotenv==1.0.0
EOF

# Install dependencies in Python container
docker exec -it ai_email_python pip install -r /app/requirements.txt
```

## 10. Verify Setup

```bash
# Check Symfony
php bin/console about

# Check database connection
php bin/console doctrine:database:check-connection

# Create database schema (if needed)
php bin/console doctrine:schema:create

# Run migrations
php bin/console doctrine:migrations:migrate
```

## 11. Start Symfony Messenger Worker

```bash
# In one terminal, start the messenger worker
php bin/console messenger:consume async -vv

# Or run in background
nohup php bin/console messenger:consume async -vv > var/messenger.log 2>&1 &
```

## 12. Access Services

- **Symfony App**: http://localhost:8080
- **n8n**: http://localhost:5678 (default: admin/changeme)
- **Ollama API**: http://localhost:11434
- **Redis**: localhost:6379
