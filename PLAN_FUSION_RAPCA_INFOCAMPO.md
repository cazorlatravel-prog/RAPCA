# Plan de Fusión: RAPCA + INFOCAMPO

## Análisis Comparativo

### RAPCA (app actual)
- **Tipo:** PWA standalone (1 archivo HTML + 1 JS + API PHP simple)
- **Usuarios:** Sin autenticación, un solo nivel
- **Datos:** Formularios específicos (VP, EL, EV) para evaluación de pastos
- **Fotos:** Cámara con watermark (brújula, mapa, coordenadas UTM)
- **Almacenamiento:** localStorage + IndexedDB para fotos
- **Backend:** PHP simple con MySQL (CRUD registros + exportar Excel)
- **Offline:** Service Worker + sync manual
- **Exportación:** Excel (.xlsx) + PDF (MiniPDF.js)

### INFOCAMPO (funcionalidades a portar)
- **Multi-tenant (empresas)** con licencias y límites
- **4 roles:** superadmin → admin → supervisor → operador
- **Panel de administración** con dashboard, estadísticas, gráficos
- **Mapa interactivo Leaflet** con todas las fotos geolocalizadas
- **Comparador de fotos** before/after con slider
- **Sistema Ghost** (overlay de foto anterior al hacer nueva foto)
- **Campos dinámicos** configurables por empresa
- **Gestión de infraestructuras** con CRUD completo
- **Unidades de obra** clasificadas
- **Capas KML** importables en el mapa
- **Sistema offline robusto** con IndexedDB (cola de subida + precache de fotos)
- **Subida a Cloudinary** con fallback local
- **Generación de PDF** con DOMPDF (servidor)
- **Exportación CSV** avanzada con filtros
- **Búsqueda por provincia/municipio** con autocompletado
- **CSRF protection** + sesiones seguras + bcrypt

---

## Funcionalidades a Integrar en RAPCA (por prioridad)

### 🔴 PRIORIDAD ALTA

#### 1. Sistema de Autenticación y Roles
**Qué aporta:** Seguridad, control de acceso, trazabilidad
- Login con sesiones PHP
- Roles: admin (gestiona todo) + operador (solo toma datos)
- Cada operador asociado a un admin/empresa
- Protección CSRF en todos los formularios

#### 2. Panel de Administración Web
**Qué aporta:** Los jefes de equipo pueden ver todo desde el ordenador
- Dashboard con estadísticas (registros por tipo, zona, fecha)
- Timeline de inspecciones recientes
- Gestión de usuarios/operadores
- Exportación avanzada con filtros

#### 3. Mapa Interactivo (Leaflet)
**Qué aporta:** Visualizar TODOS los registros geolocalizados
- Marcadores por zona/unidad con colores por tipo (VP/EL/EV)
- Popups con resumen del registro + fotos
- Filtros por operador, tipo, fecha, zona
- Capas KML importables (zonas de estudio, límites, etc.)

#### 4. Subida de Fotos al Servidor (Cloudinary)
**Qué aporta:** Las fotos se guardan en la nube, no solo en local
- Subida automática de fotos a Cloudinary al sincronizar
- URLs permanentes accesibles desde el panel admin
- Fallback a almacenamiento local del servidor

### 🟡 PRIORIDAD MEDIA

#### 5. Sistema Offline Mejorado (IndexedDB)
**Qué aporta:** Más robusto que localStorage
- Cola de subida con reintentos automáticos
- Precarga de datos para trabajar sin cobertura
- Indicadores visuales de estado online/offline
- Sincronización automática al recuperar conexión

#### 6. Comparador de Fotos
**Qué aporta:** Ver evolución temporal de un punto
- Slider before/after entre visitas de la misma unidad
- Agrupación por fecha y waypoint
- Overlay ghost en cámara (foto anterior transparente)

#### 7. Generación de Informes PDF (servidor)
**Qué aporta:** PDFs profesionales con gráficos
- DOMPDF genera informes completos en servidor
- Incluye fotos de Cloudinary (URLs reales)
- Más fiable que el MiniPDF.js actual del cliente

#### 8. Gestión de Zonas/Unidades como Entidades
**Qué aporta:** CRUD de zonas y unidades, no solo texto libre
- Tabla de zonas con coordenadas GPS
- Tabla de unidades asignadas a zonas
- Autocompletado al seleccionar en la app
- Historial de visitas por unidad

### 🟢 PRIORIDAD BAJA

#### 9. Campos Dinámicos
**Qué aporta:** El admin puede añadir campos extra sin tocar código
- Constructor visual de campos (texto, número, select, etc.)
- Campos diferentes por tipo de visita o por proyecto

#### 10. Multi-Empresa / Multi-Proyecto
**Qué aporta:** Separación de datos por proyecto/campaña
- Cada proyecto tiene sus zonas, operadores y datos
- Un admin puede gestionar varios proyectos

---

## Arquitectura Propuesta (fusionada)

```
rapca-v2/
├── index.html              → Landing / marketing
├── composer.json            → DOMPDF + dependencias PHP
├── .env                     → Config BD + Cloudinary
│
├── admin/                   → Panel de administración (web)
│   ├── login.php
│   ├── dashboard.php        → Estadísticas VP/EL/EV
│   ├── registros.php        → Timeline de todos los registros
│   ├── mapa.php             → Mapa Leaflet con fotos
│   ├── comparador.php       → Before/after por unidad
│   ├── usuarios.php         → CRUD operadores
│   ├── zonas.php            → CRUD zonas y unidades
│   ├── importar_kml.php     → Importar capas KML
│   ├── exportar_csv.php     → Export con filtros
│   ├── generar_pdf.php      → Informes PDF servidor
│   └── includes/header.php
│
├── public/                  → App operador (PWA móvil)
│   ├── login.php            → Login operador
│   ├── app.php              → App principal (antes index.html)
│   ├── subir.php            → Upload fotos (Cloudinary)
│   ├── sw.js                → Service Worker
│   ├── manifest.json
│   ├── api/
│   │   ├── registros.php    → CRUD registros (ampliado)
│   │   ├── zonas.php        → Zonas/unidades del operador
│   │   ├── fotos_comp.php   → Fotos comparativas anteriores
│   │   └── sync.php         → Sincronización batch
│   ├── css/
│   │   └── app.css          → Estilos (extraídos del HTML)
│   └── js/
│       ├── app.js           → Lógica principal (refactorizado)
│       ├── camera.js         → Cámara + ghost + watermark
│       ├── offline.js        → IndexedDB + cola sync
│       └── minipdf.js        → PDF local (respaldo)
│
├── includes/                → PHP compartido
│   ├── config.php           → Conexión BD + env
│   ├── auth.php             → Sesiones + roles + CSRF
│   └── cloudinary.php       → Helper Cloudinary
│
└── database/
    ├── schema.sql           → Schema completo fusionado
    └── migrate.php          → Migraciones
```

## Schema de Base de Datos (fusionado)

```sql
-- Empresas/Proyectos
CREATE TABLE empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    licencia_fin DATE,
    max_usuarios INT DEFAULT 10,
    activa TINYINT(1) DEFAULT 1
);

-- Usuarios con roles
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150),
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    rol ENUM('admin','supervisor','operador') DEFAULT 'operador',
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

-- Zonas de estudio
CREATE TABLE zonas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100),
    codigo VARCHAR(50),
    lat_centro DECIMAL(10,7),
    lon_centro DECIMAL(10,7),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

-- Unidades dentro de zonas
CREATE TABLE unidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zona_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    FOREIGN KEY (zona_id) REFERENCES zonas(id)
);

-- Registros (VP, EL, EV) - estructura actual ampliada
CREATE TABLE registros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED,
    tipo ENUM('VP','EL','EV') NOT NULL,
    fecha DATE NOT NULL,
    zona VARCHAR(100),
    unidad VARCHAR(100),
    transecto VARCHAR(10),
    datos JSON NOT NULL,
    lat_real DECIMAL(10,7),
    lon_real DECIMAL(10,7),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_empresa_tipo (empresa_id, tipo),
    INDEX idx_fecha (fecha)
);

-- Fotos vinculadas a registros
CREATE TABLE fotos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registro_id BIGINT UNSIGNED,
    empresa_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(100),
    tipo ENUM('general','W1','W2') DEFAULT 'general',
    url_cloudinary VARCHAR(512),
    url_local VARCHAR(512),
    lat DECIMAL(10,7),
    lon DECIMAL(10,7),
    heading INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registro_id) REFERENCES registros(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

-- Capas KML
CREATE TABLE capas_kml (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255),
    contenido_kml LONGTEXT,
    color VARCHAR(7) DEFAULT '#8b5cf6',
    activa TINYINT(1) DEFAULT 1,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);
```

---

## Cómo Ejecutar con Claude Code

### Paso 1: Preparar el proyecto
```bash
mkdir rapca-v2
cd rapca-v2
cp -r /ruta/rapca-db/* .
git init
git remote add origin git@github.com:tu-usuario/rapca-v2.git
```

### Paso 2: Dar contexto a Claude Code
Crea un archivo `CLAUDE.md` en la raíz con este contenido (copia lo que necesites de este plan) para que Claude Code entienda el proyecto.

### Paso 3: Pedir la fusión por fases
Usa prompts como estos, UNO POR UNO, no todo de golpe:

**Fase 1 - Base:**
> "Refactoriza la estructura de archivos según el plan. Crea las carpetas admin/, public/, includes/, database/. Mueve los archivos actuales a su lugar. Implementa includes/config.php con .env y includes/auth.php con sesiones y roles."

**Fase 2 - Autenticación:**
> "Implementa el sistema de login para admin y operador. Crea admin/login.php y public/login.php. Protege todas las rutas con requireRole(). Crea el schema SQL con las tablas usuarios y empresas."

**Fase 3 - Panel Admin:**
> "Crea el dashboard de administración (admin/dashboard.php) con estadísticas de registros VP/EL/EV, operadores activos, y actividad reciente. Usa Bootstrap 5."

**Fase 4 - Mapa:**
> "Añade admin/mapa.php con Leaflet mostrando todos los registros geolocalizados. Incluye filtros por tipo, zona y fecha. Implementa capas KML."

**Fase 5 - Fotos en nube:**
> "Implementa subida de fotos a Cloudinary en public/subir.php. Modifica app.js para enviar fotos al sincronizar. Añade la tabla fotos a la BD."

**Fase 6 - Offline mejorado:**
> "Reescribe el sistema offline usando IndexedDB con cola de subida y reintentos automáticos, basándote en el patrón de INFOCAMPO/offline.js."

---

## Notas Importantes

- **No intentes hacer todo de golpe.** Cada fase es un commit independiente. Prueba que funciona antes de pasar a la siguiente.
- **La app de operador (formularios VP/EL/EV) NO cambia** en su lógica de negocio. Solo se mejora la infraestructura alrededor.
- **Mantén la compatibilidad** con los datos existentes en localStorage durante la transición.
- **Las fotos siguen descargándose localmente** (como ahora), pero ADEMÁS se suben al servidor.
- **El watermark RAPCA EMA se mantiene** tal cual está en la app actual.
