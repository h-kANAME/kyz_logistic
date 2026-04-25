-- =============================================================
-- KYZ Logistica - Indice compuesto para listados por jornada + orden
-- Evita filesort costoso en findByJornada (Out of sort memory en volumen).
-- =============================================================

USE kyz_logistic;

ALTER TABLE asignaciones
  ADD INDEX idx_jornada_orden (jornada_id, orden);

-- Listados de hojas por jornada ordenados por fecha (evita filesort en paginación)
ALTER TABLE hojas_ruta
  ADD INDEX idx_hr_jornada_created (jornada_id, created_at);
