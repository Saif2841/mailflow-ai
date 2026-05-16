<div align="center">

<img src="https://img.shields.io/badge/mailflow--ai-v1.0.0-6366f1?style=for-the-badge&labelColor=1e1e2e" alt="version" />
<img src="https://img.shields.io/badge/PHP-8.3-7c3aed?style=for-the-badge&logo=php&logoColor=white&labelColor=1e1e2e" alt="PHP 8.3" />
<img src="https://img.shields.io/badge/Symfony-7.1-000000?style=for-the-badge&logo=symfony&logoColor=white&labelColor=1e1e2e" alt="Symfony 7.1" />
<img src="https://img.shields.io/badge/Supabase-free_tier-3ecf8e?style=for-the-badge&logo=supabase&logoColor=white&labelColor=1e1e2e" alt="Supabase" />
<img src="https://img.shields.io/badge/license-MIT-f59e0b?style=for-the-badge&labelColor=1e1e2e" alt="MIT License" />

<br /><br />

# MailFlow AI

### Production-grade AI email automation for invoice processing & e-commerce support

*Classify · Extract · Approve · Schedule — all on a $0 stack*

<br />

[**Get Started**](#-quick-start) · [**Architecture**](#-architecture) · [**Stack**](#-tech-stack) · [**Contributing**](#-contributing)

<br />

</div>

---

## What is MailFlow AI?

MailFlow AI is a self-hosted, AI-powered email operations platform that replaces manual email triage with a fully automated decision-making system. It handles two core workflows:

- **Invoice automation** — receives supplier invoices via email, runs OCR, extracts data, routes for approval, stamps PDFs, and generates payment schedules twice a month
- **E-commerce support** — classifies customer emails, matches orders, drafts empathetic replies with Ollama AI, and escalates edge cases automatically

Everything runs locally or on free tiers. No paid APIs. No subscriptions. No vendor lock-in.

---

## Architecture

```
Gmail / IMAP Inbox
        │
        ▼
  n8n (self-hosted)          ← watches inboxes, triggers on new email
        │
        ▼
  Symfony Webhook            ← POST /api/webhook/email-intake
        │
        ├── saves email metadata ──────────────────► Supabase (PostgreSQL)
        └── uploads attachments ───────────────────► Supabase Storage
                │
                ▼
      Symfony Messenger Queue (Redis)
                │
                ▼
        Ollama + Llama 3.1           ← classifies email type
                │
                ▼
        Policy Engine                ← applies company rules
                │
          ┌─────┴──────┐
          ▼             ▼
  Invoice Workflow   Support Workflow
          │             │
  Tesseract OCR    Order lookup
  Field extraction  Draft reply (AI)
  PDF stamp         Escalation check
  Supabase insert   Human review
  Approval email    Send reply
          │
          ▼
  15th / 30th → QuickBooks CSV + payment schedule email
```

---

## Tech Stack

| Layer | Tool | Why |
|---|---|---|
| Backend | Symfony 7.1 (PHP 8.3) | Robust, async-ready, great DX |
| Database | Supabase (hosted PostgreSQL) | Free, has Auth + Storage + Realtime built in |
| AI / LLM | Ollama + Llama 3.1 8B | Fully local — no API costs, no data leaving your server |
| OCR | Tesseract OCR | Open-source, battle-tested |
| PDF stamping | PyMuPDF (Python 3.12) | Fast, precise PDF manipulation |
| Email automation | n8n self-hosted | Visual workflows, native Supabase node |
| Job queue | Symfony Messenger + Redis | Async processing, retry logic |
| Dashboard | Twig + Bootstrap 5 + Alpine.js | Lightweight, no build step needed |
| File storage | Supabase Storage (1 GB free) | Signed URLs, replaces S3 or Google Drive |
| Auth | Supabase Auth + JWT | Roles, magic links, no custom auth code |
| Realtime | Supabase Realtime | Dashboard auto-updates without polling |
| DevOps | Docker Compose | One command to spin up everything |

> Supabase is **cloud-hosted** (free tier) — it does **not** run in Docker. Everything else does.

---

## Features

- **AI email classification** — Ollama + Llama 3.1 classifies every incoming email into `vendor_invoice`, `refund_request`, `shipping_issue`, `customer_complaint`, and more
- **Invoice OCR pipeline** — Tesseract extracts text from PDF/image attachments; AI parses vendor, amount, dates, and line items
- **PDF date-stamping** — PyMuPDF stamps every received invoice with a `RECEIVED: YYYY-MM-DD | REF: INV-XXX` overlay
- **Approval workflows** — secure token links emailed to approvers; no login required to approve/reject
- **Duplicate detection** — blocks invoices with already-seen invoice numbers before they hit the approval queue
- **Policy engine** — configurable rules route invoices by amount threshold, flag escalations, and request missing order numbers
- **Payment scheduling** — automated payment batch CSVs generated on the 15th and 30th, emailed to finance
- **QuickBooks export** — QuickBooks-compatible CSV format for manual import (no paid QuickBooks API needed)
- **E-commerce reply drafting** — AI writes empathetic customer replies following your refund/return policy
- **Real-time dashboard** — Supabase Realtime highlights new emails and invoices in the dashboard as they arrive
- **Full audit trail** — every classification, approval, escalation, and status change is logged

---

## Project Structure

```
mailflow-ai/
├── .github/
│   └── workflows/
│       └── ci.yml                  # GitHub Actions (lint + tests)
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── n8n/
│   ├── email-intake.json           # importable n8n workflow
│   └── payment-schedule.json       # cron workflow for 15th/30th
├── python/
│   ├── requirements.txt
│   └── stamp_pdf.py                # PyMuPDF PDF stamper
├── supabase/
│   └── schema.sql                  # full schema + RLS + indexes + triggers
├── symfony/                        # Symfony project root
│   ├── config/
│   ├── src/
│   │   ├── Controller/
│   │   │   ├── EmailIntakeController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── CaseController.php
│   │   │   └── DashboardController.php
│   │   ├── Service/
│   │   │   ├── SupabaseService.php
│   │   │   ├── OllamaService.php
│   │   │   ├── EmailClassifierService.php
│   │   │   ├── PolicyEngineService.php
│   │   │   ├── OcrService.php
│   │   │   ├── InvoiceParserService.php
│   │   │   ├── PdfStampService.php
│   │   │   ├── ApprovalService.php
│   │   │   └── ReplyDrafterService.php
│   │   ├── Message/
│   │   └── MessageHandler/
│   ├── templates/
│   ├── .env                        # committed — safe defaults only
│   └── .env.local                  # gitignored — your real secrets
├── docker-compose.yml
├── .gitignore
├── .env.example                    # template with empty values
├── SETUP.md                        # full Symfony bundle install guide
├── SUPABASE_SETUP.md               # Supabase dashboard checklist
└── README.md
```

---

## Quick Start

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker + Compose on Linux)
- A free [Supabase](https://supabase.com) account and project
- [VS Code](https://code.visualstudio.com/) with the extensions in `.code-workspace`
- Git

---

### 1. Clone the repository

```bash
git clone https://github.com/YOUR-USERNAME/mailflow-ai.git
cd mailflow-ai
```

---

### 2. Configure environment

```bash
cp .env.example symfony/.env.local
```

Open `symfony/.env.local` and fill in:

```dotenv
SUPABASE_URL=https://yourproject.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
SUPABASE_JWT_SECRET=your-jwt-secret
SUPABASE_STORAGE_BUCKET=invoices

OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b

MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
MAILER_DSN=smtp://user:pass@smtp.example.com:587
FINANCE_EMAIL=finance@yourcompany.com
```

Find your Supabase keys at: **Project Settings → API**

---

### 3. Set up Supabase

1. In your Supabase dashboard, open the **SQL Editor**
2. Paste the contents of `supabase/schema.sql` and run it
3. Go to **Storage** → create a bucket named `invoices` (set to private)
4. Go to **Database → Replication** → enable Realtime on `emails`, `invoice_records`, `customer_cases`

Full checklist in `SUPABASE_SETUP.md`.

---

### 4. Start Docker services

```bash
docker compose up -d
```

Check everything is running:

```bash
docker compose ps
```

You should see these services healthy:

| Service | URL |
|---|---|
| Symfony app | http://localhost:8080 |
| n8n | http://localhost:5678 |
| Ollama API | http://localhost:11434 |
| Redis | localhost:6379 |

---

### 5. Install Symfony dependencies

```bash
docker compose exec php-fpm composer install
```

---

### 6. Pull the AI model

```bash
docker exec -it ai_email_ollama ollama pull llama3.1:8b
```

This downloads ~4.7 GB. Run it once; the model persists in the Docker volume.

Test it:

```bash
docker exec -it ai_email_ollama ollama run llama3.1:8b "Classify this email: invoice attached for $1,200"
```

---

### 7. Install Python dependencies

```bash
docker compose exec python-helper pip install -r /app/requirements.txt
```

---

### 8. Import n8n workflows

1. Open n8n at http://localhost:5678 (default login: `admin` / `changeme` — **change this**)
2. Go to **Workflows → Import from file**
3. Import `n8n/email-intake.json`
4. Import `n8n/payment-schedule.json`
5. Configure your Gmail credentials in each workflow

---

### 9. Start the Messenger worker

```bash
docker compose exec -d php-fpm php bin/console messenger:consume async -vv
```

---

You're live. Visit http://localhost:8080/dashboard to see the admin panel.

---

## Database schema

All tables live in Supabase. The full schema is in `supabase/schema.sql`.

| Table | Purpose |
|---|---|
| `emails` | Every incoming email (metadata + status) |
| `email_attachments` | Attachment records with Supabase Storage paths |
| `ai_classifications` | Ollama classification results + confidence |
| `invoice_records` | Extracted invoice data + approval/payment status |
| `approval_requests` | Secure token-based approval links |
| `orders` | E-commerce orders imported from Shopify/WooCommerce CSV |
| `customer_cases` | Customer support cases with AI draft replies |
| `case_events` | Timeline of every action on a case |
| `payment_schedules` | Bi-monthly payment batches |
| `payment_schedule_items` | Line items per payment batch |
| `audit_logs` | Full system event log |

Row Level Security is enabled on all tables. Roles: `admin`, `approver`, `finance`, `support`.

---

## Useful commands

```bash
# View all service logs
docker compose logs -f

# View logs for a specific service
docker compose logs -f php-fpm
docker compose logs -f n8n

# Run a Symfony console command
docker compose exec php-fpm php bin/console about

# Generate payment schedule manually
docker compose exec php-fpm php bin/console app:generate-payment-schedule

# Import orders from CSV
docker compose exec php-fpm php bin/console app:sync-orders-csv

# Restart a service
docker compose restart php-fpm

# Open a shell in the PHP container
docker compose exec php-fpm bash

# Check Ollama models
docker exec ai_email_ollama ollama list
```

---

## Supabase free tier limits

| Resource | Free limit |
|---|---|
| Database | 500 MB |
| Storage | 1 GB |
| Bandwidth | 2 GB / month |
| Auth users | 50,000 MAU |
| Realtime | 50 MB messages / month |
| Edge Functions | 500,000 invocations / month |

For a small-to-medium invoice volume (< 500 invoices/month), the free tier is more than sufficient.

---

## Troubleshooting

**Docker services not starting**
```bash
docker compose logs
docker compose restart [service-name]
```

**Supabase connection errors**
- Verify `SUPABASE_URL` and keys in `.env.local`
- Check your Supabase project is not paused (free projects pause after 1 week of inactivity)
- Confirm RLS policies allow the `service_role` key

**Ollama not responding**
```bash
# Check if model is loaded
docker exec ai_email_ollama ollama list

# Re-pull if missing
docker exec ai_email_ollama ollama pull llama3.1:8b
```

**n8n workflow not triggering**
```bash
docker compose logs n8n
# Verify Gmail credentials and OAuth token in the workflow node
# Check Redis is running for the queue
```

**PDF stamping failing**
```bash
docker compose logs python-helper
# Ensure PyMuPDF is installed: docker compose exec python-helper pip list | grep PyMuPDF
```

---

## Security checklist before production

- [ ] Never commit `.env.local` — it contains your Supabase service role key
- [ ] Change default n8n credentials (`admin` / `changeme`)
- [ ] Rotate `APP_SECRET` to a strong random value
- [ ] Review all Supabase RLS policies match your team's access needs
- [ ] Use `SUPABASE_ANON_KEY` only for client-side operations; `SERVICE_ROLE_KEY` only on the server
- [ ] Set up HTTPS (Nginx + Let's Encrypt or Cloudflare Tunnel) before exposing to the internet
- [ ] Enable 2FA on your GitHub and Supabase accounts

---

## Contributing

1. Fork the repo and create your branch: `git checkout -b feat/your-feature`
2. Commit your changes: `git commit -m "feat: describe your change"`
3. Push to the branch: `git push origin feat/your-feature`
4. Open a Pull Request

Please follow [Conventional Commits](https://www.conventionalcommits.org/) for commit messages.

---

## License

MIT — free for personal and commercial use. See [LICENSE](LICENSE).

---

<div align="center">

Built with Symfony · Supabase · Ollama · n8n · Docker · Bootstrap 5

</div>
