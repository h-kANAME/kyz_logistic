-- =============================================================
-- KYZ Logistica - Migracion: punto de retorno en perfil consultor
-- =============================================================

USE kyz_logistic;

ALTER TABLE consultor_perfiles
  ADD COLUMN IF NOT EXISTS punto_retorno_direccion VARCHAR(255) DEFAULT NULL AFTER punto_partida_longitud,
  ADD COLUMN IF NOT EXISTS punto_retorno_latitud DECIMAL(10,7) DEFAULT NULL AFTER punto_retorno_direccion,
  ADD COLUMN IF NOT EXISTS punto_retorno_longitud DECIMAL(10,7) DEFAULT NULL AFTER punto_retorno_latitud;

UPDATE consultor_perfiles
SET
  punto_retorno_direccion = COALESCE(punto_retorno_direccion, punto_partida_direccion),
  punto_retorno_latitud = COALESCE(punto_retorno_latitud, punto_partida_latitud),
  punto_retorno_longitud = COALESCE(punto_retorno_longitud, punto_partida_longitud)
WHERE punto_retorno_direccion IS NULL
   OR punto_retorno_latitud IS NULL
   OR punto_retorno_longitud IS NULL;
