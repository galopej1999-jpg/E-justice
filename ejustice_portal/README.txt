eJustice Portal: Online Blotter and Court Escalation System
===========================================================

Tech Stack
- PHP 8+
- MySQL (InnoDB)
- PDO with prepared statements
- Bootstrap (CDN)
- Runs in XAMPP (htdocs)
- AES-256-CBC encryption for uploaded documents

Main Features
- User registration/login (complainants online; staff created by admin/seeder)
- Roles: complainant, police_staff, mtc_staff, mtc_judge, rtc_staff, rtc_judge, system_admin, barangay_staff, punong_barangay, lupon_secretary
- Online case filing (police blotter)
- Case categories (Philippine law): CRIMINAL, CIVIL, ADMINISTRATIVE
- Case escalation: Barangay → Police Blotter → MTC → RTC
- Encrypted document upload (AES-256-CBC)
- Only authorized staff/judges can decrypt documents
- Document access audit logging
- **NEW: Barangay Justice Module** - Complete workflow from initial complaint to escalation

Barangay Module Features
------------------------
1. Record Initial Complaints - Barangay staff can record initial disputes with complainant/respondent info
2. Mediation Tracking - Track multiple mediation attempts with outcomes and observations
3. Punong Barangay Notes - Digital input of mediation summaries and recommendations
4. Settlement Forms - Generate official documents:
   - Kasunduan sa Pag-aayos (Settlement Agreement)
   - Certificate to File Action (CFA)
   - Certificate of Non-Appearance (CNA)
5. Automatic Escalation - Unresolved cases automatically escalate to Police Blotter
6. Barangay Dashboard - Real-time statistics, pending cases, and activity logs

Installation Steps
------------------
1. Copy the `ejustice_portal` folder into your XAMPP htdocs directory.
   Example: C:\xampp\htdocs\ejustice_portal

2. Create a MySQL database named `ejustice_portal` (or any name you like).

3. Import the SQL schema (in order):
   - Open phpMyAdmin
   - Select the `ejustice_portal` database
   - Go to the Import tab
   - Choose file: `sql/ejustice_portal.sql`
   - Click Go
   - Repeat import for: `sql/002_add_audit_logs.sql`
   - Repeat import for: `sql/003_add_barangay_module.sql`

4. Configure database and encryption key:
   - Open `config/config.php`
   - Set your DB host, name, user, password
   - Change `DOC_ENC_KEY` to a long random secret string

5. Seed demo users:
   - In your browser, open: http://localhost/ejustice_portal/public/seed_demo_users.php
   - It will create default demo accounts and barangay information (only once).

   Demo logins (after seeding):
   - system_admin : admin@example.com / password
   - complainant  : complainant@example.com / password
   - police       : police@example.com / password
   - mtc_staff    : mtcstaff@example.com / password
   - mtc_judge    : mtcjudge@example.com / password
   - rtc_staff    : rtcstaff@example.com / password
   - rtc_judge    : rtcjudge@example.com / password
   - barangay_staff : barangay@example.com / password
   - punong_barangay : punongbarangay@example.com / password
   - lupon_secretary : lupon@example.com / password

6. Open the site:
   - http://localhost/ejustice_portal/public/

Barangay Workflow
-----------------
1. Complainant files case online → file_case.php (selects Barangay)
2. Case automatically creates Barangay record and appears in Barangay Dashboard
3. Barangay staff processes in Mediation → barangay_mediation.php
4. If settled: Generate Kasunduan (Settlement Agreement) → barangay_settlement_form.php
5. If unresolved: Generate CFA (Certificate to File Action) → Automatic escalation to Police Blotter
6. Police handles Police Blotter, can escalate to MTC/RTC
7. Track everything via Barangay Dashboard → barangay_dashboard.php

New Audit Features
-------------------
- Document access tracking (who viewed/decrypted which document and when)
- Barangay action logging (record creation, mediation updates, escalations)
- Audit logs viewer with filtering by date, user, action, and document
- System-wide audit trail for compliance

Deployment
----------
For production deployment, use one of these options:

1. **Netlify (Frontend) + Railway (Backend)** [RECOMMENDED - See NETLIFY_SETUP_GUIDE.md]
   - Host static frontend on Netlify CDN
   - Deploy PHP backend to Railway
   - Automatic deployment on git push
   - Best for: Fast CDN + managed PHP backend

2. **Full Docker deployment** (See docker-compose.yml)
   - Deploy Dockerfile to Railway, Render, Fly, or Docker Compose
   - Includes Nginx + PHP-FPM + MySQL
   - Best for: Complete control, same-host all services

3. **VPS / Self-hosted** (Using XAMPP or Docker)
   - Deploy to your own server or cloud VPS
   - Full responsibility for infrastructure
   - Best for: Maximum control, offline deployment capability

Quick Start for Production:
   1. Read NETLIFY_SETUP_GUIDE.md (comprehensive, step-by-step)
   2. OR use NETLIFY_DEPLOYMENT_CHECKLIST.txt (quick checklist)
   3. Deploy backend to Railway/Render
   4. Deploy frontend to Netlify
   5. Update netlify.toml with your backend URL

Notes
-----
- This is a comprehensive system for Philippine local justice workflow
- Supports digital Barangay Lupon procedures (Katarungan Pambarangay)
- Bridges gap between Barangay mediation and formal court proceedings
- You can extend with SMS notifications, email reminders, and QR code verification
- Suitable for local government units (LGUs) and Barangay offices
- All documents are encrypted (AES-256-CBC) for security and compliance
