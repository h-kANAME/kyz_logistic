-- =============================================================
-- MIGRATION: consultor_perfiles + config consultor
-- Fecha: 2026-04-23
-- =============================================================

USE kyz_logistic;

CREATE TABLE IF NOT EXISTS consultor_perfiles (
    usuario_id               INT UNSIGNED    NOT NULL,
    punto_partida_direccion  VARCHAR(255)    DEFAULT NULL,
    punto_partida_latitud    DECIMAL(10,7)   DEFAULT NULL,
    punto_partida_longitud   DECIMAL(10,7)   DEFAULT NULL,
    movilidad                ENUM('a_pie','vehiculo','autobus','bicicleta') NOT NULL DEFAULT 'a_pie',
    disponibilidad_minutos   SMALLINT UNSIGNED NOT NULL DEFAULT 240,
    created_at               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id),
    INDEX idx_movilidad (movilidad),
    CONSTRAINT fk_perfil_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
