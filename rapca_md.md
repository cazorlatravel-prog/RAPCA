# RAPCA - Documentacion completa del proyecto

## Que es RAPCA
**Registro y Analisis de Puntos de Control y Actuaciones.** Aplicacion web PWA para inspeccion de infraestructuras forestales con camara, GPS y reportes PDF. Orientada a trabajo de campo en gestion forestal INFOCA (Andalucia, Espana).

Dominio: **rapca.app** | Hosting: **Hostinger** | BD: **u919343704_rapcajaen**

---

## Stack tecnico
- **Backend**: PHP 8.1+ (vanilla, sin framework)
- **Base de datos**: MySQL 8 (charset utf8mb4)
- **Frontend**: HTML/CSS/JS vanilla (PWA con service worker)
- **Fotos**: Cloudinary (subida via API REST con cURL, cloud name: `drnqs1jwl`)
- **PDFs**: dompdf 2.x (via Composer)
- **Excel import**: phpoffice/phpspreadsheet 5.x (via Composer)
- **Excel export**: SheetJS (CDN, client-side XLSX generation)
- **EXIF metadata**: piexifjs (CDN, client-side EXIF embedding)
- **Configuracion**: archivo `.env` parseado manualmente en `includes/config.php`
- **Offline**: IndexedDB para cola de subidas y precarga de fotos

---

## Estructura del proyecto

```
RAPCA/
├── CLAUDE.md                 # Instrucciones para IA
├── rapca_md.md               # Este archivo - documentacion completa
├── composer.json             # Dependencias PHP (dompdf, phpspreadsheet)
├── composer.lock
├── index.html                # Landing page (infocampo)
├── .env                      # Variables de entorno (NO se commitea)
│
├── database/
│   ├── schema.sql            # Esquema completo de la BD (8 tablas + 1 vista)
│   └── migrate.php           # Script de migracion
│
├── includes/
│   ├── config.php            # Carga .env, constantes, conexion PDO (getDB())
│   ├── auth.php              # Autenticacion, sesiones, CSRF, roles
│   └── cloudinary_helper.php # Subida de fotos a Cloudinary (API REST + cURL)
│
├── admin/
│   ├── dashboard.php         # Panel de administracion (Resumen/Campos/Infras/Operadores/Mapa)
│   └── api/
│       ├── campos.php        # CRUD campos formulario dinamicos
│       ├── capas_kml.php     # CRUD capas KML para mapa
│       ├── infraestructuras.php  # CRUD infraestructuras (admin)
│       └── operadores.php    # CRUD operadores y asignacion de infras
│
└── public/
    ├── login.php             # Login de usuarios
    ├── operador.php          # Panel principal del operador de campo (SPA-like)
    ├── subir.php             # Endpoint de subida de fotos (POST)
    ├── manifest.json         # PWA manifest
    ├── sw.js                 # Service worker para offline
    │
    ├── api/
    │   ├── campos.php               # GET: campos activos
    │   ├── capas_kml.php            # GET: capas KML activas
    │   ├── export_pdf.php           # GET: genera PDF (individual o todos)
    │   ├── fotos_comparativas.php   # GET: fotos comparativas por infra
    │   ├── import_infraestructuras.php  # POST: importar infras desde Excel/CSV
    │   ├── infraestructuras.php     # GET: buscar/filtrar infras | POST: crear
    │   ├── registros_mapa.php       # GET: registros para mapa | POST: guardar formulario
    │   ├── ultima_foto.php          # GET: ultima foto de una infra
    │   ├── unidades_obra.php        # (deprecated)
    │   └── visitas.php              # GET: visitas agrupadas | POST: editar registro
    │
    ├── css/
    │   ├── camera.css               # Estilos del visor de camara
    │   └── operador.css             # Estilos del panel operador + formularios
    │
    ├── js/
    │   ├── operador.js              # Logica principal del panel operador
    │   ├── forms.js                 # Formularios VP/EL/EI de recogida de datos
    │   └── offline.js               # Modulo offline (IndexedDB, sync queue)
    │
    └── icons/
        └── generate_icons.php       # Generador de iconos PWA
```

---

## Variables de entorno (.env)

```env
DB_HOST=localhost
DB_NAME=u919343704_rapcajaen
DB_USER=u919343704_datosrapca
DB_PASS=***

CLOUDINARY_CLOUD_NAME=drnqs1jwl
CLOUDINARY_API_KEY=587983846793923
CLOUDINARY_API_SECRET=***

APP_URL=https://rapca.app
```

**IMPORTANTE**: El `.env` nunca se commitea (esta en .gitignore). En produccion (Hostinger) se configura manualmente.

---

## Base de datos - Esquema

### Tablas principales

#### 1. `usuarios`
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| id | INT UNSIGNED AI | PK |
| nombre | VARCHAR(150) | Nombre completo |
| email | VARCHAR(255) | Unico |
| password | VARCHAR(255) | Hash bcrypt (cost 12) |
| rol | ENUM('superadmin','admin','operador') | Rol del usuario |
| activo | TINYINT(1) | 1=activo |
| ultimo_login | DATETIME | Ultimo acceso |

**Roles:**
- `superadmin`: Control total (lectura + escritura + gestion de usuarios/infras)
- `admin`: Solo lectura (ve todo pero no puede modificar datos)
- `operador`: Trabajo de campo (solo sus infraestructuras asignadas)

#### 2. `infraestructuras`
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| id | INT UNSIGNED AI | PK |
| nombre | VARCHAR(250) | Nombre de la infraestructura |
| provincia | VARCHAR(150) | Provincia |
| id_zona | VARCHAR(50) | Zona INFOCA |
| id_unidad | VARCHAR(50) | Unidad INFOCA |
| cod_infoca | VARCHAR(50) | Codigo INFOCA |
| superficie | DECIMAL(12,2) | Hectareas |
| municipio | VARCHAR(150) | Municipio |
| monte | VARCHAR(200) | Nombre del monte |
| cod_monte | VARCHAR(50) | Codigo del monte |
| pendiente | VARCHAR(100) | Pendiente del terreno |
| distancia_aprisco | VARCHAR(100) | Distancia al aprisco |
| vegetacion | VARCHAR(200) | Tipo de vegetacion |
| tipo_contrato | VARCHAR(100) | Tipo de contrato |
| parque | VARCHAR(200) | Parque natural |
| pago_max | DECIMAL(10,2) | Pago maximo |
| desbroce | VARCHAR(200) | Tipo de desbroce |
| observaciones | TEXT | Notas |
| lat_teorica / lon_teorica | DECIMAL(10,7) | Coordenadas de referencia |
| activa | TINYINT(1) | 1=activa |

#### 3. `operario_infraestructura`
Tabla pivote: que operarios tienen acceso a que infraestructuras.
- `usuario_id` → `usuarios.id`
- `infraestructura_id` → `infraestructuras.id`

#### 4. `registros`
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| id | INT UNSIGNED AI | PK |
| infra_id | INT UNSIGNED | FK infraestructuras |
| usuario_id | INT UNSIGNED | FK usuarios |
| fecha | DATETIME | Timestamp del registro |
| lat_real / lon_real | DECIMAL(10,7) | GPS real del operador |
| url_cloudinary | VARCHAR(512) | URL de la imagen en Cloudinary |
| datos_tecnicos | JSON | Datos del formulario VP/EL/EI (flexible) |
| estado_incidencia | ENUM('vp','ev') | VP=Visita Previa, EV=Evaluacion |
| observaciones | TEXT | Texto libre |
| tipo_foto | ENUM('aleatorio','comparativo') | Tipo de captura |
| secuencia_comparativa | INT UNSIGNED | Numero de secuencia W1, W2... |
| nombre_archivo | VARCHAR(255) | Nombre del archivo |

#### 5. `campos_formulario`
Campos dinamicos configurables desde admin. Tipos: texto, numero, select, checkbox, textarea, fecha.

#### 6. `valores_campo`
Valores de campos dinamicos por registro. FK a `registros` y `campos_formulario`.

#### 7. `subidas_fallidas`
Log de subidas que fallaron en Cloudinary. Campos: motivo_error, intentos, resuelta.

#### 8. `capas_kml`
Capas KML para superponer en el mapa. Campos: nombre, contenido_kml (LONGTEXT), color, activa.

### Vista
- `v_registros_completos`: JOIN de registros + infraestructuras + usuarios con todos los campos relevantes.

---

## Autenticacion y sesiones (`includes/auth.php`)

- Sesiones PHP nativas
- `login($email, $password)` → verifica bcrypt, crea session
- `$_SESSION` keys: `user_id`, `user_name`, `user_email`, `user_rol`
- CSRF: token en session, validacion via POST o header `X-CSRF-TOKEN`
- Funciones de control de acceso:
  - `isLoggedIn()`, `currentUser()`, `requireAuth()`, `requireRole()`
  - `isSuperAdmin()`, `isAdmin()`, `isReadOnly()`, `canWrite()`
  - `getOperadorInfraIds($userId)` → IDs de infras accesibles

---

## API Endpoints

### Publicos (`public/api/`)

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `infraestructuras.php` | GET | Buscar/filtrar infraestructuras (?q=, ?zona=, ?municipio=, ?usuario_id=, ?action=zonas/municipios) |
| `infraestructuras.php` | POST | Crear nueva infraestructura |
| `registros_mapa.php` | GET | Registros para mapa (?usuario_id=, ?infra_id=, ?tipo_foto=, ?limit=) |
| `registros_mapa.php` | POST | Guardar formulario de campo (JSON body con datos_tecnicos) |
| `fotos_comparativas.php` | GET | Fotos comparativas por infra (?infra_id=) |
| `ultima_foto.php` | GET | Ultima foto de una infra (?infra_id=) |
| `visitas.php` | GET | Visitas agrupadas por infra/fecha (?usuario_id=) |
| `visitas.php` | POST | Editar registro (action=edit_record) |
| `campos.php` | GET | Campos dinamicos activos |
| `capas_kml.php` | GET | Capas KML activas |
| `export_pdf.php` | GET | Generar PDF (?registro_id=&usuario_id= o ?all=1&usuario_id=&admin=1) |
| `import_infraestructuras.php` | POST | Importar infras desde Excel/CSV |

### Subida de fotos (`public/subir.php`)

| Campo POST | Tipo | Requerido | Descripcion |
|-----------|------|-----------|-------------|
| imagen | FILE | Si | Foto JPEG/PNG/WebP |
| infra_id | int | Si | ID infraestructura |
| usuario_id | int | Si | ID operador |
| lat_real | float | Si | Latitud GPS |
| lon_real | float | Si | Longitud GPS |
| estado_incidencia | string | No | 'vp' o 'ev' (default: vp) |
| tipo_foto | string | No | 'aleatorio' o 'comparativo' |
| secuencia_comparativa | int | No | Numero de secuencia |
| nombre_archivo | string | No | Nombre del archivo |
| datos_tecnicos | JSON string | No | Datos tecnicos en JSON |
| observaciones | string | No | Texto libre |

**Flujo de subida**:
1. Valida campos y archivo
2. Si Cloudinary esta configurado → sube via API REST (cURL, firma SHA-256)
3. Si Cloudinary falla → guarda localmente en `public/uploads/` + registra en `subidas_fallidas`
4. Si Cloudinary no configurado → guarda localmente
5. Inserta registro en BD con URL (Cloudinary o local)
6. Responde JSON `{ok: true, registro_id, url_imagen, warning?}`

### Admin API (`admin/api/`)

| Endpoint | Metodos | Descripcion |
|----------|---------|-------------|
| `campos.php` | GET/POST/PUT/DELETE | CRUD campos formulario |
| `capas_kml.php` | GET/POST/PUT/DELETE | CRUD capas KML |
| `infraestructuras.php` | GET/POST/PUT/DELETE | CRUD infraestructuras |
| `operadores.php` | GET/POST/PUT/DELETE | CRUD operadores + asignacion infras |

---

## Panel del operador (`public/operador.php` + `operador.js`)

### Pantallas (screens)
El operador funciona como una SPA con multiples pantallas:

1. **ficha** — Pantalla principal tras seleccionar infraestructura
   - Info de la infra, galeria de fotos, contadores
   - Botones: Foto aleatoria, Foto comparativa, Formularios VP/EL/EI
   - Observaciones generales

2. **camera** — Visor de camara
   - Captura de fotos con geolocalizacion GPS
   - Sistema de **ghosting**: superpone foto anterior comparativa con slider de transparencia (0-100%)
   - Genera nombre de archivo con formato: `CODINFOCA_W[seq]_YYYYMMDD_HHmmss`

3. **preview** — Vista previa de la foto capturada
   - Confirmar o descartar foto
   - Guarda en galeria del dispositivo con metadatos EXIF (GPS, fecha)

4. **form-vp** — Formulario Visita Previa
   - Pastoreo: 3 puntos con nota 0-5
   - Estado de observacion: Senal, Veredas, Cagarrutas (nota 0-5)
   - Fotos y observaciones

5. **form-el** — Formulario Evaluacion Ligera
   - Pastoreo: 3 puntos con nota 0-5
   - Herbaceas: 7 puntos con nota 0-5
   - Matorralizacion: 2 parcelas con cobertura (%), altura (cm), especie
   - Calculo automatico de volumen (m3/ha)

6. **form-ei** — Formulario Evaluacion Intensa
   - 3 transectos (T1, T2, T3) con progresion
   - Plantas: 10 slots con autocomplete de 32 especies + 10 notas cada una
   - Palatables: 3 slots con 15 notas cada una
   - Pastoreo: 3 puntos
   - Herbaceas: 7 puntos
   - Matorralizacion: 2 parcelas
   - Estadisticas en tiempo real (media, max, min)

7. **panel** — Panel de registros
   - Lista de todos los registros con filtros
   - Admin/SuperAdmin ven TODOS los registros de todos los usuarios
   - Exportar a CSV, XLSX (SheetJS), PDF (dompdf)
   - Detalle de registro individual

8. **mapa** — Mapa de registros
   - Leaflet + OpenStreetMap
   - Marcadores por registro con popups
   - Capas KML superpuestas

### Funcionalidades clave del operador

- **Modo aleatorio**: Fotos sin secuencia, para documentacion general
- **Modo comparativo**: Fotos en secuencia W1, W2... para seguimiento temporal
- **Ghosting**: Superpone foto anterior con opacidad configurable (slider)
- **GPS**: Coordenadas ETRS89, mostradas en tiempo real
- **Offline**: IndexedDB para cola de subidas cuando no hay red
- **EXIF**: Embebe metadatos GPS y fecha en las fotos guardadas al dispositivo
- **Exportacion**:
  - CSV: descarga directa
  - XLSX: generado con SheetJS en el navegador
  - PDF: generado con dompdf en el servidor

---

## Formularios de recogida de datos (`public/js/forms.js`)

### VP (Visita Previa)
```json
{
  "tipo_formulario": "vp",
  "pastoreo": {"p1": 3, "p2": 4, "p3": 2},
  "observacion": {"senal": 2, "veredas": 3, "cagarrutas": 1},
  "fotos": {"general": [], "w1": [], "w2": []},
  "observaciones_form": "texto libre"
}
```

### EL (Evaluacion Ligera)
```json
{
  "tipo_formulario": "el",
  "pastoreo": {"p1": 3, "p2": 4, "p3": 2},
  "herbaceas": {"h1": 2, "h2": 3, ..., "h7": 1},
  "matorralizacion": {
    "parcela1": {"cobertura": 60, "altura": 120, "especie": "Cistus sp."},
    "parcela2": {"cobertura": 40, "altura": 80, "especie": "Rosmarinus officinalis"}
  },
  "fotos": {...},
  "observaciones_form": "texto libre"
}
```

### EI (Evaluacion Intensa)
```json
{
  "tipo_formulario": "ei",
  "transecto": 1,
  "plantas": [
    {"especie": "Quercus ilex", "notas": [3, 2, 4, 1, 3, 2, 4, 3, 2, 1]},
    ...
  ],
  "palatables": [
    {"especie": "Thymus sp.", "notas": [2, 3, 1, 4, 2, ...]},
    ...
  ],
  "pastoreo": {"p1": 3, "p2": 4, "p3": 2},
  "herbaceas": {"h1": 2, ..., "h7": 1},
  "matorralizacion": {...},
  "fotos": {...},
  "observaciones_form": "texto libre"
}
```

Los datos se guardan en el campo `datos_tecnicos` (JSON) de la tabla `registros`, con `estado_incidencia = 'vp'` o `'ev'`.

### Especies con autocomplete (32)
Arbutus unedo, Asparagus acutifolius, Chamaerops humilis, Cistus sp., Crataegus monogyna, Cytisus sp., Daphne gnidium, Dittrichia viscosa, Foeniculum vulgare, Genista sp., Halimium sp., Helichrysum stoechas, Juncus spp., Juniperus sp., Lavandula latifolia, Myrtus communis, Olea europaea var. sylvestris, Phillyrea angustifolia, Phlomis purpurea, Pistacia lentiscus, Quercus coccifera, Quercus ilex, Quercus sp., Retama sphaerocarpa, Rhamnus sp., Rosa sp., Rosmarinus officinalis, Rubus ulmifolius, Salvia rosmarinus, Spartium junceum, Thymus sp., Ulex sp.

---

## Modulo offline (`public/js/offline.js`)

- **IndexedDB**: base `rapca_offline`, version 1
- **Stores**:
  - `upload_queue`: Fotos pendientes de subir (blob + metadata)
  - `photo_cache`: Fotos precargadas para ghosting sin red
- **Sincronizacion**: automatica cuando vuelve la conexion
- **Callbacks**: `onStatusChange`, `onQueueChange`, `onSyncProgress`, `onSyncComplete`

---

## Cloudinary - Subida de imagenes (`includes/cloudinary_helper.php`)

- **Cloud name**: `drnqs1jwl`
- **Endpoint**: `https://api.cloudinary.com/v1_1/{cloud}/image/upload`
- **Firma**: SHA-256 con sorted params + API secret
- **Carpetas**: `rapca/aleatorias`, `rapca/comparativas`
- **Fallback**: si falla, guarda en `public/uploads/` y registra en `subidas_fallidas`
- **Verificacion**: `isConfigured()` comprueba que las credenciales no estan vacias ni son placeholders

---

## Panel de administracion (`admin/dashboard.php`)

Solo accesible por `superadmin` y `admin`. Tabs:

1. **Resumen**: Metricas (total infras, registros, operadores, registros hoy/semana, fotos alea/comp, VP/EV, subidas fallidas). Ultimos 20 registros.
2. **Campos**: CRUD de campos dinamicos del formulario.
3. **Infraestructuras**: CRUD completo + importacion masiva desde Excel/CSV.
4. **Operadores**: CRUD de usuarios operadores + asignacion de infraestructuras.
5. **Mapa**: Visualizacion de todas las infraestructuras y registros + gestion de capas KML.

**Permisos de escritura**:
- `superadmin`: puede crear, editar y eliminar todo
- `admin`: solo lectura (ve todo pero los botones de accion estan deshabilitados)

---

## Convenios de desarrollo

- No usar frameworks JS ni CSS (todo vanilla)
- APIs devuelven JSON, endpoints en `public/api/`
- Autenticacion basada en sesiones PHP
- Fotos se suben a Cloudinary, nunca al servidor local (excepto fallback)
- El `.env` nunca se commitea (esta en .gitignore)
- CDN externas usadas: SheetJS, piexifjs, Leaflet, Bootstrap Icons
- Formularios de campo guardan datos en JSON dentro de `datos_tecnicos`
- GPS en formato ETRS89 (decimal degrees)

---

## Usuarios de prueba (seed en schema.sql)

| Email | Password | Rol |
|-------|----------|-----|
| rapcajaen@gmail.com | Gallito9431 | superadmin |
| admin@rapca.app | (mismo hash) | admin |
| operador1@rapca.app a operador6@rapca.app | (mismo hash) | operador |

---

## Dependencias Composer

```json
{
  "require": {
    "php": ">=8.1",
    "dompdf/dompdf": "^2.0",
    "ext-pdo": "*",
    "ext-curl": "*",
    "ext-json": "*",
    "ext-fileinfo": "*",
    "phpoffice/phpspreadsheet": "^5.5"
  }
}
```

---

## Notas importantes para desarrollo

1. **Cloudinary cloud name correcto**: `drnqs1jwl` (no `rapca`)
2. **Subidas silenciosas**: `subir.php` devuelve `ok: true` incluso si Cloudinary falla (guarda localmente). Revisar campo `warning` en la respuesta.
3. **Formularios VP/EL/EI**: Los datos se almacenan en `datos_tecnicos` JSON. EL y EI usan `estado_incidencia = 'ev'`, VP usa `'vp'`. El campo `tipo_formulario` dentro del JSON distingue EL de EI.
4. **Admin export**: Admin/SuperAdmin pueden exportar TODOS los registros (no solo los suyos). Pasar `&admin=1` al endpoint de PDF.
5. **Ghosting**: El slider de opacidad va de 0% a 100%. La imagen ghost se carga desde fotos comparativas previas de la misma infraestructura.
6. **Offline**: IndexedDB almacena fotos pendientes. La sincronizacion es automatica al recuperar conexion.
7. **EXIF**: Las fotos guardadas al dispositivo llevan metadatos GPS (DMS) y fecha/hora embebidos via piexifjs.
