-- hojas_ruta desde lote: no hay jornada (solo consultor_lote_id).
-- La columna jornada_id debe aceptar NULL; la FK a jornadas aplica solo cuando jornada_id no es NULL.

USE kyz_logistic;

-- Si el nombre del FK en el servidor no es fk_hr_jornada, usar:
--   SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hojas_ruta';

ALTER TABLE hojas_ruta DROP FOREIGN KEY fk_hr_jornada;

ALTER TABLE hojas_ruta
  MODIFY COLUMN jornada_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE hojas_ruta
  ADD CONSTRAINT fk_hr_jornada
    FOREIGN KEY (jornada_id) REFERENCES jornadas (id)
    ON UPDATE CASCADE ON DELETE CASCADE;
