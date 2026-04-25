-- =============================================================
-- KYZ Logística - Schema de base de datos
-- Stack: MySQL 8+  |  Charset: utf8mb4
-- =============================================================

CREATE DATABASE IF NOT EXISTS kyz_logistic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kyz_logistic;

-- -------------------------------------------------------------
-- usuarios: Admin, Supervisor, Consultor
-- -------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100)    NOT NULL,
    email         VARCHAR(150)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    rol           ENUM('admin','supervisor','consultor') NOT NULL DEFAULT 'consultor',
    activo        TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email),
    INDEX idx_rol   (rol),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- consultor_perfiles: configuracion operativa por consultor
-- -------------------------------------------------------------
CREATE TABLE consultor_perfiles (
    usuario_id               INT UNSIGNED    NOT NULL,
    punto_partida_direccion  VARCHAR(255)    DEFAULT NULL,
    punto_partida_latitud    DECIMAL(10,7)   DEFAULT NULL,
    punto_partida_longitud   DECIMAL(10,7)   DEFAULT NULL,
    punto_retorno_direccion  VARCHAR(255)    DEFAULT NULL,
    punto_retorno_latitud    DECIMAL(10,7)   DEFAULT NULL,
    punto_retorno_longitud   DECIMAL(10,7)   DEFAULT NULL,
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

-- -------------------------------------------------------------
-- secciones: zonas geográficas (15 zonas en Santa Fe)
-- -------------------------------------------------------------
CREATE TABLE secciones (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    numero      TINYINT UNSIGNED NOT NULL,
    descripcion VARCHAR(100)    DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- domicilios: cargados desde Excel, una sola fuente de verdad
-- -------------------------------------------------------------
CREATE TABLE domicilios (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    calle        VARCHAR(150)    NOT NULL,
    altura       SMALLINT UNSIGNED NOT NULL,
    seccion_id   INT UNSIGNED    NOT NULL,
    provincia    VARCHAR(100)    NOT NULL DEFAULT 'Santa Fe',
    pais         VARCHAR(100)    NOT NULL DEFAULT 'Argentina',
    servicio     ENUM('Gas Natural','Servicio Social') NOT NULL,
    latitud      DECIMAL(10,7)   DEFAULT NULL,
    longitud     DECIMAL(10,7)   DEFAULT NULL,
    geocodificado TINYINT(1)     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_seccion        (seccion_id),
    INDEX idx_servicio       (servicio),
    INDEX idx_calle_altura   (calle, altura),
    INDEX idx_geocodificado  (geocodificado),
    CONSTRAINT fk_domicilio_seccion
        FOREIGN KEY (seccion_id) REFERENCES secciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- jornadas: día de trabajo asignado a un consultor
-- -------------------------------------------------------------
CREATE TABLE jornadas (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    consultor_id     INT UNSIGNED    NOT NULL,
    supervisor_id    INT UNSIGNED    NOT NULL,
    fecha            DATE            NOT NULL,
    estado           ENUM('borrador','activa','completada','cancelada') NOT NULL DEFAULT 'borrador',
    total_asignados  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_visitados  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_consultor_fecha (consultor_id, fecha),
    INDEX idx_fecha       (fecha),
    INDEX idx_supervisor  (supervisor_id),
    INDEX idx_estado      (estado),
    CONSTRAINT fk_jornada_consultor
        FOREIGN KEY (consultor_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_jornada_supervisor
        FOREIGN KEY (supervisor_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- hojas_ruta: propuestas validadas por consultor para seguimiento
-- -------------------------------------------------------------
CREATE TABLE hojas_ruta (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    jornada_id         INT UNSIGNED      NULL DEFAULT NULL,
    consultor_id       INT UNSIGNED      NOT NULL,
    consultor_lote_id  BIGINT UNSIGNED   DEFAULT NULL,
    titulo             VARCHAR(140)      NOT NULL,
    estado             ENUM('validada','en_curso','completada','cancelada') NOT NULL DEFAULT 'validada',
    next_offset        INT UNSIGNED      NOT NULL DEFAULT 0,
    last_opened_batch_start INT UNSIGNED NULL DEFAULT NULL,
    last_opened_batch_at TIMESTAMP       NULL DEFAULT NULL,
    constraints_json   JSON              NOT NULL,
    plan_json          JSON              NOT NULL,
    creada_por         INT UNSIGNED      NOT NULL,
    created_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_hr_jornada (jornada_id),
    INDEX idx_hr_jornada_created (jornada_id, created_at),
    INDEX idx_hr_consultor (consultor_id),
    INDEX idx_hr_consultor_lote (consultor_lote_id),
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

-- -------------------------------------------------------------
-- asignaciones: domicilios asignados a una jornada con orden
-- -------------------------------------------------------------
CREATE TABLE asignaciones (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    jornada_id    INT UNSIGNED    NOT NULL,
    domicilio_id  INT UNSIGNED    NOT NULL,
    orden         SMALLINT UNSIGNED NOT NULL,
    estado        ENUM('pendiente','visitado','ausente','reagendar') NOT NULL DEFAULT 'pendiente',
    firmado       TINYINT(1)      NOT NULL DEFAULT 0,
    observacion   TEXT            DEFAULT NULL,
    visitado_at   TIMESTAMP       NULL DEFAULT NULL,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jornada_domicilio (jornada_id, domicilio_id),
    INDEX idx_jornada    (jornada_id),
    INDEX idx_jornada_orden (jornada_id, orden),
    INDEX idx_domicilio  (domicilio_id),
    INDEX idx_estado     (estado),
    CONSTRAINT fk_asignacion_jornada
        FOREIGN KEY (jornada_id) REFERENCES jornadas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_asignacion_domicilio
        FOREIGN KEY (domicilio_id) REFERENCES domicilios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- lotes_mensuales: paquetes de direcciones por mes
-- -------------------------------------------------------------
CREATE TABLE lotes_mensuales (
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

-- -------------------------------------------------------------
-- lote_domicilios: direcciones que componen un lote mensual
-- -------------------------------------------------------------
CREATE TABLE lote_domicilios (
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

-- -------------------------------------------------------------
-- consultor_lotes: asignación exclusiva de lote a consultor
-- -------------------------------------------------------------
CREATE TABLE consultor_lotes (
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
    ADD CONSTRAINT fk_hr_consultor_lote
    FOREIGN KEY (consultor_lote_id) REFERENCES consultor_lotes(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- -------------------------------------------------------------
-- visitas_registro / visitas_observaciones: historial operativo
-- -------------------------------------------------------------
CREATE TABLE visitas_registro (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    hoja_ruta_id      BIGINT UNSIGNED   NOT NULL,
    domicilio_id      INT UNSIGNED      NOT NULL,
    visitado          TINYINT(1)        NOT NULL DEFAULT 0,
    documento_firmado TINYINT(1)        NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED      NOT NULL,
    created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

CREATE TABLE visitas_observaciones (
    id                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    visita_registro_id BIGINT UNSIGNED   NOT NULL,
    observacion        VARCHAR(255)      NOT NULL,
    created_by         INT UNSIGNED      NOT NULL,
    created_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_vo_visita_created (visita_registro_id, created_at),
    CONSTRAINT fk_vo_visita
        FOREIGN KEY (visita_registro_id) REFERENCES visitas_registro(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vo_created_by
        FOREIGN KEY (created_by) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- importaciones: auditoría de archivos Excel importados
-- -------------------------------------------------------------
CREATE TABLE importaciones (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id       INT UNSIGNED    NOT NULL,
    nombre_archivo   VARCHAR(255)    NOT NULL,
    total_registros  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    errores          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_usuario (usuario_id),
    CONSTRAINT fk_importacion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- llm_sugerencias: auditoria de decisiones sugeridas por LLM
-- -------------------------------------------------------------
CREATE TABLE llm_sugerencias (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    jornada_id        INT UNSIGNED      NOT NULL,
    usuario_id        INT UNSIGNED      NOT NULL,
    proveedor         VARCHAR(30)       NOT NULL,
    modelo            VARCHAR(80)       NOT NULL,
    objetivo          VARCHAR(60)       NOT NULL,
    input_json        JSON              NOT NULL,
    output_json       JSON              NOT NULL,
    aplicado          TINYINT(1)        NOT NULL DEFAULT 0,
    prompt_tokens     INT UNSIGNED      DEFAULT NULL,
    completion_tokens INT UNSIGNED      DEFAULT NULL,
    total_tokens      INT UNSIGNED      DEFAULT NULL,
    latency_ms        INT UNSIGNED      DEFAULT NULL,
    created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_jornada (jornada_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_objetivo (objetivo),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_llm_jornada
        FOREIGN KEY (jornada_id) REFERENCES jornadas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_llm_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- usuario admin inicial (password: Admin1234! → cambiar en producción)
-- hash generado con password_hash('Admin1234!', PASSWORD_BCRYPT)
-- -------------------------------------------------------------
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@kyz.local', '$2b$12$tcaOU8hDaqZKrLyqJ4/sju54/cU7cgPlAB3vn9mkcVskTCFe25oUe', 'admin');
