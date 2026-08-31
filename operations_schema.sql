-- MADAPT ULDs — Operations module
-- Based on the supplied B787-900 Ground Time Utilization / 70-minute turnaround model.
-- Safe to run once on the existing MySQL database.

CREATE TABLE IF NOT EXISTS madapt_operation_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  aircraft_type VARCHAR(32) NOT NULL DEFAULT 'B787-900',
  turnaround_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 70,
  operation_group VARCHAR(80) NOT NULL,
  operation_no SMALLINT UNSIGNED NOT NULL,
  operation_name VARCHAR(255) NOT NULL,
  planned_start_sec SMALLINT UNSIGNED NULL,
  planned_end_sec SMALLINT UNSIGNED NULL,
  allocated_sec SMALLINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_operation_template (aircraft_type, turnaround_minutes, operation_group, operation_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_turnarounds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  flight_number VARCHAR(32) NULL,
  aircraft_type VARCHAR(32) NOT NULL DEFAULT 'B787-900',
  turnaround_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 70,
  block_in_at DATETIME NULL,
  target_block_out_at DATETIME NULL,
  status ENUM('PLANNED','ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
  created_by VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_turnaround_status (status),
  KEY idx_turnaround_block_in (block_in_at),
  KEY idx_turnaround_flight (flight_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_operations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  turnaround_id BIGINT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NULL,
  operation_group VARCHAR(80) NOT NULL,
  operation_no SMALLINT UNSIGNED NOT NULL,
  operation_name VARCHAR(255) NOT NULL,
  planned_start_sec SMALLINT UNSIGNED NULL,
  planned_end_sec SMALLINT UNSIGNED NULL,
  allocated_sec SMALLINT UNSIGNED NULL,
  status ENUM('PENDING','STARTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  operator VARCHAR(120) NULL,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_operations_turnaround (turnaround_id),
  KEY idx_operations_status (status),
  KEY idx_operations_group (operation_group),
  CONSTRAINT fk_operations_turnaround FOREIGN KEY (turnaround_id)
    REFERENCES madapt_turnarounds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madapt_operation_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  turnaround_id BIGINT UNSIGNED NULL,
  operation_id BIGINT UNSIGNED NULL,
  action ENUM('BLOCK_IN','STARTED','COMPLETED','RESET','CANCELLED','NOTE') NOT NULL,
  operator VARCHAR(120) NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'WEB',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_operation_events_turnaround (turnaround_id),
  KEY idx_operation_events_operation (operation_id),
  KEY idx_operation_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 20 operations supplied in Code.gs.
INSERT INTO madapt_operation_templates
(aircraft_type, turnaround_minutes, operation_group, operation_no, operation_name, planned_start_sec, planned_end_sec, allocated_sec)
VALUES
('B787-900',70,'Passenger Services',1,'Engine Shutdown / Chocks on',0,NULL,NULL),
('B787-900',70,'Passenger Services',2,'Position Ground Support Equip.',0,120,120),
('B787-900',70,'Passenger Services',3,'Deplane Passengers at 23 pax per minute',120,960,840),
('B787-900',70,'Passenger Services',4,'Cabin Cleaning',1020,2100,1080),
('B787-900',70,'Passenger Services',5,'Catering Forward & Mid Galley',1020,2040,1020),
('B787-900',70,'Passenger Services',6,'Catering Aft Galley',1020,1980,960),
('B787-900',70,'Passenger Services',7,'Cabin Inspection (Security)',2100,2400,300),
('B787-900',70,'Passenger Services',8,'Boarding clearance',2460,NULL,NULL),
('B787-900',70,'Passenger Services',9,'Passenger boarding at 13 per minute',2520,3960,1440),
('B787-900',70,'Passenger Services',10,'Documentation and passenger Count',3960,4080,120),
('B787-900',70,'Passenger Services',11,'Door Closure and removal of passenger bridge / stairs',4080,4200,120),
('B787-900',70,'Bag / CGO Handling',1,'Unload Aft Compartment',0,1020,900),
('B787-900',70,'Bag / CGO Handling',2,'Unload Forward Compartment',120,1320,1200),
('B787-900',70,'Bag / CGO Handling',3,'Unload Bulk hold',120,600,480),
('B787-900',70,'Bag / CGO Handling',4,'Load Forward Compartment',1320,2520,1200),
('B787-900',70,'Bag / CGO Handling',5,'Load Aft Compartment',1920,3720,1800),
('B787-900',70,'Bag / CGO Handling',6,'Load Bulk hold',3720,4200,480),
('B787-900',70,'A/C Servicing',1,'Refueling',0,2820,1800),
('B787-900',70,'A/C Servicing',2,'Service Toilets',120,840,720),
('B787-900',70,'A/C Servicing',3,'Service Potable water',120,780,660)
ON DUPLICATE KEY UPDATE operation_name=VALUES(operation_name), planned_start_sec=VALUES(planned_start_sec), planned_end_sec=VALUES(planned_end_sec), allocated_sec=VALUES(allocated_sec);
