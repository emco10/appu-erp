-- Additive valuation snapshots. Historical amounts/ledger are not recalculated.
-- NULL denotes a receipt created before the currency snapshot existed.
ALTER TABLE tbl_goods_receipt
 ADD COLUMN valuation_currency VARCHAR(10) NULL AFTER currency,
 ADD COLUMN valuation_exchange_rate DECIMAL(18,8) NULL AFTER valuation_currency;

ALTER TABLE tbl_goods_receipt_detail
 ADD COLUMN document_unit_cost DECIMAL(16,6) NULL AFTER quantity,
 ADD COLUMN document_inventory_value DECIMAL(16,4) NULL AFTER document_unit_cost;

-- Costs of delivery are entered and allocated in the receipt valuation currency.
-- Their currency/total_cost and detail allocated_cost already persist that unit.
