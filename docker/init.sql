-- ============================================================
-- Forecast System — Esquema de base de datos (MySQL 8.0)
-- ============================================================

CREATE TABLE usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150)  NOT NULL,
    email           VARCHAR(150)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    rol             ENUM('ejecutivo','admin') NOT NULL DEFAULT 'ejecutivo',
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oportunidades (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejecutivo_id    INT UNSIGNED NOT NULL,
    cuenta          VARCHAR(255) NOT NULL,
    nit             VARCHAR(20)  NOT NULL,
    tipo            ENUM('COMPUTO','SERVIDOR','IMPRESION','SOFTWARE','IMAGEN_Y_VIDEO','SERVICIOS','CONECTIVIDAD','COMBINADO') NOT NULL,
    fecha_creacion  DATE          NOT NULL,
    monto           DECIMAL(14,2) NOT NULL,
    estado          ENUM('ES','POC','COC','PF','OTROS') NOT NULL DEFAULT 'ES',
    activa          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_oportunidades_ejecutivo
        FOREIGN KEY (ejecutivo_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_oportunidades_ejecutivo_activa (ejecutivo_id, activa),
    INDEX idx_oportunidades_nit (nit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE metas_mensuales (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejecutivo_id    INT UNSIGNED NOT NULL,
    anio            SMALLINT UNSIGNED NOT NULL,
    mes             TINYINT UNSIGNED NOT NULL,
    monto_meta      DECIMAL(14,2) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_metas_ejecutivo
        FOREIGN KEY (ejecutivo_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT chk_metas_mes CHECK (mes BETWEEN 1 AND 12),
    UNIQUE KEY uq_metas_ejecutivo_periodo (ejecutivo_id, anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reportes_semanales (
    id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ejecutivo_id                    INT UNSIGNED NOT NULL,
    fecha_reporte                   DATE NOT NULL
        COMMENT 'Lunes ISO de la semana calendario que representa el reporte',
    meta_mes                        DECIMAL(14,2) NOT NULL,
    venta_empresas                  DECIMAL(14,2) NOT NULL DEFAULT 0,
    venta_general                   DECIMAL(14,2) NOT NULL DEFAULT 0,
    comentarios                     TEXT NULL,
    total_pipeline_snapshot         DECIMAL(14,2) NOT NULL DEFAULT 0,
    pronostico_ponderado_snapshot   DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reportes_ejecutivo
        FOREIGN KEY (ejecutivo_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT chk_reportes_venta_empresas CHECK (venta_empresas <= venta_general),
    UNIQUE KEY uq_reportes_ejecutivo_semana (ejecutivo_id, fecha_reporte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parametros (
    clave           VARCHAR(50)  PRIMARY KEY,
    valor           VARCHAR(50)  NOT NULL,
    descripcion     VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametros (clave, valor, descripcion) VALUES
    ('pct_conversion_pipeline', '0.30', 'Porcentaje plano aplicado al total del pipeline para el pronóstico ponderado'),
    ('umbral_dias_alta',        '15',   'Días máximos de antigüedad para probabilidad ALTA'),
    ('umbral_dias_baja',        '30',   'Días de antigüedad a partir de los cuales la probabilidad es BAJA'),
    ('umbral_semaforo_bajo',    '0.30', 'Fracción de la meta del mes por debajo de la cual el semáforo es rojo'),
    ('umbral_semaforo_alto',    '0.80', 'Fracción de la meta del mes por encima de la cual el semáforo es verde');
