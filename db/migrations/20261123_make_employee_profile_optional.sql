-- Datos personales que no intervienen en SUNAT ni en la identidad interna
-- del usuario. Se conservan cuando existen y pueden quedar vacíos.

ALTER TABLE tbl_employee
  MODIFY COLUMN id_doc_number VARCHAR(50) NULL,
  MODIFY COLUMN birth_date DATE NULL,
  MODIFY COLUMN admission_date DATE NULL;
