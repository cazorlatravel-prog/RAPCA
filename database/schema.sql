-- ============================================================
-- RAPCA - Registro y Análisis de Puntos de Control y Actuaciones
-- Schema MySQL 8.0+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 1. USUARIOS
--    Roles: superadmin (control total), admin (solo lectura), operador (campo)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(150)    NOT NULL,
    email           VARCHAR(255)    NOT NULL,
    password        VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt',
    rol             ENUM('superadmin','admin','operador')
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
--    Campos basados en gestión forestal INFOCA
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS infraestructuras (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    provincia           VARCHAR(150)    DEFAULT NULL COMMENT 'Provincia',
    id_zona             VARCHAR(50)     DEFAULT NULL COMMENT 'Identificador de zona',
    id_unidad           VARCHAR(50)     DEFAULT NULL COMMENT 'Identificador de unidad',
    cod_infoca          VARCHAR(50)     DEFAULT NULL COMMENT 'Codigo INFOCA',
    nombre              VARCHAR(250)    NOT NULL,
    superficie          DECIMAL(12,2)   DEFAULT NULL COMMENT 'Superficie en hectareas',
    municipio           VARCHAR(150)    DEFAULT NULL,
    monte               VARCHAR(200)    DEFAULT NULL COMMENT 'Nombre del monte',
    cod_monte           VARCHAR(50)     DEFAULT NULL COMMENT 'Codigo del monte',
    pendiente           VARCHAR(100)    DEFAULT NULL COMMENT 'Pendiente del terreno',
    distancia_aprisco   VARCHAR(100)    DEFAULT NULL COMMENT 'Distancia al aprisco',
    vegetacion          VARCHAR(200)    DEFAULT NULL COMMENT 'Tipo de vegetacion',
    tipo_contrato       VARCHAR(100)    DEFAULT NULL COMMENT 'Tipo de contrato',
    parque              VARCHAR(200)    DEFAULT NULL COMMENT 'Parque natural',
    pago_max            DECIMAL(10,2)   DEFAULT NULL COMMENT 'Pago maximo',
    desbroce            VARCHAR(200)    DEFAULT NULL COMMENT 'Tipo de desbroce',
    observaciones       TEXT            DEFAULT NULL,
    lat_teorica         DECIMAL(10,7)   DEFAULT NULL COMMENT 'Latitud de referencia (opcional)',
    lon_teorica         DECIMAL(10,7)   DEFAULT NULL COMMENT 'Longitud de referencia (opcional)',
    activa              TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_infra_provincia (provincia),
    INDEX idx_infra_zona (id_zona),
    INDEX idx_infra_cod_infoca (cod_infoca),
    INDEX idx_infra_municipio (municipio),
    INDEX idx_infra_coords (lat_teorica, lon_teorica)
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
-- 4. REGISTROS (cada inspección / foto de campo)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS registros (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    infra_id                INT UNSIGNED    NOT NULL,
    usuario_id              INT UNSIGNED    NOT NULL,
    fecha                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lat_real                DECIMAL(10,7)   NOT NULL COMMENT 'Latitud GPS real del operador',
    lon_real                DECIMAL(10,7)   NOT NULL COMMENT 'Longitud GPS real del operador',
    url_cloudinary          VARCHAR(512)    NOT NULL COMMENT 'URL de la imagen en Cloudinary',
    datos_tecnicos          JSON            DEFAULT NULL COMMENT 'Payload libre: temperatura, presion, notas...',
    estado_incidencia       ENUM('vp','ev')
                                            NOT NULL DEFAULT 'vp',
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
    CONSTRAINT fk_reg_infra
        FOREIGN KEY (infra_id) REFERENCES infraestructuras (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reg_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. CAMPOS_FORMULARIO (campos dinámicos)
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
-- 6. VALORES_CAMPO (valores de campos dinámicos por registro)
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
-- 7. SUBIDAS_FALLIDAS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS subidas_fallidas (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED    NOT NULL,
    infra_id            INT UNSIGNED    NOT NULL,
    nombre_archivo      VARCHAR(255),
    estado_incidencia   ENUM('vp','ev') DEFAULT 'vp',
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
-- 8. CAPAS_KML
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
    i.id            AS infra_id,
    i.nombre        AS infra_nombre,
    i.cod_infoca,
    i.lat_teorica,
    i.lon_teorica,
    i.provincia     AS infra_provincia,
    i.municipio     AS infra_municipio,
    u.id            AS usuario_id,
    u.nombre        AS usuario_nombre,
    u.email         AS usuario_email
FROM registros r
    INNER JOIN infraestructuras i ON r.infra_id   = i.id
    INNER JOIN usuarios u         ON r.usuario_id = u.id;

-- -----------------------------------------------------------
-- Seed: Usuarios iniciales
-- -----------------------------------------------------------

-- Super Administrador (rapcajaen@gmail.com / Gallito9431)
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo)
VALUES ('Super Administrador', 'rapcajaen@gmail.com',
        '$2y$12$qc6Hzv1Fi2def71Yd6meEe33rh1b/Drv2d6fKXvqKyJ9m9qTt9JOe',
        'superadmin', 1);

-- Administrador (solo lectura)
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo)
VALUES ('Administrador', 'admin@rapca.app',
        '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy',
        'admin', 1);

-- 6 Operadores de campo
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo) VALUES
('Operador 1', 'operador1@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1),
('Operador 2', 'operador2@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1),
('Operador 3', 'operador3@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1),
('Operador 4', 'operador4@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1),
('Operador 5', 'operador5@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1),
('Operador 6', 'operador6@rapca.app', '$2y$12$LJ3b9FzGO8HnKNQPbQqXkeKdBkYvHZVx5Y1w6xQmR/Ll.hPGXOJOy', 'operador', 1);

SET FOREIGN_KEY_CHECKS = 1;
