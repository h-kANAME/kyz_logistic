-- Indice de inicio del ultimo bloque enviado a Google Maps (para alinear UX de detalle con el celular)
USE kyz_logistic;

ALTER TABLE hojas_ruta
  ADD COLUMN last_opened_batch_start INT UNSIGNED NULL DEFAULT NULL
  AFTER next_offset;
