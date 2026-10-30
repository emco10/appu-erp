ALTER TABLE tbl_document_series
    MODIFY document_type ENUM(
        'INVOICE','RECEIPT','CREDIT_NOTE','QUOTATION','SALES_ORDER',
        'PURCHASE','PURCHASE_REQUEST','ORDER','GOODS_RECEIPT','REMISSION_GUIDE',
        'INCOMING_PAYMENT','OUTGOING_PAYMENT'
    ) NOT NULL;

INSERT IGNORE INTO tbl_document_series (document_type, series, next_number, number_padding, active_status) VALUES
    ('QUOTATION', 'COTZ', 1, 7, 1),
    ('SALES_ORDER', 'OV', 1, 7, 1),
    ('PURCHASE', 'CP', 1, 7, 1),
    ('PURCHASE_REQUEST', 'SC', 1, 7, 1),
    ('ORDER', 'OC', 1, 7, 1),
    ('GOODS_RECEIPT', 'NI', 1, 7, 1),
    ('REMISSION_GUIDE', 'GI', 1, 7, 1),
    ('INCOMING_PAYMENT', 'COB', 1, 7, 1),
    ('OUTGOING_PAYMENT', 'PAG', 1, 7, 1);
