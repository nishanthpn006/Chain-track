# ChainTrack — Digital Chain-of-Custody Tracking System

ChainTrack is a secure, web-based digital chain-of-custody and evidence management application engineered for law enforcement, internal forensic units, and investigative compliance departments.

---

## 1. Project Name
**ChainTrack — Digital Chain-of-Custody Tracking System**

---

## 2. Project Purpose
The purpose of ChainTrack is to guarantee legal defensibility, strict accountability, and non-repudiation during the intake, custody transfer, laboratory analysis, storage, and auditing of physical and digital evidence.

---

## 3. Problem Statement
In conventional investigative workflows, chain-of-custody logs often rely on manual paperwork, physical sign-out ledgers, or disjointed spreadsheets. These methods suffer from:
- Vulnerability to tampering, retroactive record falsification, or loss.
- Inconsistent custodial handoffs without verifiable multi-party acknowledgment.
- Inability to establish immediate, unbroken chronological timelines for courtroom or audit proceedings.
- Lack of granular role-based access control (RBAC), allowing unauthorized viewing or handling of sensitive case material.

---

## 4. Proposed Solution
ChainTrack addresses these challenges by implementing an institutional-grade, web-based tracking architecture featuring:
- Strict two-party custodial transfer workflows (Initiation $\rightarrow$ Receipt & Verification $\rightarrow$ Dual-party Confirmation/Rejection).
- Atomic database transactions ensuring that current custody, location status, status history, and append-only custody history remain permanently synchronized.
- Fine-grained role-based access control (RBAC) across five specialized organizational roles.
- Permanent, immutable audit trails recording user logins, entity updates, and custody transitions with timestamps and IP addresses.
- Comprehensive investigative dossier generation and management reporting.

---

## 5. Main Features

- **Authentication & Session Security**:
  - Secure session handling (`HttpOnly`, `SameSite=Strict` cookies, automatic session timeout, session fixation prevention).
  - Deactivated account blocking and credential validation via PHP `password_hash()` BCRYPT.
- **Role-Based Access Control (RBAC)**:
  - Strict server-side access barriers protecting administrative, forensic, investigative, and audit views.
- **Investigation Case Management**:
  - Structured case registry linking lead investigators, departments, start/end dates, and associated controlled items.
- **Controlled Item Registry**:
  - Comprehensive metadata registration (category, acquisition date, physical description, source location, write-block seals).
  - Automated unique reference generation (e.g. `ITM-2026-0008`).
  - Automatic initial custody assignment recorded to the immutable ledger.
- **Two-Party Custody Transfers**:
  - Controlled transfer initiation with destination selection and mandatory transfer reasoning.
  - Dedicated incoming transfer queue for receiving custodians.
  - Review, acceptance, or rejection with full confirmation remark capture.
  - Atomic transfer execution with automatic item status, location, and custodian updating.
- **Item Dossier & Chronological Timeline**:
  - Unified visual dossier tracking physical state, current custodian, location badge, and complete chronological custody history.
- **Comprehensive Audit Logging**:
  - Real-time event recording (`user.login`, `item.create`, `custody.transfer_initiated`, `custody.transfer_confirmed`, etc.).
- **Analytical Reports & Print Utility**:
  - Real-time reporting on custody activity, inventory by category, investigation status summaries, and overdue/inactive items with clean print stylesheets.
- **Built-in System Diagnostics**:
  - Diagnostic CLI/Web tool (`tools/diagnostics.php`) verifying database schema health, zero orphaned foreign keys, and 100% sequential chain-of-custody continuity.

---

## 6. Technology Stack

- **Backend Logic**: PHP 8.2+ / PHP 8.5+ (Object-Oriented & Procedural Core Architecture, no third-party framework dependencies)
- **Database Engine**: MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine, Foreign Key Constraints, Transactions, Prepared Statements via PDO & MySQLi)
- **Frontend Presentation**: Semantic HTML5, Vanilla CSS3 (Institutional Design System, High-contrast neutral palette), Vanilla JavaScript (no external runtime bloat)
- **Web Server**: Built-in PHP Development Server (or Apache 2.4+ with `mod_rewrite`)

---

## 7. Project Structure

```text
chaintrack/
├── .htaccess                 Apache routing rules and security overrides
├── .gitignore                Git ignore rules protecting local config and logs
├── DEMO_GUIDE.md             Comprehensive step-by-step viva & demonstration guide
├── README.md                 Project documentation and system reference
├── index.php                 Application root entry point and initial route distributor
├── audit/                    Audit log browsing views
│   └── index.php
├── categories/               Item category management (Admin)
├── config/                   Configuration files
│   ├── app.php               App constants, dynamic BASE_PATH & BASE_URL
│   ├── database.example.php  Template configuration for database connection
│   ├── database.php          Local database connection (git-ignored)
│   └── db_mysqli.php         MySQLi connection helper
├── controllers/              Authentication checks and entry handlers
├── core/                     Core system infrastructure
│   ├── Auth.php              Authentication, session guards, and login handling
│   ├── bootstrap.php         Application bootstrapping, auto-loading, error reporting
│   └── Helpers.php           Sanitization (e/esc), URL formatting, flash messages
├── dashboard/                Role-based dynamic dashboards
├── database/                 Database schemas and seed scripts
│   ├── chaintrack_schema.sql Full relational database schema (12 tables)
│   ├── chaintrack_seed.sql   Initial demonstration dataset (10 users, cases, items)
│   └── step32_custody_history.sql Permanent custody history table definition
├── departments/              Department management (Admin)
├── includes/                 Global utility functions and legacy bridges
│   ├── auth_check.php        Procedural role verification functions
│   └── functions.php         Sanitization, validation helpers, query generators
├── investigations/           Case management (List, View, Add, Edit)
├── items/                    Controlled item management (Registry, Dossier, Status)
├── locations/                Storage facility and locker management
├── middleware/               Role authorization guards
├── models/                   Data access classes (User, Item, CustodyTransfer, etc.)
├── public/                   Static assets (CSS, JS)
│   ├── css/chaintrack.css    Institutional Design System stylesheet
│   └── js/chaintrack.js      Client-side interactions and confirmations
├── reports/                  Analytical reports and print layouts
├── tools/                    System health utilities
│   └── diagnostics.php       Automated database integrity and continuity checker
├── transfers/                Custody transfer workflows (Initiate, Incoming, Confirm)
├── users/                    User management and account activation
└── views/                    Institutional UI templates and component views
```

---

## 8. Database Setup

Ensure your local MySQL service is active on host `localhost` and port `3306`.

---

## 9. How to Import the Schema

Open PowerShell or Command Prompt and execute:

```powershell
# Create the database and import table definitions:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS chaintrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p chaintrack < database/chaintrack_schema.sql
```

*If prompted, enter your local MySQL root password.*

---

## 10. How to Import Seed Data

Populate the database with demonstration roles, departments, user accounts, sample investigations, and historical custody items:

```powershell
mysql -u root -p chaintrack < database/chaintrack_seed.sql
```

*(Optional: To run the diagnostic integrity check at any time, execute `php tools/diagnostics.php`).*

---

## 11. How to Configure the Database

Copy the tracked configuration template to create your local `config/database.php` (which is git-ignored):

```powershell
cp config/database.example.php config/database.php
```

Then edit `config/database.php` to set your local MySQL credentials:

```php
// File: config/database.php
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'chaintrack');
define('DB_USER',    'root');
define('DB_PASS',    'YOUR_LOCAL_MYSQL_PASSWORD');
define('DB_CHARSET', 'utf8mb4');
```

---

## 12. How to Start the Application

From the project root directory:

```powershell
cd D:\path\to\chaintrack
php -S localhost:8000 -t .
```

---

## 13. Application URL

Access the running system in your web browser:
**[http://localhost:8000/](http://localhost:8000/)**

*(Unauthenticated visits are automatically redirected to the secure login gateway).*

---

## 14. Demo Accounts & Credentials

> [!NOTE]
> **DEMONSTRATION / DEVELOPMENT CREDENTIALS ONLY**  
> The accounts below are intentional synthetic demonstration identities seeded for academic and demonstration evaluation. Never use these credentials in a production environment.

All default seed accounts share the standard demonstration password:  
**`Password@123`**

| Username | Full Name | Role | Department |
| :--- | :--- | :--- | :--- |
| **`admin`** | System Administrator | Administrator | System-wide Operations |
| **`reza.hartono`** | Inspector Reza Hartono | Lead Investigator | Criminal Investigation Division (CID) |
| **`deni.oviya.a`** | Deni Oviya A | Lead Investigator | Criminal Investigation Division (CID) |
| **`aisha.noor`** | Inspector Aisha Noor | Lead Investigator | Criminal Investigation Division (CID) |
| **`budi.santoso`** | Custodian Budi Santoso | Evidence Custodian | Evidence and Custody Section (ECS) |
| **`lena.kovacs`** | Custodian Lena Kovacs | Evidence Custodian | Evidence and Custody Section (ECS) |
| **`chen.wei`** | Dr. Chen Wei | Forensic Analyst | Forensic Science Laboratory (FSL) |
| **`priya.menon`** | Priya Menon | Forensic Analyst | Forensic Science Laboratory (FSL) |
| **`sven.larsen`** | Sven Larsen | Compliance Auditor | Compliance and Audit Unit (COMP) |

---

## 15. Role Descriptions & Responsibilities

1. **Administrator (`administrator`)**:
   - Total system administration and oversight.
   - Manages user accounts, activates/deactivates officers, manages departments, item categories, and storage locations.
   - Access to complete audit logs and master configurations.
2. **Lead Investigator (`investigator`)**:
   - Case officer responsible for opening investigation files, registering newly seized evidence items, and initiating custody transfers to evidence lockers, vaults, or analysts.
3. **Evidence Custodian (`custodian`)**:
   - Vault and locker supervisor.
   - Reviews incoming transfer queues, physically verifies tamper-evident packaging, and accepts or rejects custody transfers.
4. **Forensic Analyst (`analyst`)**:
   - Laboratory examiner.
   - Receives items for analysis, updates examination states, logs forensic findings, and transfers analyzed items back to central custody.
5. **Compliance Auditor (`auditor`)**:
   - Independent oversight authority.
   - Read-only access to all case records, item dossiers, chronological custody history, and full system audit logs. Generates compliance reports.

---

## 16. Known Limitations

- **Single-Threaded Development Server**: When executed with `php -S localhost:8000`, the built-in server is intended for single-user demonstration, local testing, and evaluation. In a production environment, deployment should use Apache 2.4+ (with `mod_rewrite`) or Nginx with PHP-FPM.
- **Filesystem Session Storage**: User sessions reside in the PHP local session store. Server restarts or manual session directory cleanups will require officers to sign back in.
- **Physical Barcode Scanning**: Item references (e.g. `ITM-2026-0008`) are system-generated and formatted for barcode compatibility; integration with physical hardware scanners relies on standard keyboard-wedge USB or Bluetooth input devices.
