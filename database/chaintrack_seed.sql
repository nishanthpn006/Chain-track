-- =============================================================================
-- ChainTrack -- Seed Data
-- Run AFTER chaintrack_schema.sql
-- All passwords are:  Password@123  (hashed with PHP password_hash BCRYPT)
-- =============================================================================

USE `chaintrack`;
SET foreign_key_checks = 0;

-- =============================================================================
-- ROLES  (5 roles, slugs match PHP auth checks exactly)
-- =============================================================================
INSERT INTO `roles` (`id`, `role_name`, `role_slug`, `description`) VALUES
(1, 'Administrator',        'administrator', 'Full system access including user and master-data management'),
(2, 'Investigator',         'investigator',  'Handles investigation items; can initiate transfers'),
(3, 'Custodian',            'custodian',     'Receives, stores and transfers controlled items'),
(4, 'Analyst / Examiner',   'analyst',       'Examines items; updates examination status'),
(5, 'Auditor / Viewer',     'auditor',       'Read-only access; search, view history and reports');

-- =============================================================================
-- DEPARTMENTS
-- =============================================================================
INSERT INTO `departments` (`id`, `dept_code`, `dept_name`, `description`, `is_active`) VALUES
(1, 'CID',  'Criminal Investigation Division',    'Main criminal investigation unit',           1),
(2, 'FSL',  'Forensic Science Laboratory',        'Forensic analysis and examination unit',     1),
(3, 'ECS',  'Evidence and Custody Section',       'Central evidence storage and management',    1),
(4, 'NCS',  'Narcotics Control Section',          'Controlled substances investigation unit',   1),
(5, 'COMP', 'Compliance and Audit Unit',          'Internal audit and compliance oversight',    1);

-- =============================================================================
-- USERS  (10 users across all roles)
-- Password for ALL accounts: Password@123
-- Hash generated with: password_hash('Password@123', PASSWORD_BCRYPT)
-- =============================================================================
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role_id`, `department_id`, `is_active`, `created_by`) VALUES
(1,  'EMP-001', 'System Administrator',   'admin@chaintrack.local',     'admin',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, 1, NULL),
(2,  'EMP-002', 'Inspector Reza Hartono', 'reza.hartono@chaintrack.local', 'reza.hartono', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1,    1, 1),
(3,  'EMP-003', 'Inspector Aisha Noor',  'aisha.noor@chaintrack.local',  'aisha.noor',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1,    1, 1),
(4,  'EMP-004', 'Inspector Tariq Yusuf', 'tariq.yusuf@chaintrack.local', 'tariq.yusuf',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 4,    1, 1),
(5,  'EMP-005', 'Custodian Budi Santoso','budi.santoso@chaintrack.local','budi.santoso', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 3,    1, 1),
(6,  'EMP-006', 'Custodian Lena Kovacs', 'lena.kovacs@chaintrack.local', 'lena.kovacs',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 3,    1, 1),
(7,  'EMP-007', 'Analyst Dr. Chen Wei',  'chen.wei@chaintrack.local',    'chen.wei',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 2,    1, 1),
(8,  'EMP-008', 'Analyst Priya Menon',   'priya.menon@chaintrack.local', 'priya.menon',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 2,    1, 1),
(9,  'EMP-009', 'Auditor Sven Larsen',   'sven.larsen@chaintrack.local', 'sven.larsen',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 5,    1, 1),
(10, 'EMP-010', 'Inspector Maya Cruz',   'maya.cruz@chaintrack.local',   'maya.cruz',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1,    1, 1),
(13, 'EMP-012', 'Inspector Deni Oviya A',    'deni.oviya.a@chaintrack.local','deni.oviya.a', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1,    1, 1);

-- =============================================================================
-- ITEM CATEGORIES
-- =============================================================================
INSERT INTO `item_categories` (`id`, `cat_name`, `description`, `is_active`) VALUES
(1, 'Physical Evidence',     'Tangible physical objects collected from a scene',          1),
(2, 'Digital Storage Device','Hard drives, USB drives, memory cards, phones, computers', 1),
(3, 'Controlled Substance',  'Narcotics, chemicals, or regulated substances',             1),
(4, 'Document / Record',     'Printed or handwritten documents, contracts, records',      1),
(5, 'Weapon',                'Firearms, bladed weapons, or other weapons',                1),
(6, 'Currency / Valuables',  'Cash, jewelry, or other high-value items',                 1),
(7, 'Biological Sample',     'Blood, DNA, tissue, or other biological specimens',         1),
(8, 'Seized Asset',          'Vehicles, equipment, or other seized property',             1);

-- =============================================================================
-- ITEM STATUSES  (ordered by typical lifecycle progression)
-- =============================================================================
INSERT INTO `item_statuses` (`id`, `status_name`, `status_slug`, `color_badge`, `description`, `is_active`, `sort_order`) VALUES
(1,  'Registered',            'registered',          'info',      'Item has been registered in the system',            1,  1),
(2,  'In Storage',            'in_storage',          'success',   'Item is securely stored at a custody location',     1,  2),
(3,  'Pending Transfer',      'pending_transfer',    'warning',   'A custody transfer has been initiated',             1,  3),
(4,  'In Transit',            'in_transit',          'warning',   'Item is in transit between custodians',             1,  4),
(5,  'Received',              'received',            'success',   'Transfer has been confirmed by the receiver',       1,  5),
(6,  'Under Examination',     'under_examination',   'primary',   'Item is being examined or processed by an analyst', 1,  6),
(7,  'Examination Complete',  'examination_complete','info',      'Examination has been completed',                    1,  7),
(8,  'Returned to Storage',   'returned_storage',    'success',   'Item has been returned to a storage location',      1,  8),
(9,  'Archived',              'archived',            'secondary', 'Item record has been archived; no further action',  1,  9),
(10, 'Closed / Disposed',     'closed',              'dark',      'Case closed; item disposed of per protocol',        1, 10);

-- =============================================================================
-- LOCATIONS  (parent_id demonstrates hierarchy)
-- =============================================================================
INSERT INTO `locations` (`id`, `location_code`, `location_name`, `location_type`, `parent_id`, `department_id`, `description`, `is_active`) VALUES
-- Top-level locations
(1, 'BLDG-A',   'Headquarters Building A',         'office',   NULL, NULL, 'Main investigative headquarters',            1),
(2, 'BLDG-B',   'Forensic Sciences Building B',    'lab',      NULL, 2,    'Forensic science laboratory complex',        1),
(3, 'BLDG-C',   'Evidence Storage Facility',       'storage',  NULL, 3,    'Central controlled evidence storage',        1),
-- Sub-locations under Building A
(4, 'A-CID',    'CID Operations Room',             'office',   1,    1,    'Criminal Investigation Division offices',    1),
(5, 'A-NCS',    'Narcotics Section Office',        'office',   1,    4,    'Narcotics Control Section offices',          1),
-- Sub-locations under Building B
(6, 'B-LAB-1',  'Forensic Analysis Lab 1',         'lab',      2,    2,    'Primary forensic analysis laboratory',       1),
(7, 'B-LAB-2',  'Digital Forensics Lab',           'lab',      2,    2,    'Digital device examination laboratory',      1),
(8, 'B-LAB-3',  'Chemistry & Substance Lab',       'lab',      2,    2,    'Controlled substance analysis lab',          1),
-- Sub-locations under Evidence Storage Facility
(9, 'C-VAULT-1','Vault 1 -- General Evidence',     'storage',  3,    3,    'General physical evidence secure vault',     1),
(10,'C-VAULT-2','Vault 2 -- Digital & Electronics','storage',  3,    3,    'Secure storage for digital evidence',        1),
(11,'C-VAULT-3','Vault 3 -- Controlled Substances','storage',  3,    3,    'Controlled substance secure locker',         1),
(12,'C-VAULT-4','Vault 4 -- Documents & Records',  'storage',  3,    3,    'Document and records secure storage',        1),
-- External
(13,'EXT-COURT','Court Evidence Room',             'external', NULL, NULL, 'Court-managed evidence holding',             1);

-- =============================================================================
-- INVESTIGATIONS  (3 open investigations)
-- =============================================================================
INSERT INTO `investigations` (`id`, `inv_reference`, `title`, `description`, `department_id`, `lead_user_id`, `start_date`, `end_date`, `status`, `created_by`) VALUES
(1, 'INV-2026-001', 'Operation Nightfall',
   'Large-scale financial fraud investigation involving multiple suspects and digital evidence.',
   1, 2, '2026-01-15', NULL, 'open', 1),
(2, 'INV-2026-002', 'Operation Clean Sweep',
   'Narcotics trafficking network investigation -- multiple seizures across three districts.',
   4, 4, '2026-03-10', NULL, 'open', 1),
(3, 'INV-2026-003', 'Operation Sigma',
   'Cybercrime investigation involving compromised digital storage devices and fraudulent documents.',
   1, 3, '2026-06-01', NULL, 'open', 1);

-- =============================================================================
-- ITEMS  (12 items across 3 investigations -- varied statuses)
-- =============================================================================
INSERT INTO `items` (`id`, `item_reference`, `investigation_id`, `category_id`, `item_name`, `description`, `physical_description`, `acquisition_date`, `acquisition_location`, `registered_by`, `current_custodian_id`, `current_location_id`, `current_status_id`, `notes`, `is_archived`) VALUES

-- Investigation 1: Operation Nightfall
(1, 'EVD-2026-00001', 1, 2, 'Suspect Laptop Computer',
   'Black laptop seized from primary suspect residence. Suspected to contain financial records and communications.',
   'Dell Latitude 7420, Black, S/N: DL7420-2024-XK9912',
   '2026-01-20', 'No. 12 Jalan Mawar, Block C, Apartment 4B',
   2, 7, 7, 6, 'Sent for digital forensics examination.', 0),

(2, 'EVD-2026-00002', 1, 4, 'Financial Transaction Records Bundle',
   'Bundle of printed bank transaction records and wire transfer confirmations.',
   'A4 documents, approx 340 pages, bound with rubber band, in sealed plastic bag',
   '2026-01-20', 'No. 12 Jalan Mawar, Block C, Apartment 4B',
   2, 5, 12, 2, NULL, 0),

(3, 'EVD-2026-00003', 1, 6, 'Cash Bundle -- USD',
   'USD 45,000 seized from suspect safety deposit box.',
   'USD banknotes, mixed denominations, rubber-banded in bundles, placed in tamper-evident bag',
   '2026-02-01', 'National Bank Branch 7, Vault Access No. VT-2026-003',
   2, 5, 9, 2, 'Counted and verified by two officers. Tamper-evident bag sealed.', 0),

(4, 'EVD-2026-00004', 1, 2, 'Mobile Phone -- Samsung',
   'Samsung smartphone belonging to secondary suspect.',
   'Samsung Galaxy S23, Blue, IMEI: 357812094563210',
   '2026-02-15', 'Suspect vehicle search -- Plate WQ 4821 B',
   2, 5, 10, 2, NULL, 0),

-- Investigation 2: Operation Clean Sweep
(5, 'EVD-2026-00005', 2, 3, 'Controlled Substance Sample A',
   'White crystalline powder seized from warehouse raid. Suspected methamphetamine.',
   'Approx 2.3 kg net weight, vacuum-sealed bags, inside red duffel bag',
   '2026-03-15', 'Warehouse Block 7, Industrial Area Selatan',
   4, 8, 6, 6, 'Sample submitted for chemical analysis. FSL reference FSL-2026-0098.', 0),

(6, 'EVD-2026-00006', 2, 3, 'Controlled Substance Sample B',
   'Brown resin blocks. Suspected cannabis resin.',
   'Approx 800g, brown resin blocks, wrapped in newspaper and clingfilm',
   '2026-03-15', 'Warehouse Block 7, Industrial Area Selatan',
   4, 5, 11, 2, NULL, 0),

(7, 'EVD-2026-00007', 2, 5, 'Firearm -- Semi-automatic Pistol',
   'Semi-automatic pistol with magazine. No registration found.',
   'Black Glock 19, S/N: partially filed off, 15-round magazine inserted, 3 spare magazines',
   '2026-03-15', 'Warehouse Block 7, Industrial Area Selatan',
   4, 5, 9, 2, 'Ballistics examination pending.', 0),

(8, 'EVD-2026-00008', 2, 8, 'Seized Vehicle -- Toyota Hilux',
   'Toyota Hilux pickup truck used in drug transport operation.',
   'White Toyota Hilux 2021, Plate: JK 9912 C, VIN: JTFB22J60M0123456',
   '2026-03-16', 'Impound yard -- South District Station',
   4, 6, 13, 3, 'Vehicle pending court order for forfeiture. Transfer to court evidence pending.', 0),

-- Investigation 3: Operation Sigma
(9, 'EVD-2026-00009', 3, 2, 'Compromised USB Drives (Lot)',
   'Five USB flash drives found at cybercrime suspect workspace.',
   '5x SanDisk 32GB USB drives, labelled A to E with suspect handwriting, placed in zip-lock bag',
   '2026-06-05', 'Suspect office -- 3rd floor, Tower Sigma, Jalan IT Park',
   3, 7, 7, 6, 'Under digital forensics examination.', 0),

(10,'EVD-2026-00010', 3, 4, 'Forged Identity Documents',
   'Set of forged national identity cards and travel documents.',
   'Approx 12 identity cards and 2 passports -- apparent forgeries',
   '2026-06-05', 'Suspect office -- 3rd floor, Tower Sigma, Jalan IT Park',
   3, 5, 12, 2, NULL, 0),

(11,'EVD-2026-00011', 3, 2, 'Network Router -- Modified',
   'Modified network router suspected to have been used for routing illegal traffic.',
   'Cisco ASR-1001, S/N: FXS2012Q7BN, modified external antenna visible',
   '2026-06-05', 'Server room -- 3rd floor, Tower Sigma',
   3, 7, 7, 7, 'Examination complete. Awaiting return to storage.', 0),

(12,'EVD-2026-00012', 3, 7, 'Biological Sample -- Blood',
   'Blood sample collected from scene as potential DNA evidence.',
   'Two 5ml tubes, labelled SS-OPS-001, in sealed biohazard container',
   '2026-06-10', 'Scene -- parking level B2, Tower Sigma',
   3, 8, 6, 6, 'DNA analysis in progress.', 0);

-- =============================================================================
-- CUSTODY TRANSFERS  (realistic lifecycle for all 12 items)
-- Covers: initial registration, multiple transfers, confirmations, pending states
-- =============================================================================
INSERT INTO `custody_transfers`
  (`id`,`transfer_reference`,`item_id`,`from_user_id`,`to_user_id`,`from_location_id`,`to_location_id`,`transfer_status`,`reason`,`initiated_by`,`initiated_at`,`confirmed_by`,`confirmed_at`,`rejection_reason`,`notes`)
VALUES

-- ---- Item 1: Suspect Laptop (current: Chen Wei, Digital Forensics Lab) ----------
-- Step 1: Registration -> Custodian Budi
(1,'TRF-2026-00001',1, NULL, 5, NULL, 9, 'confirmed',
 'Initial custody assignment on item registration.',
 2, '2026-01-20 10:15:00', 5, '2026-01-20 10:30:00', NULL, NULL),
-- Step 2: Storage -> Digital Forensics Lab (Chen Wei)
(2,'TRF-2026-00002',1, 5, 7, 9, 7, 'confirmed',
 'Transfer to Digital Forensics Lab for examination of device contents.',
 5, '2026-01-25 09:00:00', 7, '2026-01-25 09:45:00', NULL, NULL),

-- ---- Item 2: Financial Records (current: Budi, Vault 4 Documents) ---------------
-- Step 1: Registration -> Custodian Budi
(3,'TRF-2026-00003',2, NULL, 5, NULL, 12, 'confirmed',
 'Initial custody assignment on item registration.',
 2, '2026-01-20 10:20:00', 5, '2026-01-20 10:35:00', NULL, NULL),

-- ---- Item 3: Cash Bundle (current: Budi, Vault 1) --------------------------------
(4,'TRF-2026-00004',3, NULL, 5, NULL, 9, 'confirmed',
 'Initial custody assignment on item registration.',
 2, '2026-02-01 14:00:00', 5, '2026-02-01 14:20:00', NULL, NULL),

-- ---- Item 4: Samsung Phone (current: Budi, Vault 2) -----------------------------
(5,'TRF-2026-00005',4, NULL, 5, NULL, 10, 'confirmed',
 'Initial custody assignment on item registration.',
 2, '2026-02-15 11:00:00', 5, '2026-02-15 11:15:00', NULL, NULL),

-- ---- Item 5: Substance Sample A (current: Priya, Lab 1) --------------------------
-- Step 1: Registration -> Custodian Budi
(6,'TRF-2026-00006',5, NULL, 5, NULL, 11, 'confirmed',
 'Initial custody assignment on item registration.',
 4, '2026-03-15 16:30:00', 5, '2026-03-15 16:45:00', NULL, NULL),
-- Step 2: Vault 3 -> Chemistry Lab (Priya Menon)
(7,'TRF-2026-00007',5, 5, 8, 11, 6, 'confirmed',
 'Transfer to Chemistry Lab for controlled substance analysis. FSL-2026-0098.',
 5, '2026-03-18 08:30:00', 8, '2026-03-18 09:00:00', NULL, 'Biohazard transport bag used.'),

-- ---- Item 6: Substance Sample B (current: Budi, Vault 3) ------------------------
(8,'TRF-2026-00008',6, NULL, 5, NULL, 11, 'confirmed',
 'Initial custody assignment on item registration.',
 4, '2026-03-15 16:35:00', 5, '2026-03-15 16:50:00', NULL, NULL),

-- ---- Item 7: Firearm (current: Budi, Vault 1) ------------------------------------
(9,'TRF-2026-00009',7, NULL, 5, NULL, 9, 'confirmed',
 'Initial custody assignment on item registration.',
 4, '2026-03-15 17:00:00', 5, '2026-03-15 17:15:00', NULL, 'Firearm confirmed unloaded and made safe before storage.'),

-- ---- Item 8: Seized Vehicle (current: Lena, Court Evidence -- PENDING) ----------
-- Step 1: Registration -> Custodian Lena
(10,'TRF-2026-00010',8, NULL, 6, NULL, 13, 'confirmed',
 'Initial custody assignment. Vehicle impounded at South District Station.',
 4, '2026-03-16 10:00:00', 6, '2026-03-16 10:30:00', NULL, NULL),
-- Step 2: Lena initiates transfer to Court Evidence Room -- PENDING
(11,'TRF-2026-00011',8, 6, 5, 13, 13, 'pending',
 'Transfer of vehicle evidence to Court Evidence Room pending court order confirmation.',
 6, '2026-04-01 09:00:00', NULL, NULL, NULL, 'Awaiting court order number before physical handover.'),

-- ---- Item 9: USB Drives (current: Chen Wei, Digital Forensics Lab) ---------------
(12,'TRF-2026-00012',9, NULL, 5, NULL, 10, 'confirmed',
 'Initial custody assignment on item registration.',
 3, '2026-06-05 14:00:00', 5, '2026-06-05 14:15:00', NULL, NULL),
(13,'TRF-2026-00013',9, 5, 7, 10, 7, 'confirmed',
 'Transfer to Digital Forensics Lab for forensic image acquisition and analysis.',
 5, '2026-06-08 08:45:00', 7, '2026-06-08 09:30:00', NULL, NULL),

-- ---- Item 10: Forged Documents (current: Budi, Vault 4) -------------------------
(14,'TRF-2026-00014',10, NULL, 5, NULL, 12, 'confirmed',
 'Initial custody assignment on item registration.',
 3, '2026-06-05 14:05:00', 5, '2026-06-05 14:20:00', NULL, NULL),

-- ---- Item 11: Modified Router (current: Chen Wei, Vault 2 -- return pending) ----
(15,'TRF-2026-00015',11, NULL, 5, NULL, 10, 'confirmed',
 'Initial custody assignment on item registration.',
 3, '2026-06-05 14:10:00', 5, '2026-06-05 14:25:00', NULL, NULL),
(16,'TRF-2026-00016',11, 5, 7, 10, 7, 'confirmed',
 'Transfer to Digital Forensics Lab for network traffic analysis.',
 5, '2026-06-09 08:00:00', 7, '2026-06-09 08:30:00', NULL, NULL),
-- Examination complete; Chen Wei initiates return to Vault 2
(17,'TRF-2026-00017',11, 7, 5, 7, 10, 'initiated',
 'Examination complete. Returning to secure storage Vault 2.',
 7, '2026-07-15 15:00:00', NULL, NULL, NULL, NULL),

-- ---- Item 12: Blood Sample (current: Priya, Lab 1) ------------------------------
(18,'TRF-2026-00018',12, NULL, 5, NULL, 6, 'confirmed',
 'Initial custody assignment on item registration.',
 3, '2026-06-10 11:00:00', 5, '2026-06-10 11:20:00', NULL, 'Biohazard transport conditions maintained.'),
(19,'TRF-2026-00019',12, 5, 8, 6, 6, 'confirmed',
 'Transfer to Priya Menon for DNA analysis.',
 5, '2026-06-12 09:00:00', 8, '2026-06-12 09:30:00', NULL, NULL);

-- =============================================================================
-- ITEM STATUS HISTORY  (mirrors every transfer event above)
-- =============================================================================
INSERT INTO `item_status_history`
  (`id`,`item_id`,`previous_status_id`,`new_status_id`,`changed_by`,`related_transfer_id`,`reason`,`changed_at`)
VALUES
-- Item 1
(1,  1, NULL, 1, 2, 1,  'Item registered in the system.',                               '2026-01-20 10:15:00'),
(2,  1, 1,    2, 5, 1,  'Item received into secure storage.',                           '2026-01-20 10:30:00'),
(3,  1, 2,    3, 5, 2,  'Transfer initiated to Digital Forensics Lab.',                 '2026-01-25 09:00:00'),
(4,  1, 3,    6, 7, 2,  'Received by analyst. Examination commenced.',                  '2026-01-25 09:45:00'),
-- Item 2
(5,  2, NULL, 1, 2, 3,  'Item registered in the system.',                               '2026-01-20 10:20:00'),
(6,  2, 1,    2, 5, 3,  'Item received into document storage.',                         '2026-01-20 10:35:00'),
-- Item 3
(7,  3, NULL, 1, 2, 4,  'Item registered in the system.',                               '2026-02-01 14:00:00'),
(8,  3, 1,    2, 5, 4,  'Cash received and stored in Vault 1.',                         '2026-02-01 14:20:00'),
-- Item 4
(9,  4, NULL, 1, 2, 5,  'Item registered in the system.',                               '2026-02-15 11:00:00'),
(10, 4, 1,    2, 5, 5,  'Device received into Vault 2 digital storage.',                '2026-02-15 11:15:00'),
-- Item 5
(11, 5, NULL, 1, 4, 6,  'Item registered in the system.',                               '2026-03-15 16:30:00'),
(12, 5, 1,    2, 5, 6,  'Substance sample received into controlled substance vault.',   '2026-03-15 16:45:00'),
(13, 5, 2,    3, 5, 7,  'Transfer initiated to chemistry lab.',                         '2026-03-18 08:30:00'),
(14, 5, 3,    6, 8, 7,  'Sample received by analyst. Analysis commenced.',              '2026-03-18 09:00:00'),
-- Item 6
(15, 6, NULL, 1, 4, 8,  'Item registered in the system.',                               '2026-03-15 16:35:00'),
(16, 6, 1,    2, 5, 8,  'Sample received into Vault 3.',                                '2026-03-15 16:50:00'),
-- Item 7
(17, 7, NULL, 1, 4, 9,  'Item registered in the system.',                               '2026-03-15 17:00:00'),
(18, 7, 1,    2, 5, 9,  'Firearm received into Vault 1 secure storage.',                '2026-03-15 17:15:00'),
-- Item 8
(19, 8, NULL, 1, 4, 10, 'Item registered in the system.',                               '2026-03-16 10:00:00'),
(20, 8, 1,    2, 6, 10, 'Vehicle logged into court evidence impound.',                  '2026-03-16 10:30:00'),
(21, 8, 2,    3, 6, 11, 'Transfer initiated to court evidence room.',                   '2026-04-01 09:00:00'),
-- Item 9
(22, 9, NULL, 1, 3, 12, 'Item registered in the system.',                               '2026-06-05 14:00:00'),
(23, 9, 1,    2, 5, 12, 'USB drives received into Vault 2.',                            '2026-06-05 14:15:00'),
(24, 9, 2,    3, 5, 13, 'Transfer initiated to Digital Forensics Lab.',                 '2026-06-08 08:45:00'),
(25, 9, 3,    6, 7, 13, 'Received by analyst. Forensic imaging in progress.',           '2026-06-08 09:30:00'),
-- Item 10
(26, 10, NULL, 1, 3, 14,'Item registered in the system.',                               '2026-06-05 14:05:00'),
(27, 10, 1,   2, 5, 14, 'Documents received into Vault 4.',                             '2026-06-05 14:20:00'),
-- Item 11
(28, 11, NULL, 1, 3, 15,'Item registered in the system.',                               '2026-06-05 14:10:00'),
(29, 11, 1,   2, 5, 15, 'Router received into Vault 2.',                                '2026-06-05 14:25:00'),
(30, 11, 2,   3, 5, 16, 'Transfer initiated to Digital Forensics Lab.',                 '2026-06-09 08:00:00'),
(31, 11, 3,   6, 7, 16, 'Received by analyst. Network analysis commenced.',             '2026-06-09 08:30:00'),
(32, 11, 6,   7, 7, 17, 'Examination complete. Return transfer initiated.',             '2026-07-15 15:00:00'),
-- Item 12
(33, 12, NULL, 1, 3, 18,'Item registered in the system.',                               '2026-06-10 11:00:00'),
(34, 12, 1,   2, 5, 18, 'Blood sample received into Lab 1 storage.',                   '2026-06-10 11:20:00'),
(35, 12, 2,   3, 5, 19, 'Transfer initiated to Priya Menon for DNA analysis.',          '2026-06-12 09:00:00'),
(36, 12, 3,   6, 8, 19, 'Sample received. DNA analysis commenced.',                    '2026-06-12 09:30:00');

-- =============================================================================
-- AUDIT LOGS  (representative entries)
-- =============================================================================
INSERT INTO `audit_logs` (`user_id`,`action`,`entity_type`,`entity_id`,`description`,`ip_address`) VALUES
(1,  'user.login',              'user',     1,  'Administrator logged in.',                                          '127.0.0.1'),
(1,  'user.created',            'user',     2,  'Created user Inspector Reza Hartono (EMP-002).',                   '127.0.0.1'),
(1,  'user.created',            'user',     3,  'Created user Inspector Aisha Noor (EMP-003).',                     '127.0.0.1'),
(1,  'user.created',            'user',     4,  'Created user Inspector Tariq Yusuf (EMP-004).',                    '127.0.0.1'),
(1,  'user.created',            'user',     5,  'Created user Custodian Budi Santoso (EMP-005).',                   '127.0.0.1'),
(1,  'user.created',            'user',     6,  'Created user Custodian Lena Kovacs (EMP-006).',                    '127.0.0.1'),
(1,  'user.created',            'user',     7,  'Created user Analyst Dr. Chen Wei (EMP-007).',                     '127.0.0.1'),
(1,  'user.created',            'user',     8,  'Created user Analyst Priya Menon (EMP-008).',                      '127.0.0.1'),
(1,  'user.created',            'user',     9,  'Created user Auditor Sven Larsen (EMP-009).',                      '127.0.0.1'),
(1,  'user.created',            'user',     10, 'Created user Inspector Maya Cruz (EMP-010).',                      '127.0.0.1'),
(1,  'investigation.created',   'investigation', 1, 'Created investigation INV-2026-001: Operation Nightfall.',     '127.0.0.1'),
(1,  'investigation.created',   'investigation', 2, 'Created investigation INV-2026-002: Operation Clean Sweep.',   '127.0.0.1'),
(1,  'investigation.created',   'investigation', 3, 'Created investigation INV-2026-003: Operation Sigma.',         '127.0.0.1'),
(2,  'item.created',            'item',     1,  'Registered item EVD-2026-00001: Suspect Laptop Computer.',         '192.168.1.10'),
(2,  'item.created',            'item',     2,  'Registered item EVD-2026-00002: Financial Transaction Records.',   '192.168.1.10'),
(2,  'item.created',            'item',     3,  'Registered item EVD-2026-00003: Cash Bundle -- USD.',             '192.168.1.10'),
(2,  'item.created',            'item',     4,  'Registered item EVD-2026-00004: Mobile Phone -- Samsung.',         '192.168.1.10'),
(4,  'item.created',            'item',     5,  'Registered item EVD-2026-00005: Controlled Substance Sample A.',   '192.168.1.20'),
(4,  'item.created',            'item',     6,  'Registered item EVD-2026-00006: Controlled Substance Sample B.',   '192.168.1.20'),
(4,  'item.created',            'item',     7,  'Registered item EVD-2026-00007: Firearm -- Semi-automatic Pistol.','192.168.1.20'),
(4,  'item.created',            'item',     8,  'Registered item EVD-2026-00008: Seized Vehicle -- Toyota Hilux.', '192.168.1.20'),
(3,  'item.created',            'item',     9,  'Registered item EVD-2026-00009: Compromised USB Drives.',          '192.168.1.15'),
(3,  'item.created',            'item',     10, 'Registered item EVD-2026-00010: Forged Identity Documents.',       '192.168.1.15'),
(3,  'item.created',            'item',     11, 'Registered item EVD-2026-00011: Network Router -- Modified.',      '192.168.1.15'),
(3,  'item.created',            'item',     12, 'Registered item EVD-2026-00012: Biological Sample -- Blood.',      '192.168.1.15'),
(5,  'transfer.initiated',      'custody_transfers', 2, 'Transfer TRF-2026-00002: Laptop to Digital Forensics Lab.','192.168.1.30'),
(7,  'transfer.confirmed',      'custody_transfers', 2, 'Transfer TRF-2026-00002 confirmed by Dr. Chen Wei.',      '192.168.1.25'),
(5,  'transfer.initiated',      'custody_transfers', 7, 'Transfer TRF-2026-00007: Substance Sample A to Chem Lab.','192.168.1.30'),
(8,  'transfer.confirmed',      'custody_transfers', 7, 'Transfer TRF-2026-00007 confirmed by Priya Menon.',       '192.168.1.28'),
(6,  'transfer.initiated',      'custody_transfers', 11,'Transfer TRF-2026-00011: Seized vehicle to Court.',        '192.168.1.35'),
(7,  'transfer.initiated',      'custody_transfers', 17,'Transfer TRF-2026-00017: Router returned to storage.',     '192.168.1.25'),
(9,  'user.login',              'user',     9,  'Auditor Sven Larsen logged in.',                                   '192.168.1.50'),
(9,  'report.viewed',           'item',     NULL,'Auditor viewed all-items report.',                                '192.168.1.50');

-- =============================================================================
-- CUSTODY HISTORY (Single source of truth for chronological timeline)
-- Covers initial assignments upon registration and confirmed transfers
-- =============================================================================
INSERT INTO `custody_history`
  (`custody_history_id`, `item_id`, `from_user_id`, `to_user_id`, `from_location_id`, `to_location_id`, `action_type`, `remarks`, `related_transfer_id`, `recorded_by`, `recorded_at`)
VALUES
-- Item 1: Suspect Laptop Computer
(1,  1,  NULL, 5, NULL, 9,  'initial_assignment', 'Initial custody assignment on item registration.', 1,  2, '2026-01-20 10:15:00'),
(2,  1,  5,    7, 9,    7,  'transfer',           'Transfer to Digital Forensics Lab for examination of device contents.', 2,  5, '2026-01-25 09:45:00'),

-- Item 2: Financial Transaction Records Bundle
(3,  2,  NULL, 5, NULL, 12, 'initial_assignment', 'Initial custody assignment on item registration.', 3,  2, '2026-01-20 10:20:00'),

-- Item 3: Cash Bundle -- USD
(4,  3,  NULL, 5, NULL, 9,  'initial_assignment', 'Initial custody assignment on item registration.', 4,  2, '2026-02-01 14:00:00'),

-- Item 4: Mobile Phone -- Samsung
(5,  4,  NULL, 5, NULL, 10, 'initial_assignment', 'Initial custody assignment on item registration.', 5,  2, '2026-02-15 11:00:00'),

-- Item 5: Controlled Substance Sample A
(6,  5,  NULL, 5, NULL, 11, 'initial_assignment', 'Initial custody assignment on item registration.', 6,  4, '2026-03-15 16:30:00'),
(7,  5,  5,    8, 11,   6,  'transfer',           'Transfer to Chemistry Lab for controlled substance analysis. FSL-2026-0098.', 7, 5, '2026-03-18 09:00:00'),

-- Item 6: Controlled Substance Sample B
(8,  6,  NULL, 5, NULL, 11, 'initial_assignment', 'Initial custody assignment on item registration.', 8,  4, '2026-03-15 16:35:00'),

-- Item 7: Firearm -- Semi-automatic Pistol
(9,  7,  NULL, 5, NULL, 9,  'initial_assignment', 'Initial custody assignment on item registration. Firearm unloaded and safe.', 9, 4, '2026-03-15 17:00:00'),

-- Item 8: Seized Vehicle -- Toyota Hilux
(10, 8,  NULL, 6, NULL, 13, 'initial_assignment', 'Initial custody assignment. Vehicle impounded at South District Station.', 10, 4, '2026-03-16 10:00:00'),

-- Item 9: Compromised USB Drives (Lot)
(11, 9,  NULL, 5, NULL, 10, 'initial_assignment', 'Initial custody assignment on item registration.', 12, 3, '2026-06-05 14:00:00'),
(12, 9,  5,    7, 10,   7,  'transfer',           'Transfer to Digital Forensics Lab for forensic image acquisition and analysis.', 13, 5, '2026-06-08 09:30:00'),

-- Item 10: Forged Identity Documents
(13, 10, NULL, 5, NULL, 12, 'initial_assignment', 'Initial custody assignment on item registration.', 14, 3, '2026-06-05 14:05:00'),

-- Item 11: Network Router -- Modified
(14, 11, NULL, 5, NULL, 10, 'initial_assignment', 'Initial custody assignment on item registration.', 15, 3, '2026-06-05 14:10:00'),
(15, 11, 5,    7, 10,   7,  'transfer',           'Transfer to Digital Forensics Lab for network traffic analysis.', 16, 5, '2026-06-09 08:30:00'),

-- Item 12: Biological Sample -- Blood
(16, 12, NULL, 5, NULL, 6,  'initial_assignment', 'Initial custody assignment on item registration. Biohazard conditions maintained.', 18, 3, '2026-06-10 11:00:00'),
(17, 12, 5,    8, 6,    6,  'transfer',           'Transfer to Priya Menon for DNA analysis.', 19, 5, '2026-06-12 09:30:00');

SET foreign_key_checks = 1;
-- END OF SEED DATA
