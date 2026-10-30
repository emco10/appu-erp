CREATE TABLE IF NOT EXISTS tbl_purchase_request (
    id INT NOT NULL AUTO_INCREMENT,
    number VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    requester_id INT NOT NULL DEFAULT 0,
    issue_date DATE NOT NULL,
    required_date DATE NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
    observation VARCHAR(500) NOT NULL DEFAULT '',
    total_estimate DECIMAL(18,4) NOT NULL DEFAULT 0,
    created_by INT NOT NULL DEFAULT 0,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchase_request_number (number),
    KEY idx_purchase_request_status (status),
    KEY idx_purchase_request_dates (issue_date, required_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_purchase_request_detail (
    id INT NOT NULL AUTO_INCREMENT,
    request_id INT NOT NULL,
    product_id INT NOT NULL DEFAULT 0,
    item_code VARCHAR(100) NOT NULL DEFAULT '',
    item_name VARCHAR(300) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
    estimated_unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(18,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_purchase_request_detail_request (request_id),
    KEY idx_purchase_request_detail_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
