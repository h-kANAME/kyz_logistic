-- Lotes mensuales + Hojas diarias v2

USE kyz_logistic;

CREATE TABLE IF NOT EXISTS lotes_mensuales (
    id               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    anio             SMALLINT UNSIGNED NOT NULL,
    mes              TINYINT UNSIGNED  NOT NULL,
    numero_lote      TINYINT UNSIGNED  NOT NULL,
    titulo           VARCHAR(140)      NOT NULL,
    observacion      VARCHAR(255)      DEFAULT NULL,
    created_by       INT UNSIGNED      NOT NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lote_mes_numero (anio, mes, numero_lote),
    INDEX idx_lote_mes (anio, mes),
    CONSTRAINT fk_lote_created_by
      FOREIGN KEY (created_by) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lote_domicilios (
    id               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    lote_id          BIGINT UNSIGNED   NOT NULL,
    domicilio_id     INT UNSIGNED      NOT NULL,
    orden_base       INT UNSIGNED      NOT NULL DEFAULT 0,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lote_domicilio (lote_id, domicilio_id),
    INDEX idx_ld_lote_orden (lote_id, orden_base),
    INDEX idx_ld_domicilio (domicilio_id),
    CONSTRAINT fk_ld_lote
      FOREIGN KEY (lote_id) REFERENCES lotes_mensuales(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ld_domicilio
      FOREIGN KEY (domicilio_id) REFERENCES domicilios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consultor_lotes (
    id               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    consultor_id     INT UNSIGNED      NOT NULL,
    lote_id          BIGINT UNSIGNED   NOT NULL,
    supervisor_id    INT UNSIGNED      NOT NULL,
    fecha_asignacion DATE              NOT NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_consultor_lote (consultor_id, lote_id),
    UNIQUE KEY uq_lote_exclusivo (lote_id),
    INDEX idx_cl_consultor (consultor_id),
    INDEX idx_cl_supervisor (supervisor_id),
    CONSTRAINT fk_cl_consultor
      FOREIGN KEY (consultor_id) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cl_supervisor
      FOREIGN KEY (supervisor_id) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cl_lote
      FOREIGN KEY (lote_id) REFERENCES lotes_mensuales(id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE hojas_ruta
  ADD COLUMN IF NOT EXISTS consultor_lote_id BIGINT UNSIGNED NULL AFTER consultor_id,
  ADD COLUMN IF NOT EXISTS next_offset INT UNSIGNED NOT NULL DEFAULT 0 AFTER estado,
  ADD COLUMN IF NOT EXISTS last_opened_batch_at TIMESTAMP NULL DEFAULT NULL AFTER next_offset;

ALTER TABLE hojas_ruta
  ADD INDEX IF NOT EXISTS idx_hr_consultor_lote (consultor_lote_id);

ALTER TABLE hojas_ruta
  ADD CONSTRAINT fk_hr_consultor_lote
  FOREIGN KEY (consultor_lote_id) REFERENCES consultor_lotes(id)
  ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS visitas_registro (
    id               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    hoja_ruta_id     BIGINT UNSIGNED   NOT NULL,
    domicilio_id     INT UNSIGNED      NOT NULL,
    visitado         TINYINT(1)        NOT NULL DEFAULT 0,
    documento_firmado TINYINT(1)       NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED      NOT NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vr_hoja_domicilio (hoja_ruta_id, domicilio_id),
    INDEX idx_vr_domicilio (domicilio_id),
    INDEX idx_vr_created (created_at),
    CONSTRAINT fk_vr_hoja
      FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vr_domicilio
      FOREIGN KEY (domicilio_id) REFERENCES domicilios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vr_created_by
      FOREIGN KEY (created_by) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitas_observaciones (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    visita_registro_id BIGINT UNSIGNED  NOT NULL,
    observacion       VARCHAR(255)      NOT NULL,
    created_by        INT UNSIGNED      NOT NULL,
    created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_vo_visita_created (visita_registro_id, created_at),
    CONSTRAINT fk_vo_visita
      FOREIGN KEY (visita_registro_id) REFERENCES visitas_registro(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vo_created_by
      FOREIGN KEY (created_by) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
