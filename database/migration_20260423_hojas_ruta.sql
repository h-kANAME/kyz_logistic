-- =============================================================
-- KYZ Logistica - Migracion: persistencia de hojas de ruta
-- =============================================================

USE kyz_logistic;

CREATE TABLE IF NOT EXISTS hojas_ruta (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    jornada_id         INT UNSIGNED      NOT NULL,
    consultor_id       INT UNSIGNED      NOT NULL,
    titulo             VARCHAR(140)      NOT NULL,
    estado             ENUM('validada','en_curso','completada','cancelada') NOT NULL DEFAULT 'validada',
    constraints_json   JSON              NOT NULL,
    plan_json          JSON              NOT NULL,
    creada_por         INT UNSIGNED      NOT NULL,
    created_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_hr_jornada (jornada_id),
    INDEX idx_hr_consultor (consultor_id),
    INDEX idx_hr_estado (estado),
    INDEX idx_hr_created (created_at),
    CONSTRAINT fk_hr_jornada
        FOREIGN KEY (jornada_id) REFERENCES jornadas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_consultor
        FOREIGN KEY (consultor_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hr_creada_por
        FOREIGN KEY (creada_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
