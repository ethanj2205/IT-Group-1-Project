CREATE DATABASE IF NOT EXISTS mediqueue_sa;
USE mediqueue_sa;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('patient','doctor','admin') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hospitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT 'Durban',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE hospitals
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) NOT NULL DEFAULT 'Durban',
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;

CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  date_of_birth DATE NULL,
  contact_number VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patient_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  hospital_id INT NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  available BOOLEAN NOT NULL DEFAULT TRUE,
  average_consultation_minutes DECIMAL(6,2) NOT NULL DEFAULT 15,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doctor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_doctor_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

CREATE TABLE IF NOT EXISTS triage_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  symptoms TEXT NOT NULL,
  urgency ENUM('Low','Moderate','High') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_triage_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX idx_triage_patient_created (patient_id, created_at)
);

CREATE TABLE IF NOT EXISTS queue_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_number VARCHAR(30) NOT NULL UNIQUE,
  hospital_id INT NOT NULL,
  patient_id INT NOT NULL,
  triage_id INT NOT NULL,
  doctor_id INT NULL,
  urgency ENUM('Low','Moderate','High') NOT NULL,
  status ENUM('Waiting','In Consultation','Completed','Referral','Cancelled') NOT NULL DEFAULT 'Waiting',
  referral_hospital_id INT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_queue_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_queue_referral_hospital FOREIGN KEY (referral_hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
  CONSTRAINT fk_queue_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_queue_triage FOREIGN KEY (triage_id) REFERENCES triage_records(id),
  CONSTRAINT fk_queue_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id),
  INDEX idx_queue_waiting (hospital_id, status, urgency, joined_at),
  INDEX idx_queue_patient (patient_id, status)
);

CREATE TABLE IF NOT EXISTS consultations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_entry_id INT NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  notes TEXT NOT NULL,
  outcome ENUM('In Progress','Completed','Referral') NOT NULL DEFAULT 'In Progress',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_consult_queue FOREIGN KEY (queue_entry_id) REFERENCES queue_entries(id),
  CONSTRAINT fk_consult_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_consult_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NULL,
  entity_id INT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at)
);

/*
 * Migration for an existing MediQueue database.
 * CREATE TABLE IF NOT EXISTS does not change an existing column definition,
 * so this statement fixes databases created with the old two-value ENUM.
 */
ALTER TABLE queue_entries
  ADD COLUMN IF NOT EXISTS referral_hospital_id INT NULL;

ALTER TABLE consultations
  MODIFY outcome ENUM('In Progress','Completed','Referral') NOT NULL DEFAULT 'In Progress';


/*
 * Demo data
 *
 * The web page uses Patient ID 1 and Hospital ID 1 by default.  The original
 * project created the tables but did not create the records those controls
 * point to, which caused the foreign-key error when triage was submitted.
 * These INSERTs are safe to run more than once.
 */
/*
 * Durban referral hospitals used by the live GPS referral recommender.
 * Coordinates are stored so the application can calculate straight-line
 * distance from the patient's live browser GPS position.
 */
INSERT INTO hospitals (id, name, city, latitude, longitude)
VALUES
  (1, 'Addington Hospital', 'Durban', -29.8621000, 31.0413000),
  (2, 'Inkosi Albert Luthuli Central Hospital', 'Durban', -29.8734200, 30.9580000),
  (3, 'Victoria Mxenge Regional Hospital', 'Durban', -29.8814700, 30.9895600),
  (4, 'Prince Mshiyeni Memorial Hospital', 'Durban', -29.9548242, 30.9366247),
  (5, 'R.K. Khan Hospital', 'Durban', -29.9336000, 30.8767000),
  (6, 'Mahatma Gandhi Memorial Hospital', 'Durban', -29.7038000, 31.0157000),
  (7, 'St Aidan’s Hospital', 'Durban', -29.8511300, 31.0106400),
  (8, 'Wentworth Hospital', 'Durban', -29.9217000, 30.9998000),
  (9, 'King Dinuzulu Hospital Complex', 'Durban', -29.8233000, 31.0009000),
  (10, 'Clairwood Hospital', 'Durban', -29.9277000, 30.9728000),
  (11, 'St Mary’s Hospital', 'Durban', -29.7900000, 30.9000000),
  (12, 'Hillcrest Hospital', 'Durban', -29.7838000, 30.7623000)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  city = VALUES(city),
  latitude = VALUES(latitude),
  longitude = VALUES(longitude);

INSERT INTO users (id, email, password_hash, role)
VALUES
  (1, 'patient1@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'patient'),
  (2, 'doctor1@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor')
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role);

INSERT INTO users (id, email, password_hash, role) VALUES
  (2, 'doctor1@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (3, 'doctor2@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (4, 'doctor3@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (5, 'doctor4@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (6, 'doctor5@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (7, 'doctor6@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (8, 'doctor7@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (9, 'doctor8@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (10, 'doctor9@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (11, 'doctor10@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (12, 'doctor11@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (13, 'doctor12@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (14, 'doctor13@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (15, 'doctor14@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (16, 'doctor15@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (17, 'doctor16@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (18, 'doctor17@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (19, 'doctor18@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (20, 'doctor19@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (21, 'doctor20@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (22, 'doctor21@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (23, 'doctor22@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (24, 'doctor23@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor'),
  (25, 'doctor24@mediqueue.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7u8X7w8Q2vQ1V0b3mW', 'doctor')
ON DUPLICATE KEY UPDATE email=VALUES(email), password_hash=VALUES(password_hash), role=VALUES(role);

INSERT INTO patients (id, user_id, full_name, date_of_birth, contact_number)
VALUES
  (1, 1, 'Patient 1', NULL, '0000000000')
ON DUPLICATE KEY UPDATE
  user_id = VALUES(user_id),
  full_name = VALUES(full_name),
  contact_number = VALUES(contact_number);

INSERT INTO doctors (id, user_id, hospital_id, full_name, available, average_consultation_minutes)
VALUES
  (1, 2, 1, 'Dr. Thabo Mokoena', TRUE, 15),
  (2, 3, 1, 'Dr. Naledi Khumalo', TRUE, 15),
  (3, 4, 2, 'Dr. Ayesha Naidoo', TRUE, 18),
  (4, 5, 2, 'Dr. Sipho Dlamini', TRUE, 15),
  (5, 6, 3, 'Dr. Lerato Molefe', TRUE, 15),
  (6, 7, 3, 'Dr. Ryan Pillay', TRUE, 17),
  (7, 8, 4, 'Dr. Zanele Ndlovu', TRUE, 16),
  (8, 9, 4, 'Dr. Yusuf Ismail', TRUE, 15),
  (9, 10, 5, 'Dr. Candice Naicker', TRUE, 15),
  (10, 11, 5, 'Dr. Sibusiso Mthembu', TRUE, 18),
  (11, 12, 6, 'Dr. Bongani Cele', TRUE, 16),
  (12, 13, 6, 'Dr. Priya Govender', TRUE, 15),
  (13, 14, 7, 'Dr. Michael Naidoo', TRUE, 15),
  (14, 15, 7, 'Dr. Ayanda Zulu', TRUE, 17),
  (15, 16, 8, 'Dr. Nkosazana Khanyile', TRUE, 15),
  (16, 17, 8, 'Dr. Ethan Jacobs', TRUE, 16),
  (17, 18, 9, 'Dr. Kagiso Molefe', TRUE, 15),
  (18, 19, 9, 'Dr. Refilwe Maseko', TRUE, 18),
  (19, 20, 10, 'Dr. Kabelo Mokoena', TRUE, 15),
  (20, 21, 10, 'Dr. Michelle Botha', TRUE, 16),
  (21, 22, 11, 'Dr. Liam Petersen', TRUE, 15),
  (22, 23, 11, 'Dr. Fatima Adams', TRUE, 18),
  (23, 24, 12, 'Dr. Willem van der Merwe', TRUE, 15),
  (24, 25, 12, 'Dr. Amina Daniels', TRUE, 16)
ON DUPLICATE KEY UPDATE
  user_id = VALUES(user_id),
  hospital_id = VALUES(hospital_id),
  full_name = VALUES(full_name),
  available = VALUES(available),
  average_consultation_minutes = VALUES(average_consultation_minutes);

/*
 * Patient locations and prescriptions
 * These tables keep the new patient-record features separate so existing
 * MediQueue installations can be upgraded without losing queue data.
 */
CREATE TABLE IF NOT EXISTS patient_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL UNIQUE,
  location VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy_meters DECIMAL(10,2) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_location_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

/* Upgrade existing installations with live GPS fields. */
ALTER TABLE patient_locations
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL,
  ADD COLUMN IF NOT EXISTS accuracy_meters DECIMAL(10,2) NULL;

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  consultation_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  medication VARCHAR(255) NOT NULL,
  instructions TEXT NULL,
  quantity DECIMAL(10,2) NULL,
  quantity_unit VARCHAR(50) NULL,
  duration_value INT NULL,
  duration_unit VARCHAR(30) NULL,
  times_per_day INT NULL,
  dose_amount VARCHAR(100) NULL,
  dose_unit VARCHAR(50) NULL,
  medication_times VARCHAR(255) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  refill_required TINYINT(1) NOT NULL DEFAULT 0,
  refill_date DATE NULL,
  checkup_required TINYINT(1) NOT NULL DEFAULT 0,
  checkup_date DATE NULL,
  followup_type VARCHAR(30) NULL,
  followup_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
  CONSTRAINT fk_prescription_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_prescription_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  INDEX idx_prescription_patient (patient_id, created_at)
);

/* Upgrade existing installations with detailed prescription scheduling fields. */
ALTER TABLE prescriptions
  ADD COLUMN IF NOT EXISTS quantity DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS quantity_unit VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS duration_value INT NULL,
  ADD COLUMN IF NOT EXISTS duration_unit VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS times_per_day INT NULL,
  ADD COLUMN IF NOT EXISTS dose_amount VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS dose_unit VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS medication_times VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS start_date DATE NULL,
  ADD COLUMN IF NOT EXISTS end_date DATE NULL,
  ADD COLUMN IF NOT EXISTS refill_required TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS refill_date DATE NULL,
  ADD COLUMN IF NOT EXISTS checkup_required TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS checkup_date DATE NULL,
  ADD COLUMN IF NOT EXISTS followup_type VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS followup_notes TEXT NULL;
