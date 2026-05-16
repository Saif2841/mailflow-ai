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

### emails table
- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
CREATE TABLE emails (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    gmail_message_id VARCHAR(255) UNIQUE NOT NULL,
    subject TEXT NOT NULL,
    sender VARCHAR(255) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    received_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    processed_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) DEFAULT 'pending',
    has_attachment BOOLEAN DEFAULT FALSE,
    attachment_url TEXT,
    ocr_text TEXT,
    ai_summary TEXT,
    ai_category VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_emails_status ON emails(status);
CREATE INDEX idx_emails_received_at ON emails(received_at);
CREATE INDEX idx_emails_gmail_message_id ON emails(gmail_message_id);
```

### invoices table
- [ ] Run this SQL:

```sql
CREATE TABLE invoices (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    email_id UUID REFERENCES emails(id) ON DELETE CASCADE,
    invoice_number VARCHAR(255),
    vendor VARCHAR(255),
    amount DECIMAL(10, 2),
    currency VARCHAR(3) DEFAULT 'USD',
    invoice_date DATE,
    due_date DATE,
    status VARCHAR(50) DEFAULT 'pending',
    storage_path TEXT,
    stamped_path TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_invoices_email_id ON invoices(email_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_invoice_number ON invoices(invoice_number);
```

### approvals table
- [ ] Run this SQL:

```sql
CREATE TABLE approvals (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    invoice_id UUID REFERENCES invoices(id) ON DELETE CASCADE,
    approver_id UUID REFERENCES auth.users(id),
    status VARCHAR(50) DEFAULT 'pending',
    comments TEXT,
    approved_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_approvals_invoice_id ON approvals(invoice_id);
CREATE INDEX idx_approvals_approver_id ON approvals(approver_id);
CREATE INDEX idx_approvals_status ON approvals(status);
```

### audit_log table
- [ ] Run this SQL:

```sql
CREATE TABLE audit_log (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id UUID NOT NULL,
    action VARCHAR(50) NOT NULL,
    user_id UUID REFERENCES auth.users(id),
    changes JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_audit_log_entity ON audit_log(entity_type, entity_id);
CREATE INDEX idx_audit_log_user_id ON audit_log(user_id);
```

## 4. Create Storage Bucket

- [ ] Go to Storage > Create a new bucket
- [ ] Name: `invoices`
- [ ] Public bucket: No (keep private)
- [ ] File size limit: 50MB (for PDFs)
- [ ] Allowed MIME types: `application/pdf`
- [ ] Click "Create bucket"

## 5. Configure Row Level Security (RLS)

### Enable RLS on all tables
- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
ALTER TABLE emails ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoices ENABLE ROW LEVEL SECURITY;
ALTER TABLE approvals ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;
```

### Create RLS Policies for emails table
- [ ] Run this SQL:

```sql
-- Allow authenticated users to read all emails
CREATE POLICY "Allow authenticated read emails" 
ON emails FOR SELECT 
TO authenticated 
USING (true);

-- Allow service role to insert emails
CREATE POLICY "Allow service role insert emails" 
ON emails FOR INSERT 
TO service_role 
WITH CHECK (true);

-- Allow service role to update emails
CREATE POLICY "Allow service role update emails" 
ON emails FOR UPDATE 
TO service_role 
USING (true);

-- Allow service role to delete emails
CREATE POLICY "Allow service role delete emails" 
ON emails FOR DELETE 
TO service_role 
USING (true);
```

### Create RLS Policies for invoices table
- [ ] Run this SQL:

```sql
-- Allow authenticated users to read all invoices
CREATE POLICY "Allow authenticated read invoices" 
ON invoices FOR SELECT 
TO authenticated 
USING (true);

-- Allow service role to insert invoices
CREATE POLICY "Allow service role insert invoices" 
ON invoices FOR INSERT 
TO service_role 
WITH CHECK (true);

-- Allow service role to update invoices
CREATE POLICY "Allow service role update invoices" 
ON invoices FOR UPDATE 
TO service_role 
USING (true);

-- Allow service role to delete invoices
CREATE POLICY "Allow service role delete invoices" 
ON invoices FOR DELETE 
TO service_role 
USING (true);
```

### Create RLS Policies for approvals table
- [ ] Run this SQL:

```sql
-- Allow authenticated users to read approvals
CREATE POLICY "Allow authenticated read approvals" 
ON approvals FOR SELECT 
TO authenticated 
USING (true);

-- Allow authenticated users to insert approvals
CREATE POLICY "Allow authenticated insert approvals" 
ON approvals FOR INSERT 
TO authenticated 
WITH CHECK (true);

-- Allow authenticated users to update their own approvals
CREATE POLICY "Allow authenticated update own approvals" 
ON approvals FOR UPDATE 
TO authenticated 
USING (auth.uid() = approver_id);

-- Allow service role full access
CREATE POLICY "Allow service role full access approvals" 
ON approvals FOR ALL 
TO service_role 
USING (true);
```

### Create RLS Policies for audit_log table
- [ ] Run this SQL:

```sql
-- Allow service role full access to audit_log
CREATE POLICY "Allow service role full access audit_log" 
ON audit_log FOR ALL 
TO service_role 
USING (true);
```

## 6. Configure Storage Policies

- [ ] Go to Storage > invoices > Policies
- [ ] Create policy for authenticated users to read files:

```sql
CREATE POLICY "Allow authenticated read invoices" 
ON storage.objects FOR SELECT 
TO authenticated 
USING (bucket_id = 'invoices');
```

- [ ] Create policy for service role to upload files:

```sql
CREATE POLICY "Allow service role upload invoices" 
ON storage.objects FOR INSERT 
TO service_role 
WITH CHECK (bucket_id = 'invoices');
```

- [ ] Create policy for service role to delete files:

```sql
CREATE POLICY "Allow service role delete invoices" 
ON storage.objects FOR DELETE 
TO service_role 
USING (bucket_id = 'invoices');
```

## 7. Set up Authentication

- [ ] Go to Authentication > Providers
- [ ] Enable Email provider (default enabled)
- [ ] Configure email templates if needed
- [ ] Set up custom SMTP for production (optional)

### Create User Roles
- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL to create roles table:

```sql
CREATE TABLE IF NOT EXISTS user_roles (
    user_id UUID REFERENCES auth.users(id) PRIMARY KEY,
    role VARCHAR(50) NOT NULL, -- 'admin', 'approver', 'finance', 'support'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Insert admin user (replace with actual user ID from auth.users)
INSERT INTO user_roles (user_id, role) 
VALUES ('YOUR_USER_ID_HERE', 'admin');
```

## 8. Enable Realtime

- [ ] Go to Database > Replication
- [ ] Enable Realtime for tables:
  - [ ] emails
  - [ ] invoices
  - [ ] approvals
- [ ] Go to SQL Editor > New Query
- [ ] Run this SQL:

```sql
ALTER PUBLICATION supabase_realtime ADD TABLE emails;
ALTER PUBLICATION supabase_realtime ADD TABLE invoices;
ALTER PUBLICATION supabase_realtime ADD TABLE approvals;
```

## 9. Create Database Functions

### Updated timestamp trigger
- [ ] Run this SQL:

```sql
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply to all tables
CREATE TRIGGER update_emails_updated_at BEFORE UPDATE ON emails
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_invoices_updated_at BEFORE UPDATE ON invoices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_approvals_updated_at BEFORE UPDATE ON approvals
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

## 10. Test Connection

- [ ] Update your `.env.local` with actual Supabase credentials
- [ ] Run Symfony command to test connection:
```bash
php bin/console doctrine:database:check-connection
```
- [ ] Verify you can query the database via SQL Editor
- [ ] Test file upload to Storage bucket

## 11. Configure n8n to Connect to Supabase

- [ ] In n8n, add HTTP Request node
- [ ] Use Supabase REST API: `https://yourproject.supabase.co/rest/v1/`
- [ ] Add header: `apikey: YOUR_ANON_KEY`
- [ ] Add header: `Authorization: Bearer YOUR_ANON_KEY`
- [ ] Add header: `Content-Type: application/json`
- [ ] Test with a simple GET request to `/emails`

## 12. Backup Configuration

- [ ] Go to Database > Backups
- [ ] Enable automated backups (Free tier: 7 days retention)
- [ ] Note: Manual backups available in Pro tier

## 13. Security Checklist

- [ ] Never commit `.env.local` to version control
- [ ] Keep `SUPABASE_SERVICE_ROLE_KEY` secret (bypasses RLS)
- [ ] Use `SUPABASE_ANON_KEY` for client-side operations
- [ ] Review RLS policies before production
- [ ] Enable additional authentication providers if needed
- [ ] Set up IP restrictions in Supabase dashboard (optional)

## 14. Performance Optimization

- [ ] Add indexes to frequently queried columns
- [ ] Enable connection pooling (Pro tier)
- [ ] Monitor database size in dashboard
- [ ] Set up alerts for quota limits (Free tier: 500MB DB, 1GB Storage)

## 15. Documentation

- [ ] Save this checklist for reference
- [ ] Document any custom SQL queries
- [ ] Keep track of schema changes
- [ ] Update `.env.local` template with any new variables
