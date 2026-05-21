# Supabase Project Setup Checklist

## 1. Create Supabase Project

- [ ] Go to https://supabase.com and sign up/login
- [ ] Click "New Project"
- [ ] Choose organization (or create one)
- [ ] Enter project name: `ai-email-automation`
- [ ] Choose database password (save it securely)
- [ ] Select region closest to your location
- [ ] Select pricing plan: Free tier
- [ ] Click "Create new project"
- [ ] Wait for project to be provisioned (2-3 minutes)

## 2. Get Project Credentials

- [ ] Navigate to Project Settings > API
- [ ] Copy the following values to your `.env.local`:
  - [ ] `SUPABASE_URL` (Project URL)
  - [ ] `SUPABASE_ANON_KEY` (anon/public key)
  - [ ] `SUPABASE_SERVICE_ROLE_KEY` (service_role key - keep secret!)
  - [ ] `DATABASE_URL` (Connection string format: `postgresql://postgres:[PASSWORD]@db.[PROJECT].supabase.co:5432/postgres`)

## 3. Create Database Tables

## 3. Create Database Tables

- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE emails (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    message_id text UNIQUE NOT NULL,
    thread_id text,
    sender_email text NOT NULL,
    sender_name text,
    recipient_inbox text,
    subject text,
    body_text text,
    body_html text,
    received_at timestamptz NOT NULL,
    has_attachments boolean DEFAULT false,
    processing_status text DEFAULT 'pending' CHECK (processing_status IN ('pending','processing','done','failed')),
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

CREATE TABLE email_attachments (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    email_id uuid REFERENCES emails(id) ON DELETE CASCADE,
    filename text NOT NULL,
    original_filename text,
    supabase_storage_path text,
    mime_type text,
    file_size bigint,
    created_at timestamptz DEFAULT now()
);

CREATE TABLE ai_classifications (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    email_id uuid REFERENCES emails(id) ON DELETE CASCADE,
    category text CHECK (category IN ('vendor_invoice','refund_request','return_request','shipping_issue','order_status','product_question','customer_complaint','payment_followup','approval_response','spam','unknown')),
    confidence float,
    requires_human_review boolean DEFAULT false,
    extracted_summary text,
    raw_response text,
    model_used text DEFAULT 'llama3.1:8b',
    classified_at timestamptz DEFAULT now()
);

CREATE TABLE invoice_records (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    email_id uuid REFERENCES emails(id),
    attachment_id uuid REFERENCES email_attachments(id),
    vendor_name text,
    invoice_number text UNIQUE,
    invoice_date date,
    due_date date,
    amount numeric(12,2),
    currency text DEFAULT 'USD',
    description text,
    project_name text,
    expense_category text,
    received_date date DEFAULT CURRENT_DATE,
    approval_status text DEFAULT 'pending' CHECK (approval_status IN ('pending','approved','rejected','needs_info')),
    approver_role text,
    approver_email text,
    approved_at timestamptz,
    payment_status text DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','scheduled','paid')),
    payment_date date,
    quickbooks_csv_exported boolean DEFAULT false,
    stamped_file_storage_path text,
    original_file_storage_path text,
    duplicate_of_id uuid REFERENCES invoice_records(id),
    notes text,
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

CREATE TABLE approval_requests (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    invoice_id uuid REFERENCES invoice_records(id),
    case_id uuid,
    token text UNIQUE NOT NULL DEFAULT encode(gen_random_bytes(32), 'hex'),
    approver_email text NOT NULL,
    approver_role text,
    status text DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected','expired')),
    responded_at timestamptz,
    expires_at timestamptz DEFAULT (now() + interval '7 days'),
    created_at timestamptz DEFAULT now()
);

CREATE TABLE orders (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    order_number text UNIQUE NOT NULL,
    customer_name text,
    customer_email text,
    product_name text,
    product_sku text,
    quantity int,
    total_amount numeric(10,2),
    order_date date,
    shipping_status text,
    tracking_number text,
    carrier text,
    delivery_address text,
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

CREATE TABLE customer_cases (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    email_id uuid REFERENCES emails(id),
    order_id uuid REFERENCES orders(id),
    customer_email text NOT NULL,
    case_type text CHECK (case_type IN ('refund','return','shipping','product_question','complaint','other')),
    urgency text DEFAULT 'medium' CHECK (urgency IN ('low','medium','high','critical')),
    status text DEFAULT 'open' CHECK (status IN ('open','pending_reply','escalated','resolved','closed')),
    assigned_to text,
    draft_reply text,
    final_reply text,
    sent_at timestamptz,
    resolution_notes text,
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

CREATE TABLE case_events (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    case_id uuid REFERENCES customer_cases(id) ON DELETE CASCADE,
    event_type text NOT NULL,
    event_data jsonb,
    actor text DEFAULT 'system',
    created_at timestamptz DEFAULT now()
);

CREATE TABLE payment_schedules (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    schedule_date date NOT NULL,
    status text DEFAULT 'draft' CHECK (status IN ('draft','sent','processed')),
    total_amount numeric(12,2),
    invoice_count int,
    csv_storage_path text,
    sent_at timestamptz,
    created_at timestamptz DEFAULT now()
);

CREATE TABLE payment_schedule_items (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    schedule_id uuid REFERENCES payment_schedules(id) ON DELETE CASCADE,
    invoice_id uuid REFERENCES invoice_records(id),
    amount numeric(12,2),
    notes text
);

CREATE TABLE audit_logs (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    entity_type text,
    entity_id uuid,
    action text NOT NULL,
    actor text DEFAULT 'system',
    data jsonb,
    created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_emails_processing_status ON emails(processing_status);
CREATE INDEX idx_emails_received_at ON emails(received_at);
CREATE INDEX idx_invoice_records_approval_status ON invoice_records(approval_status);
CREATE INDEX idx_invoice_records_payment_status ON invoice_records(payment_status);
CREATE INDEX idx_customer_cases_status ON customer_cases(status);
CREATE INDEX idx_customer_cases_urgency ON customer_cases(urgency);
```

## 4. Enable Realtime

- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
ALTER PUBLICATION supabase_realtime ADD TABLE emails, invoice_records, customer_cases;
```

## 5. Configure Row Level Security (RLS)

- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
ALTER TABLE emails ENABLE ROW LEVEL SECURITY;
ALTER TABLE email_attachments ENABLE ROW LEVEL SECURITY;
ALTER TABLE ai_classifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE approval_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE case_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_schedules ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_schedule_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id uuid REFERENCES auth.users(id) PRIMARY KEY,
    role text NOT NULL,
    created_at timestamptz DEFAULT now()
);

CREATE OR REPLACE FUNCTION has_role(role_name text)
RETURNS boolean AS $$
    SELECT EXISTS (
        SELECT 1
        FROM user_roles
        WHERE user_id = auth.uid()
          AND role = role_name
    );
$$ LANGUAGE sql STABLE;

CREATE POLICY "admin_all_emails" ON emails
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_email_attachments" ON email_attachments
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_ai_classifications" ON ai_classifications
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_invoice_records" ON invoice_records
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_approval_requests" ON approval_requests
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_orders" ON orders
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_customer_cases" ON customer_cases
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_case_events" ON case_events
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_payment_schedules" ON payment_schedules
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_payment_schedule_items" ON payment_schedule_items
    FOR ALL USING (has_role('ROLE_ADMIN'));
CREATE POLICY "admin_all_audit_logs" ON audit_logs
    FOR ALL USING (has_role('ROLE_ADMIN'));

CREATE POLICY "approver_select_invoice_records" ON invoice_records
    FOR SELECT USING (has_role('ROLE_APPROVER'));
CREATE POLICY "approver_update_invoice_records" ON invoice_records
    FOR UPDATE USING (has_role('ROLE_APPROVER'));
CREATE POLICY "approver_select_approval_requests" ON approval_requests
    FOR SELECT USING (has_role('ROLE_APPROVER'));
CREATE POLICY "approver_update_approval_requests" ON approval_requests
    FOR UPDATE USING (has_role('ROLE_APPROVER'));

CREATE POLICY "finance_select_invoice_records" ON invoice_records
    FOR SELECT USING (has_role('ROLE_FINANCE'));
CREATE POLICY "finance_update_invoice_records" ON invoice_records
    FOR UPDATE USING (has_role('ROLE_FINANCE'));
CREATE POLICY "finance_select_payment_schedules" ON payment_schedules
    FOR SELECT USING (has_role('ROLE_FINANCE'));
CREATE POLICY "finance_update_payment_schedules" ON payment_schedules
    FOR UPDATE USING (has_role('ROLE_FINANCE'));
CREATE POLICY "finance_select_payment_schedule_items" ON payment_schedule_items
    FOR SELECT USING (has_role('ROLE_FINANCE'));
CREATE POLICY "finance_update_payment_schedule_items" ON payment_schedule_items
    FOR UPDATE USING (has_role('ROLE_FINANCE'));

CREATE POLICY "support_select_customer_cases" ON customer_cases
    FOR SELECT USING (has_role('ROLE_SUPPORT'));
CREATE POLICY "support_update_customer_cases" ON customer_cases
    FOR UPDATE USING (has_role('ROLE_SUPPORT'));
CREATE POLICY "support_select_case_events" ON case_events
    FOR SELECT USING (has_role('ROLE_SUPPORT'));
CREATE POLICY "support_update_case_events" ON case_events
    FOR UPDATE USING (has_role('ROLE_SUPPORT'));
CREATE POLICY "support_select_orders" ON orders
    FOR SELECT USING (has_role('ROLE_SUPPORT'));
CREATE POLICY "support_update_orders" ON orders
    FOR UPDATE USING (has_role('ROLE_SUPPORT'));
```

## 6. Create updated_at Trigger

- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_emails_updated_at BEFORE UPDATE ON emails
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_invoice_records_updated_at BEFORE UPDATE ON invoice_records
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_customer_cases_updated_at BEFORE UPDATE ON customer_cases
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

## 7. Create Storage Bucket

- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
INSERT INTO storage.buckets (id, name, public)
VALUES ('invoices', 'invoices', false)
ON CONFLICT (id) DO NOTHING;
```

## 8. Notes

- The service_role key bypasses RLS. Use it only in backend Symfony services.
- If you want users to have roles, insert them into `user_roles`.
- [ ] Verify you can query the database via SQL Editor
