# Forecast System — Diseño

**Fecha:** 2026-06-19
**Origen:** Digitalización del reporte semanal en Excel que cada ejecutivo corporativo de Compulago llena a mano (`FORECAST CARLOS MONTES 15 DE JUN.xlsx`).

## 1. Contexto y problema

Cada ejecutivo corporativo de Compulago lleva su gestión de ventas en un Excel individual (una hoja por ejecutivo, derivada de un libro maestro con hojas `<NOMBRE>` + una hoja `KPI` consolidada). Semanalmente registra a mano:

- La meta de venta del mes.
- La venta ya cerrada esa semana (separada en Empresas / Otros).
- Comentarios de seguimiento.
- Su pipeline de oportunidades de negocio abiertas (cuenta, NIT, tipo de producto, fecha, monto, estado).

El Excel calcula automáticamente: días de antigüedad de cada oportunidad, una probabilidad ALTA/MEDIA/BAJA por antigüedad, el total del pipeline, un pronóstico ponderado (30% plano del total) y un semáforo (rojo/ámbar/verde) comparando ese pronóstico contra la meta del mes.

El proceso es 100% manual, sin histórico consultable, sin vista consolidada fácil de todos los ejecutivos, y propenso a errores de copiado/fórmulas rotas (de hecho el archivo analizado ya tenía 2 de 3 gráficos rotos por referencias externas perdidas).

## 2. Hallazgos del análisis del Excel

| Sección Excel | Contenido | Notas |
|---|---|---|
| `META MES` (C1) | Meta de venta del mes, en pesos | Digitada a mano |
| Umbrales D1/E1/F1 | 30%, 40%, 80% de la meta | **E1 (40%) es código huérfano, no se usa en ningún cálculo ni semáforo visible — se confirma con el usuario que se omite.** El semáforo real usa bandas 0-30% / 31-80% / 81%+ |
| `VENTA SEMANAL` (filas 5-9) | EMPRESAS (digitado), OTROS (= GENERAL − EMPRESAS, calculado), GENERAL (digitado), PARTICIPACION % | Alimenta un gráfico de pastel |
| `COMENTARIOS` (filas 11-17) | Texto libre | Bitácora semanal cualitativa |
| `OPORTUNIDADES DE NEGOCIO` (filas 23-54) | Pipeline: NUMERO, CUENTA, ID (NIT), TIPO, FECHA, MONTO, DIAS, PROBABILIDAD, ESTADO | Confirmado con el usuario: las oportunidades **persisten** semana a semana (no se redigitan), no hay límite de 30 filas, se van agregando debajo y el propio ejecutivo o el admin las retira cuando ya no aplican |
| `DIAS` | `HOY() − FECHA` | **FECHA = fecha de creación de la oportunidad** (confirmado con el usuario), no fecha de último contacto ni fecha estimada de cierre |
| `PROBABILIDAD` | ALTA ≤15 días, MEDIA 16-30 días, BAJA >30 días | Basada solo en antigüedad, no en el ESTADO real del negocio |
| `ESTADO` | Códigos `ES`, `COC`, `POC`, `PF`, `OTROS` | Significado confirmado con el usuario (ver tabla abajo). Es puramente informativo en el Excel: no afecta ningún cálculo |
| Totales (filas 57-58) | `H57` = SUBTOTAL del pipeline. `H58` = `H57 × 30%`, etiquetado "PROBABILIDAD" | Pronóstico ponderado plano, no usa la probabilidad individual de cada fila |
| Semáforo en `H58` | Formato condicional: <30% meta = rojo (`FF0000`), 30-80% = ámbar (`FFC000`), >80% = verde (`00B050`) | Colores exactos tomados del archivo |
| Vínculo externo roto | Referencia a libro maestro con hojas `CARLOS` y `KPI` | Confirma que el Excel analizado es una hoja exportada de un libro mayor con una hoja por ejecutivo + un consolidado |

**Significado confirmado de ESTADO:**

| Código | Significado |
|---|---|
| `ES` | ESTUDIO — cotización enviada, cliente la está revisando |
| `POC` | POR ORDEN DE COMPRA — fuimos seleccionados, a la espera de que el cliente envíe la orden de compra |
| `COC` | CON ORDEN DE COMPRA — el cliente ya envió la orden de compra, en proceso logístico interno (consecución de producto/insumos) |
| `PF` | POR FACTURAR — todo el proceso se completó (entrega total o parcial) pero aún no se ha facturado |
| `OTROS` | Caso no cubierto por los anteriores |

Esta secuencia (`ES → POC → COC → PF`) es de hecho un pipeline de ventas con etapas claras, aunque hoy el Excel no lo use para calcular probabilidad. **Decisión del usuario: el sistema nuevo replica fielmente la lógica actual (probabilidad por antigüedad + 30% plano), no introduce el cálculo por etapa.** Queda como mejora futura posible, no en este alcance.

## 3. Alcance del sistema digital

Reemplaza el Excel por una aplicación web interna donde cada ejecutivo diligencia su información en formularios nativos (no se sube/parsea el archivo Excel) y un admin/gerente ve todo consolidado.

- **Usuarios esperados:** 2-10 ejecutivos + 1 admin/gerente.
- **Roles:**
  - **Ejecutivo:** gestiona su propio pipeline y su reporte semanal. No ve datos de otros ejecutivos.
  - **Admin/Gerente:** ve el dashboard consolidado de todos los ejecutivos, gestiona usuarios y parámetros del sistema.

## 4. Arquitectura

Sigue el mismo patrón ya usado en los proyectos hermanos de Compulago (`PQR-Plataforma`, `arma-tu-pc-compulago-system`), para mantener consistencia y no introducir un stack nuevo que el equipo deba aprender o mantener aparte:

- **Backend:** PHP 8, estructura propia (`app/`, `config/`, `public/`), sin framework pesado.
- **Base de datos:** MySQL 8.
- **Infraestructura:** Docker Compose (contenedor web + MySQL + phpMyAdmin para administración rápida de datos, igual que en PQR-Plataforma).
- **Autenticación:** sesiones PHP (login/logout), sin SSO — acorde al tamaño del equipo.
- **Control de versiones:** git, con remoto en `https://github.com/marketing454/forecastcompulago.git`, rama `main`.

## 5. Modelo de datos

Dos ciclos de vida distintos que el Excel mezclaba en una sola hoja:

### `usuarios`

- `id`, `nombre`, `email`, `password_hash`, `rol` (`ejecutivo` | `admin`).

### `oportunidades` (pipeline — persiste, vive independiente del ciclo semanal)

- `id`, `ejecutivo_id` (FK a `usuarios`), `cuenta`, `nit`, `tipo` (enum: `COMPUTO`, `SERVIDOR`, `IMPRESION`, `SOFTWARE`, `IMAGEN_Y_VIDEO`, `SERVICIOS`, `CONECTIVIDAD`, `COMBINADO`), `fecha_creacion`, `monto`, `estado` (enum: `ES`, `POC`, `COC`, `PF`, `OTROS`), `activa` (boolean — se desactiva en vez de borrarse físicamente, conserva histórico), `fecha_actualizacion`.
- Campos calculados al leer (no se guardan en BD): `dias = HOY() - fecha_creacion`, `probabilidad` (ALTA/MEDIA/BAJA según `dias`).

### `reportes_semanales` (foto semanal por ejecutivo)

- `id`, `ejecutivo_id` (FK), `fecha_reporte` (semana calendario), `meta_mes`, `venta_empresas`, `venta_general`, `comentarios`.
- Snapshot calculado y guardado al momento de crear el reporte (no recalculado después, para que el histórico de dashboards no cambie retroactivamente aunque el pipeline siga moviéndose):
  - `total_pipeline_snapshot` = suma de `monto` de oportunidades activas del ejecutivo en ese momento.
  - `pronostico_ponderado_snapshot` = `total_pipeline_snapshot × 30%`.
- Restricción: un solo reporte por ejecutivo por semana calendario (si ya existe, se edita en lugar de crear uno nuevo).
- Regla: reportes de semanas pasadas son de **solo lectura** una vez termina la semana — no editables retroactivamente.

### `parametros` (configuración global, clave-valor, editable por admin, no quemada en código)

- `pct_conversion_pipeline` (default 30%, igual al Excel).
- `umbral_dias_alta` (default 15), `umbral_dias_baja` (default 30).
- `umbral_semaforo_bajo` (default 30% de la meta), `umbral_semaforo_alto` (default 80% de la meta).

### `metas_mensuales` (meta de venta por ejecutivo, por mes)

- `id`, `ejecutivo_id` (FK), `anio`, `mes`, `monto_meta`.
- Asignada por el admin; el formulario de reporte semanal prellena `meta_mes` desde aquí (mes en curso), editable puntualmente si hace falta ajustarla esa semana.

## 6. Lógica de negocio (fiel al Excel, centralizada en código)

- `venta_otros = venta_general - venta_empresas` (no se digita, igual que `D8` en el Excel).
- `participacion_empresas = venta_empresas / venta_general`, `participacion_otros = venta_otros / venta_general`.
- `dias` y `probabilidad` por oportunidad: igual a las fórmulas `I25`/`J25` del Excel, usando los umbrales de `parametros` en vez de números fijos.
- `total_pipeline` = suma de `monto` de oportunidades `activa = true` del ejecutivo.
- `pronostico_ponderado = total_pipeline × pct_conversion_pipeline` (igual a `H58`, 30% plano — no pondera por el `estado` individual de cada oportunidad, tal como se decidió).
- Semáforo: comparando `pronostico_ponderado` contra `meta_mes` con las mismas bandas y colores del Excel:
  - `< 30%` de la meta → 🔴 rojo `#FF0000` (probabilidad baja)
  - `30%-80%` de la meta → 🟠 ámbar `#FFC000` (probabilidad media)
  - `> 80%` de la meta → 🟢 verde `#00B050` (probabilidad alta)

## 7. Pantallas

**Vista Ejecutivo**
- *Mi Pipeline*: tabla CRUD de oportunidades (crear, editar monto/estado, marcar inactiva).
- *Mi Reporte Semanal*: formulario (meta del mes prellenada, venta empresas, venta general → calcula en vivo OTROS y participación, comentarios). Un reporte por semana; edita el de la semana actual si ya existe.
- *Mi Dashboard*: semáforo actual, gráfico EMPRESAS/OTROS, total pipeline, histórico de reportes semanales pasados (solo lectura).

**Vista Admin/Gerente**
- *Dashboard consolidado*: semáforo y totales por ejecutivo, ranking, total general de la empresa, tendencia histórica de todos los ejecutivos.
- *Gestión de usuarios*: alta/baja de ejecutivos, asignación de meta mensual por ejecutivo.
- *Parámetros*: edición de `pct_conversion_pipeline`, umbrales de antigüedad y umbrales del semáforo.

## 8. Fuera de alcance (MVP)

No incluido en esta primera versión (se evalúa después si hace falta):
- Exportar reportes a Excel/PDF.
- Notificaciones o recordatorios automáticos para diligenciar el reporte semanal.
- App móvil nativa (solo web responsive).
- Integración con CRM externo.
- Pronóstico ponderado por etapa (`ES`/`POC`/`COC`/`PF`) — el `estado` queda informativo, igual que en el Excel.

## 9. Esquema SQL completo (MySQL 8.0)

```sql
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
-- dias y probabilidad NO se guardan: se calculan al leer (HOY() - fecha_creacion).

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
    meta_mes                        DECIMAL(14,2) NOT NULL
        COMMENT 'Copiado de metas_mensuales al crear el reporte; editable puntualmente',
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
-- venta_otros y participaciones NO se guardan: se calculan al leer
-- (venta_general - venta_empresas, etc.), igual que D8/E7/E8 en el Excel.

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
```

**Notas de diseño del esquema:**

- `DECIMAL(14,2)` en todos los montos (no `FLOAT`) para evitar errores de redondeo en dinero — hasta 999.999.999.999,99, de sobra para los montos manejados hoy (el pipeline total en el Excel analizado era $324.500.000).
- `ON DELETE RESTRICT` en todas las FK hacia `usuarios`: los ejecutivos no se borran físicamente (se usa `activo = 0`), así que nunca debería intentarse un delete que rompa esta restricción; está ahí como cinturón de seguridad.
- `CHECK (venta_empresas <= venta_general)`: refuerza a nivel de base de datos la regla del Excel donde `OTROS = GENERAL - EMPRESAS` nunca puede ser negativo.
- `UNIQUE (ejecutivo_id, fecha_reporte)` y `UNIQUE (ejecutivo_id, anio, mes)`: implementan a nivel de BD las reglas de "un solo reporte por semana" y "una sola meta por ejecutivo por mes".
- `parametros` es clave-valor (en vez de columnas fijas) para poder agregar nuevos parámetros sin migración futura.

## 10. Decisiones registradas (para no re-discutir)

- El motor de pronóstico replica fielmente el Excel (30% plano + probabilidad por antigüedad); no se introduce ponderación por etapa.
- El umbral del 40% (`E1` del Excel) es código huérfano y se omite del sistema nuevo.
- El "resumen semanal" se diligencia en formulario web nativo, no se sube/parsea el archivo Excel.
- El pipeline de oportunidades es una entidad persistente (vive entre semanas); el reporte semanal es una foto/snapshot independiente.
- Stack: PHP + MySQL + Docker, igual que los demás proyectos internos de Compulago.
- `parametros` (configuración global) y `metas_mensuales` (meta por ejecutivo/mes) son tablas separadas por tener cardinalidad distinta.
