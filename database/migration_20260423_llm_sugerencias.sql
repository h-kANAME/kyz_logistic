-- Agregar auditoria de sugerencias LLM a una base existente
USE kyz_logistic;

CREATE TABLE IF NOT EXISTS llm_sugerencias (
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
