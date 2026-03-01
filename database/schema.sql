-- ============================================================
-- RAPCA - Registro y Análisis de Puntos de Control y Actuaciones
-- Schema MySQL 8.0+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 1. USUARIOS
--    Roles: admin (gestión total), operador (trabajo de campo)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(150)    NOT NULL,
    email           VARCHAR(255)    NOT NULL,
    password        VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt',
    rol             ENUM('admin','operador')
                                    NOT NULL DEFAULT 'operador',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    ultimo_login    DATETIME        DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    INDEX idx_usuarios_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. INFRAESTRUCTURAS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS infraestructuras (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(250)    NOT NULL,
    codigo_unico    VARCHAR(50)     NOT NULL COMMENT 'Codigo visible en campo (ej: TORRE-0421)',
    lat_teorica     DECIMAL(10,7)   NOT NULL COMMENT 'Latitud de referencia',
    lon_teorica     DECIMAL(10,7)   NOT NULL COMMENT 'Longitud de referencia',
    tipo            VARCHAR(100)    DEFAULT NULL COMMENT 'Tipo libre: torre, poste, puente...',
    provincia       VARCHAR(100)    DEFAULT NULL,
    municipio       VARCHAR(150)    DEFAULT NULL,
    descripcion     TEXT            DEFAULT NULL,
    activa          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_infra_codigo (codigo_unico),
    INDEX idx_infra_coords (lat_teorica, lon_teorica),
    INDEX idx_infra_provincia (provincia),
    INDEX idx_infra_municipio (provincia, municipio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. OPERARIO_INFRAESTRUCTURA
--    Tabla de acceso: qué operarios pueden ver/usar cada infra
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS operario_infraestructura (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id          INT UNSIGNED    NOT NULL,
    infraestructura_id  INT UNSIGNED    NOT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_infra (usuario_id, infraestructura_id),
    INDEX idx_oi_usuario (usuario_id),
    INDEX idx_oi_infra (infraestructura_id),
    CONSTRAINT fk_oi_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_oi_infra
        FOREIGN KEY (infraestructura_id) REFERENCES infraestructuras (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 4. UNIDADES DE OBRA
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS unidades_obra (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(200)    NOT NULL,
    codigo      VARCHAR(50)     DEFAULT NULL,
    descripcion TEXT            DEFAULT NULL,
    activa      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. REGISTROS (cada inspección / foto de campo)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS registros (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    infra_id                INT UNSIGNED    NOT NULL,
    unidad_obra_id          INT UNSIGNED    DEFAULT NULL,
    usuario_id              INT UNSIGNED    NOT NULL,
    fecha                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lat_real                DECIMAL(10,7)   NOT NULL COMMENT 'Latitud GPS real del operador',
    lon_real                DECIMAL(10,7)   NOT NULL COMMENT 'Longitud GPS real del operador',
    url_cloudinary          VARCHAR(512)    NOT NULL COMMENT 'URL de la imagen en Cloudinary',
    datos_tecnicos          JSON            DEFAULT NULL COMMENT 'Payload libre: temperatura, presion, notas...',
    estado_incidencia       ENUM('antes','durante','despues')
                                            NOT NULL DEFAULT 'antes',
    observaciones           TEXT            DEFAULT NULL,
    tipo_foto               ENUM('aleatorio','comparativo') NOT NULL DEFAULT 'aleatorio',
    secuencia_comparativa   INT UNSIGNED    DEFAULT NULL,
    nombre_archivo          VARCHAR(255)    DEFAULT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_reg_infra (infra_id),
    INDEX idx_reg_usuario (usuario_id),
    INDEX idx_reg_fecha (fecha),
    INDEX idx_reg_estado (estado_incidencia),
    INDEX idx_reg_tipo_foto (tipo_foto),
    INDEX idx_reg_unidad_obra (unidad_obra_id),
    CONSTRAINT fk_reg_infra
        FOREIGN KEY (infra_id) REFERENCES infraestructuras (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reg_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 6. CAMPOS_FORMULARIO (campos dinámicos)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS campos_formulario (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(150)    NOT NULL COMMENT 'Nombre visible del campo',
    slug            VARCHAR(100)    NOT NULL COMMENT 'Identificador unico snake_case',
    tipo            ENUM('texto','numero','select','checkbox','textarea','fecha')
                                    NOT NULL DEFAULT 'texto',
    opciones        JSON            DEFAULT NULL COMMENT 'Para select: ["Opcion A","Opcion B"]',
    obligatorio     TINYINT(1)      NOT NULL DEFAULT 0,
    orden           INT UNSIGNED    NOT NULL DEFAULT 0,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campo_slug (slug),
    INDEX idx_campo_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 7. VALORES_CAMPO (valores de campos dinámicos por registro)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS valores_campo (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    registro_id     INT UNSIGNED    NOT NULL,
    campo_id        INT UNSIGNED    NOT NULL,
    valor           TEXT            DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_valor_registro_campo (registro_id, campo_id),
    INDEX idx_valor_registro (registro_id),
    INDEX idx_valor_campo (campo_id),
    CONSTRAINT fk_valor_registro
        FOREIGN KEY (registro_id) REFERENCES registros (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_valor_campo
        FOREIGN KEY (campo_id) REFERENCES campos_formulario (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 8. SUBIDAS_FALLIDAS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS subidas_fallidas (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED    NOT NULL,
    infra_id            INT UNSIGNED    NOT NULL,
    nombre_archivo      VARCHAR(255),
    estado_incidencia   ENUM('antes','durante','despues') DEFAULT 'antes',
    tipo_foto           ENUM('aleatorio','comparativo') DEFAULT 'aleatorio',
    motivo_error        TEXT,
    intentos            INT UNSIGNED    DEFAULT 1,
    resuelta            TINYINT(1)      DEFAULT 0,
    fecha_fallo         DATETIME        DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion    DATETIME        NULL,
    created_at          DATETIME        DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_resuelta (resuelta),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (infra_id) REFERENCES infraestructuras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 9. CAPAS_KML
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS capas_kml (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(255)    NOT NULL,
    contenido_kml   LONGTEXT        NOT NULL,
    color           VARCHAR(7)      DEFAULT '#8b5cf6',
    activa          TINYINT(1)      DEFAULT 1,
    created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- VISTA: registros con datos completos
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW v_registros_completos AS
SELECT
    r.id            AS registro_id,
    r.fecha,
    r.lat_real,
    r.lon_real,
    r.url_cloudinary,
    r.datos_tecnicos,
    r.estado_incidencia,
    r.observaciones,
    r.tipo_foto,
    r.secuencia_comparativa,
    r.nombre_archivo,
    r.unidad_obra_id,
    i.id            AS infra_id,
    i.nombre        AS infra_nombre,
    i.codigo_unico,
    i.lat_teorica,
    i.lon_teorica,
    i.tipo          AS infra_tipo,
    u.id            AS usuario_id,
    u.nombre        AS usuario_nombre,
    u.email         AS usuario_email
FROM registros r
    INNER JOIN infraestructuras i ON r.infra_id   = i.id
    INNER JOIN usuarios u         ON r.usuario_id = u.id;

-- -----------------------------------------------------------
-- Seed: Administrador por defecto
-- Password: Rapca2024!
-- -----------------------------------------------------------
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo)
VALUES ('Administrador', 'admin@rapca.app',
        '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy',
        'admin', 1);

SET FOREIGN_KEY_CHECKS = 1;
