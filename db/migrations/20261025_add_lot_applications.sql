-- Trazabilidad de lotes consumidos por salidas de inventario.
CREATE TABLE tbl_inventory_lot_application (
    id INT NOT NULL AUTO_INCREMENT,
    source_type VARCHAR(40) NOT NULL,
    source_id INT NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    lot_id INT NOT NULL,
    quantity DECIMAL(18,6) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by INT NULL,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_lot_application_source (source_type, source_id, product_id, status),
    KEY idx_lot_application_lot (lot_id),
    CONSTRAINT fk_lot_application_product FOREIGN KEY (product_id) REFERENCES tbl_product(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lot_application_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lot_application_lot FOREIGN KEY (lot_id) REFERENCES tbl_product_lot(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
