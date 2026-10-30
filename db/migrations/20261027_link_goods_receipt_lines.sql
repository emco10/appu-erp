-- Integra las entradas de mercancías existentes con el motor documental común.
INSERT INTO tbl_document_line_application
    (source_type,source_id,source_line_id,target_type,target_id,target_line_id,stage,quantity,status,created_by,created_at,reversed_by,reversed_at)
SELECT
    LOWER(detail.base_document_type),receipt.base_document_id,detail.base_line_id,
    'goods_receipt',receipt.id,detail.id,'RECEPTION',detail.quantity,
    IF(receipt.status='Anulada','REVERSED','ACTIVE'),receipt.received_by,receipt.registration_date,
    IF(receipt.status='Anulada',receipt.received_by,NULL),IF(receipt.status='Anulada',receipt.registration_date,NULL)
FROM tbl_goods_receipt_detail detail
JOIN tbl_goods_receipt receipt ON receipt.id=detail.goods_receipt_id
WHERE receipt.base_document_id IS NOT NULL
  AND detail.base_line_id IS NOT NULL
  AND detail.quantity>0
ON DUPLICATE KEY UPDATE
    quantity=VALUES(quantity),status=VALUES(status),
    reversed_by=VALUES(reversed_by),reversed_at=VALUES(reversed_at);
