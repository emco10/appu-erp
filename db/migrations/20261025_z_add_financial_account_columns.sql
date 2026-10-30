-- Compatibilidad para bases creadas antes de que las cabeceras de pago
-- incluyeran la cuenta financiera. Se ejecuta al final del lote de migraciones.
ALTER TABLE tbl_incoming_payment
  ADD COLUMN IF NOT EXISTS financial_account_id INT NULL AFTER payment_method;
ALTER TABLE tbl_outgoing_payment
  ADD COLUMN IF NOT EXISTS financial_account_id INT NULL AFTER payment_method;
