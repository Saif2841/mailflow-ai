# AI Email Automation System

Production-grade AI-powered email automation system for invoice processing and approval workflows.

## Tech Stack (All Free)

- **Backend**: Symfony 7.1 (PHP 8.3)
- **Database**: Supabase (hosted PostgreSQL + REST API + Realtime + Auth + Storage)
- **Supabase PHP SDK**: supabase-community/supabase-php
- **Automation Engine**: n8n self-hosted via Docker
- **AI/LLM**: Ollama-compatible Cloud API (recommended) + Llama 3.1 8B
- **OCR**: Tesseract OCR (Docker container)
- **PDF Stamping**: PyMuPDF Python script (Docker)
- **Email Intake**: Gmail API (free) via n8n
- **Queue**: Symfony Messenger + Redis (Docker)
- **Frontend**: Twig + Bootstrap 5 + Alpine.js (CDN)
- **File Storage**: Supabase Storage (free 1 GB) for invoice PDFs
- **DevOps**: Docker Compose (n8n, Ollama, Redis, Nginx, PHP-FPM, Python helper)

## Why Supabase Over Local PostgreSQL?

- No Docker container needed for the database
- Built-in REST API (PostgREST) — n8n can query Supabase directly without Symfony
- Built-in file storage for invoice PDFs (replaces local filesystem or Google Drive)
- Built-in Auth for dashboard login (replaces Symfony security for the admin panel)
- Realtime subscriptions — dashboard auto-refreshes when new emails arrive
- Row Level Security (RLS) for role-based access (admin, approver, finance, support)

## Quick Start

### 1. Clone and Setup

```bash
# Navigate to project directory
cd /path/to/project

# Copy environment template
cp .env.local .env

# Edit .env with your Supabase credentials
nano .env
```

### 2. Start Docker Services

```bash
# Start all services
docker-compose up -d

# Check services status
docker-compose ps
```

### 3. Install Symfony Dependencies

```bash
# Create Symfony project (if not already done)
composer create-project symfony/skeleton:^7.1 .

# Install required bundles (see SETUP.md for full list)
composer require symfony/web-app
composer require supabase-community/supabase-php
composer require symfony/messenger
# ... see SETUP.md for complete list
```

### 4. Setup Supabase

Follow the checklist in `SUPABASE_SETUP.md` to:
- Create Supabase project
- Configure database tables
- Set up storage bucket
- Configure RLS policies
- Enable Realtime

### 5. Configure Ollama Cloud

Set the following in `.env.local` for local dev (do not commit secrets):

```
LLM_PROVIDER=ollama_cloud
LLM_BASE_URL=https://ollama.com
LLM_API_KEY=your_api_key_here
LLM_MODEL=llama3.1:8b
```

Production environment variables (set in your hosting provider):

```
LLM_PROVIDER=ollama_cloud
LLM_BASE_URL=https://ollama.com
LLM_API_KEY=prod_api_key_here
LLM_MODEL=llama3.1:8b
```

Test with Postman:

- Method: GET
- URL: http://127.0.0.1:8000/debug/llm-test
- Expected: {"status":"ok","message":"LLM reachable"}

### 6. Install Python Dependencies

```bash
# Create requirements.txt
cat > python/requirements.txt << EOF
pymupdf==1.23.8
Pillow==10.1.0
requests==2.31.0
python-dotenv==1.0.0
EOF

# Install dependencies
docker exec -it ai_email_python pip install -r /app/requirements.txt
```

## Service URLs

- **Symfony App**: http://localhost:8080
- **n8n**: http://localhost:5678 (default: admin/changeme)
- **LLM API**: Ollama Cloud base URL (set via LLM_BASE_URL)
- **Redis**: localhost:6379

## Project Structure

```
.
├── docker/
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── default.conf
│   └── php/
│       └── php.ini
├── python/
│   ├── requirements.txt
│   └── stamp_pdf.py
├── storage/
│   ├── invoices/
│   ├── temp/
│   └── processed/
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Message/
│   └── Service/
├── templates/
├── docker-compose.yml
├── .env.local
├── .code-workspace
├── SETUP.md
├── SUPABASE_SETUP.md
└── README.md
```

## Configuration Files

### docker-compose.yml
Contains all Docker services:
- php-fpm: PHP 8.3 for Symfony
- nginx: Web server
- redis: Queue backend
- n8n: Workflow automation
- ollama: Local LLM
- python-helper: PDF stamping
- tesseract: OCR

### .env.local
Environment variables for:
- Supabase credentials
- Database connection
- Redis configuration
- n8n settings
- Ollama configuration
- Gmail API

### .code-workspace
VS Code workspace with recommended extensions:
- PHP Intelephense
- Symfony support
- Docker
- Thunder Client
- GitLens
- Supabase
- REST Client

## Database Schema

### Tables
- **emails**: Incoming emails from Gmail
- **invoices**: Extracted invoice data
- **approvals**: Approval workflow
- **audit_log**: Audit trail
- **user_roles**: User role assignments

### Storage
- **invoices**: Supabase Storage bucket for PDF files

## Workflow

1. **Email Intake**: n8n polls Gmail API for new emails
2. **Storage**: Attachments uploaded to Supabase Storage
3. **OCR**: Tesseract extracts text from PDFs
4. **AI Processing**: Ollama (Llama 3.1) analyzes and categorizes
5. **Data Extraction**: Invoice data stored in Supabase
6. **Approval**: Workflow triggers approval process
7. **PDF Stamping**: Python script stamps approved invoices
8. **Notification**: Realtime updates via Supabase Realtime

## Development

### Running Symfony Commands

```bash
# Inside PHP container
docker exec -it ai_email_php_fpm bash

# Or use docker-compose exec
docker-compose exec php-fpm php bin/console about
```

### Starting Messenger Worker

```bash
# In one terminal
docker-compose exec php-fpm php bin/console messenger:consume async -vv

# Or run in background
docker-compose exec -d php-fpm php bin/console messenger:consume async -vv
```

### Viewing Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f n8n
docker-compose logs -f php-fpm
```

## Documentation

- **SETUP.md**: Complete Symfony setup commands
- **SUPABASE_SETUP.md**: Supabase project setup checklist

## Security Notes

- Never commit `.env.local` or API keys to version control
- Keep `SUPABASE_SERVICE_ROLE_KEY` secret (bypasses RLS)
- Use `SUPABASE_ANON_KEY` for client-side operations
- Review RLS policies before production
- Change default n8n credentials in production

## Free Tier Limits (Supabase)

- Database: 500MB
- Storage: 1GB
- Bandwidth: 2GB/month
- Email: 3,000/month
- Auth: 50,000 MAU
- Edge Functions: 500,000 invocations/month

## Troubleshooting

### Docker Services Not Starting
```bash
# Check logs
docker-compose logs

# Restart specific service
docker-compose restart [service-name]
```

### Database Connection Issues
- Verify `DATABASE_URL` in `.env.local`
- Check Supabase project is active
- Ensure RLS policies allow access

### Ollama Model Not Working
```bash
# Check if model is pulled
docker exec -it ai_email_ollama ollama list

# Re-pull model
docker exec -it ai_email_ollama ollama pull llama3.1:8b
```

### n8n Workflow Issues
- Check n8n logs: `docker-compose logs n8n`
- Verify Redis connection for queue mode
- Test Supabase REST API connection

## License

MIT License - Free for personal and commercial use
