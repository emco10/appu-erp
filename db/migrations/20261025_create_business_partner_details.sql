CREATE TABLE IF NOT EXISTS tbl_business_partner_address (
  id BIGINT NOT NULL AUTO_INCREMENT,
  partner_type ENUM('customer','provider') NOT NULL,
  partner_id INT NOT NULL,
  address_type ENUM('billing','shipping','other') NOT NULL DEFAULT 'billing',
  address_code VARCHAR(30) NOT NULL,
  address_name VARCHAR(100) NOT NULL,
  address_line VARCHAR(300) NOT NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'Perú',
  department_id VARCHAR(2) NOT NULL DEFAULT '',
  province_id VARCHAR(4) NOT NULL DEFAULT '',
  district_id VARCHAR(6) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  reference VARCHAR(200) NOT NULL DEFAULT '',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active_status TINYINT(1) NOT NULL DEFAULT 1,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partner_address_code (partner_type, partner_id, address_code),
  KEY idx_partner_address (partner_type, partner_id, active_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_business_partner_contact (
  id BIGINT NOT NULL AUTO_INCREMENT,
  partner_type ENUM('customer','provider') NOT NULL,
  partner_id INT NOT NULL,
  contact_name VARCHAR(200) NOT NULL,
  position_name VARCHAR(100) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  mobile VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(200) NOT NULL DEFAULT '',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active_status TINYINT(1) NOT NULL DEFAULT 1,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_partner_contact (partner_type, partner_id, active_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_business_partner_address(partner_type,partner_id,address_type,address_code,address_name,address_line,country,department_id,province_id,district_id,is_default,active_status)
SELECT 'customer',client_id,'billing','PRINCIPAL','Dirección fiscal',address,'Perú',department_id,province_id,district_id,1,active_status FROM tbl_customer WHERE TRIM(address)<>'';
INSERT IGNORE INTO tbl_business_partner_address(partner_type,partner_id,address_type,address_code,address_name,address_line,country,city,reference,is_default,active_status)
SELECT 'provider',id,'billing','PRINCIPAL','Dirección fiscal',address,country,city,district,1,active_status FROM tbl_provider WHERE TRIM(address)<>'';

INSERT INTO tbl_business_partner_contact(partner_type,partner_id,contact_name,phone,is_default,active_status)
SELECT 'customer',client_id,contact1_name,contact1_phone,1,active_status FROM tbl_customer c WHERE TRIM(contact1_name)<>'' AND NOT EXISTS(SELECT 1 FROM tbl_business_partner_contact x WHERE x.partner_type='customer' AND x.partner_id=c.client_id AND x.contact_name=c.contact1_name);
INSERT INTO tbl_business_partner_contact(partner_type,partner_id,contact_name,phone,is_default,active_status)
SELECT 'provider',id,contact1_name,contact1_phone,1,active_status FROM tbl_provider p WHERE TRIM(contact1_name)<>'' AND NOT EXISTS(SELECT 1 FROM tbl_business_partner_contact x WHERE x.partner_type='provider' AND x.partner_id=p.id AND x.contact_name=p.contact1_name);
