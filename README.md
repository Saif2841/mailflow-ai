<div align="center">

<img src="https://img.shields.io/badge/mailflow--ai-v1.0.0-6366f1?style=for-the-badge&labelColor=1e1e2e" alt="version" />
<img src="https://img.shields.io/badge/PHP-8.3-7c3aed?style=for-the-badge&logo=php&logoColor=white&labelColor=1e1e2e" alt="PHP 8.3" />
<img src="https://img.shields.io/badge/Symfony-7.1-000000?style=for-the-badge&logo=symfony&logoColor=white&labelColor=1e1e2e" alt="Symfony 7.1" />
<img src="https://img.shields.io/badge/Supabase-free_tier-3ecf8e?style=for-the-badge&logo=supabase&logoColor=white&labelColor=1e1e2e" alt="Supabase" />
<img src="https://img.shields.io/badge/Ollama-Llama_3.1-ff6b35?style=for-the-badge&labelColor=1e1e2e" alt="Ollama" />
<img src="https://img.shields.io/badge/n8n-self--hosted-ea4b71?style=for-the-badge&logo=n8n&logoColor=white&labelColor=1e1e2e" alt="n8n" />
<img src="https://img.shields.io/badge/license-MIT-f59e0b?style=for-the-badge&labelColor=1e1e2e" alt="MIT License" />

<br /><br />

# MailFlow AI

### Production-grade AI email automation for invoice processing & e-commerce support

*Classify · Extract · Approve · Schedule — all on a $0 stack*

<br />

[**Get Started**](#-quick-start) · [**Architecture**](#-architecture) · [**Stack**](#-tech-stack) · [**Dashboard**](#-role-based-dashboard) · [**Contributing**](#-contributing)

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

The diagram below shows the full end-to-end flow — from a raw incoming email all the way to role-based dashboard access, with every service and decision point mapped out.

<br />

<div align="center">

```
                        ┌─────────────────────┐
                        │   Gmail / IMAP       │
                        │  Watched by n8n      │
                        └──────────┬──────────┘
                                   │ new email
                                   ▼
                        ┌─────────────────────┐
                        │  POST /api/webhook   │       ┌──────────────────┐
                        │  /email-intake       │──────►│    Supabase       │
                        │                     │       │  DB + Storage     │
                        │  saves · queues job │       └──────────────────┘
                        └──────────┬──────────┘
                                   │ async job (Redis)
                                   ▼
                   ┌───────────────────────────────┐
                   │     EmailClassifierService     │
                   │  Ollama + Llama 3.1 8B (local) │
                   │  category · confidence · summary│
                   └──────────────┬────────────────┘
                                  │
                   ┌──────────────▼────────────────┐
                   │       PolicyEngineService      │
                   │   applies company rules        │
                   │   writes decision → audit_logs │
                   └──────────────┬────────────────┘
                                  │
                   ┌──────────────▼────────────────┐
                   │      WorkflowRouterService     │
                   │   dispatches Messenger message │
                   └──────┬──────────┬─────────────┘
                          │          │           │
                    invoice       escalate    support
                          │          │           │
              ┌───────────▼─┐  ┌─────▼───┐  ┌───▼──────────┐
              │  Invoice     │  │ Human   │  │  Support      │
              │  Workflow    │  │ Review  │  │  Workflow     │
              │             │  │         │  │               │
              │ OCR extract │  │ urgent  │  │ order lookup  │
              │ PDF stamp   │  │ flagged │  │ AI draft reply│
              │ approval    │  │ assigned│  │ urgency check │
              │ pay schedule│  │ to role │  │ case events   │
              └──────┬──────┘  └────┬────┘  └──────┬────────┘
                     │              │               │
                     └──────────────┼───────────────┘
                                    ▼
                   ┌────────────────────────────────────────────┐
                   │              Supabase                       │
                   │  emails · invoices · cases · audit_logs    │
                   │  approvals · schedules · classifications    │
                   └──────────────────┬─────────────────────────┘
                                      │
                   ┌──────────────────▼─────────────────────────┐
                   │         Supabase Auth + JWT                 │
                   │    Symfony maps user → app role             │
                   └────────┬──────────────┬────────────────────┘
                            │              │              │
                         admin          finance        support
                            │              │              │
                   ┌────────▼───┐  ┌───────▼────┐  ┌────▼──────────┐
                   │   Admin    │  │  Finance   │  │   Support      │
                   │ /dashboard │  │ /invoices  │  │ /emails        │
                   │ /audit-log │  │ /payments  │  │ /cases         │
                   └────────────┘  └────────────┘  └───────────────┘
                                      │
                   ┌──────────────────▼─────────────────────────┐
                   │           Supabase Realtime                 │
                   │    dashboard auto-updates — no polling      │
                   └─────────────────────────────────────────────┘
```

</div>

<br />

---

## Tech Stack

| Layer | Tool | Why |
|---|---|---|
| Backend | Symfony 7.1 (PHP 8.3) | Robust, async-ready, great DX |
| Database | Supabase (hosted PostgreSQL) | Free — Auth + Storage + Realtime built in |
| AI / LLM | Ollama + Llama 3.1 8B | Fully local — no API costs, no data leaving your server |
| OCR | Tesseract OCR | Open-source, battle-tested invoice text extraction |
| PDF stamping | PyMuPDF (Python 3.12) | Fast, precise PDF manipulation |
| Email automation | n8n self-hosted | Visual workflows, native Supabase node |
| Job queue | Symfony Messenger + Redis | Async processing with retry logic |
| Dashboard | Twig + Bootstrap 5 + Alpine.js | Lightweight, no build step needed |
| File storage | Supabase Storage (1 GB free) | Signed URLs, replaces S3 or Google Drive |
| Auth | Supabase Auth + JWT | Roles, magic links, no custom auth code |
| Realtime | Supabase Realtime | Dashboard auto-updates without polling |
| DevOps | Docker Compose | One command to spin up the full stack |

> **Note:** Supabase is **cloud-hosted** (free tier) — it does **not** run in Docker. Everything else does.

---

## Features

### Email intelligence
- **AI classification** — Ollama + Llama 3.1 classifies every email into `vendor_invoice`, `refund_request`, `shipping_issue`, `customer_complaint`, and more — fully local, no API cost
- **Confidence scoring** — low-confidence results are automatically flagged for human review before any action is taken
- **Policy engine** — configurable business rules decide what happens next: amount thresholds, escalation keywords, duplicate detection, missing info requests

### Invoice & finance
- **Invoice OCR pipeline** — Tesseract extracts text from PDF/image attachments; Ollama parses vendor, amount, dates, and line items
- **PDF date-stamping** — PyMuPDF stamps every received invoice with `RECEIVED: YYYY-MM-DD | REF: INV-XXX`
- **Approval workflows** — secure token links emailed to approvers; no login required to approve or reject
- **Duplicate detection** — blocks invoices with already-seen invoice numbers before they reach the approval queue
- **Payment scheduling** — automated payment batch CSVs generated on the 15th and 30th, emailed to finance
- **QuickBooks export** — QuickBooks-compatible CSV for manual import (no paid QuickBooks API needed)

### Customer support
- **E-commerce reply drafting** — AI writes empathetic customer replies following your refund and return policy
- **Order matching** — automatically looks up the order from your imported Shopify/WooCommerce CSV
- **Auto-escalation** — detects chargeback threats, high-value orders, or repeat complainers and routes them to a human immediately

### Platform
- **Real-time dashboard** — Supabase Realtime highlights new emails and invoices as they arrive, no polling
- **Full audit trail** — every classification, approval, escalation, and status change is logged with actor and timestamp
- **Role-based access** — admin, finance, support, and approver roles each see only what they need

---

## Role-based dashboard

| Role | Pages | Can do |
|---|---|---|
| `admin` | `/dashboard`, `/audit-log` | Full system access, all stats, event log |
| `finance` | `/invoices`, `/payment-schedule` | Approve invoices, export CSV, send payment batches |
| `support` | `/emails`, `/cases` | View classified emails, manage cases, send AI-drafted replies |
| `approver` | `/approvals/{token}` | Approve or reject via secure link — no login needed |

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
│   └── payment-schedule.json       # cron workflow for 15th / 30th
├── python/
│   ├── requirements.txt
│   └── stamp_pdf.py                # PyMuPDF PDF stamper
├── supabase/
│   └── schema.sql                  # full schema + RLS + indexes + triggers
├── symfony/
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

Open `symfony/.env.local` and fill in your values:

```dotenv
# Supabase — get these from Project Settings → API
SUPABASE_URL=https://yourproject.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
SUPABASE_JWT_SECRET=your-jwt-secret
SUPABASE_STORAGE_BUCKET=invoices

# Ollama (runs in Docker)
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b

# Queue
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages

# Email sending
MAILER_DSN=smtp://user:pass@smtp.example.com:587
FINANCE_EMAIL=finance@yourcompany.com
```

---

### 3. Set up Supabase

1. Open the **SQL Editor** in your Supabase dashboard
2. Paste `supabase/schema.sql` and run it — this creates all tables, indexes, RLS policies, and triggers
3. Go to **Storage** → create a bucket named `invoices` (private)
4. Go to **Database → Replication** → enable Realtime on `emails`, `invoice_records`, `customer_cases`

> Full step-by-step checklist in `SUPABASE_SETUP.md`

---

### 4. Start Docker services

```bash
docker compose up -d
```

Verify everything is healthy:

```bash
docker compose ps
```

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

This downloads ~4.7 GB once. The model persists in a Docker volume across restarts.

Test it works:

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

1. Open n8n at http://localhost:5678
2. Default login: `admin` / `changeme` — **change this immediately**
3. Go to **Workflows → Import from file**
4. Import `n8n/email-intake.json`
5. Import `n8n/payment-schedule.json`
6. Configure your Gmail OAuth credentials in each workflow node

---

### 9. Start the Messenger worker

```bash
docker compose exec -d php-fpm php bin/console messenger:consume async -vv
```

---

You're live. Open http://localhost:8080/dashboard to access the admin panel.

---

## Database Schema

All tables live in Supabase. Row Level Security is enabled on every table. Full schema in `supabase/schema.sql`.

| Table | Purpose |
|---|---|
| `emails` | Every incoming email — metadata, body, and processing status |
| `email_attachments` | Attachment file records with Supabase Storage paths |
| `ai_classifications` | Ollama classification output — category, confidence, summary |
| `invoice_records` | Extracted invoice data with approval and payment status |
| `approval_requests` | Secure token-based approval links (expire after 7 days) |
| `orders` | E-commerce orders imported from Shopify / WooCommerce CSV |
| `customer_cases` | Support cases with AI draft replies and urgency tracking |
| `case_events` | Full timeline of every action taken on a case |
| `payment_schedules` | Bi-monthly payment batches |
| `payment_schedule_items` | Line items within each payment batch |
| `audit_logs` | Immutable log of every system event with actor and timestamp |
| `user_roles` | Maps Supabase Auth users to app roles |

---

## Useful Commands

```bash
# Follow logs for all services
docker compose logs -f

# Follow logs for a specific service
docker compose logs -f php-fpm
docker compose logs -f n8n

# Check the Symfony app
docker compose exec php-fpm php bin/console about

# Generate payment schedule manually (also runs via n8n cron on 15th/30th)
docker compose exec php-fpm php bin/console app:generate-payment-schedule

# Import orders from a Shopify / WooCommerce CSV export
docker compose exec php-fpm php bin/console app:sync-orders-csv

# Restart a specific service
docker compose restart php-fpm

# Open a shell inside the PHP container
docker compose exec php-fpm bash

# List available Ollama models
docker exec ai_email_ollama ollama list

# Re-pull the AI model if missing
docker exec ai_email_ollama ollama pull llama3.1:8b
```

---

## Supabase Free Tier Limits

| Resource | Free limit |
|---|---|
| Database | 500 MB |
| Storage | 1 GB |
| Bandwidth | 2 GB / month |
| Auth users | 50,000 MAU |
| Realtime | 50 MB messages / month |
| Edge Functions | 500,000 invocations / month |

> For a typical small-to-medium workload (under 500 invoices/month), the free tier is more than sufficient. Free projects pause after 1 week of inactivity — keep this in mind for production.

---

## Troubleshooting

**Docker services not starting**
```bash
docker compose logs
docker compose restart [service-name]
```

**Supabase connection errors**
- Double-check `SUPABASE_URL`, `SUPABASE_ANON_KEY`, and `SUPABASE_SERVICE_ROLE_KEY` in `.env.local`
- Check if your Supabase project is paused — free projects pause after 7 days of inactivity; resume from the dashboard
- Confirm your RLS policies allow the `service_role` key to bypass them

**Ollama not responding**
```bash
# Check what models are loaded
docker exec ai_email_ollama ollama list

# Re-pull if the model is missing
docker exec ai_email_ollama ollama pull llama3.1:8b
```

**n8n workflow not triggering**
```bash
docker compose logs n8n
```
- Verify your Gmail OAuth token is still valid in the n8n workflow node
- Check that Redis is running: `docker compose ps redis`

**PDF stamping failing**
```bash
docker compose logs python-helper

# Verify PyMuPDF is installed
docker compose exec python-helper pip list | grep PyMuPDF
```

**Messenger queue not processing**
```bash
# Check the worker is running
docker compose exec php-fpm php bin/console messenger:stats

# Restart the worker
docker compose exec -d php-fpm php bin/console messenger:consume async -vv
```

---

## Security Checklist Before Production

- [ ] Never commit `.env.local` — it contains your Supabase service role key which bypasses all RLS
- [ ] Change the default n8n credentials from `admin` / `changeme`
- [ ] Rotate `APP_SECRET` to a strong random value (`openssl rand -hex 32`)
- [ ] Review all Supabase RLS policies and confirm each role sees only what it should
- [ ] Use `SUPABASE_ANON_KEY` only for client-side operations; `SERVICE_ROLE_KEY` only on the server side
- [ ] Set up HTTPS using Nginx + Let's Encrypt or Cloudflare Tunnel before any public exposure
- [ ] Enable 2FA on your GitHub and Supabase accounts
- [ ] Set Supabase approval token expiry to match your business needs (default: 7 days)

---

## Contributing

1. Fork the repo and create your branch from `main`

```bash
git checkout -b feat/your-feature
```

2. Make your changes, then commit using [Conventional Commits](https://www.conventionalcommits.org/)

```bash
git commit -m "feat: add bulk invoice export"
git commit -m "fix: correct OCR timeout handling"
git commit -m "docs: update Supabase setup checklist"
```

3. Push and open a Pull Request

```bash
git push origin feat/your-feature
```

**Branch naming:**
- `feat/` — new features
- `fix/` — bug fixes
- `docs/` — documentation only
- `phase/` — phase-based development (e.g. `phase/3-invoice-workflow`)

---

## License

MIT — free for personal and commercial use. See [LICENSE](LICENSE).

---

<div align="center">

Built with Symfony · Supabase · Ollama · n8n · Tesseract · Docker · Bootstrap 5

<br /><br />

<img src="https://img.shields.io/badge/100%25-free_stack-3ecf8e?style=flat-square&labelColor=1e1e2e" alt="100% free stack" />
<img src="https://img.shields.io/badge/self--hosted-no_vendor_lock--in-6366f1?style=flat-square&labelColor=1e1e2e" alt="self-hosted" />
<img src="https://img.shields.io/badge/AI-runs_locally-ff6b35?style=flat-square&labelColor=1e1e2e" alt="AI runs locally" />

</div>
