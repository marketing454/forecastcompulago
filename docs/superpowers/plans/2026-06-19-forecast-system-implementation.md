# Forecast System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the manual weekly Excel forecast process with a web app where executives manage their pipeline and weekly report, and an admin sees a consolidated dashboard.

**Architecture:** PHP 8 (no framework, PSR-4 autoload via Composer) + MySQL 8 + Docker Compose, mirroring the existing Compulago internal tools (`PQR-Plataforma`, `arma-tu-pc-compulago-system`). Business logic (forecast calculations) lives in plain, dependency-free `App\Services` classes covered by PHPUnit; pages are direct PHP scripts under `public/` (no router), matching the `PQR-Plataforma` convention.

**Tech Stack:** PHP 8.2 (`php:8.2-apache` image), MySQL 8.0, Docker Compose, Composer (PSR-4), PHPUnit 10.

**Spec:** `docs/superpowers/specs/2026-06-19-forecast-system-design.md`

## Global Constraints

- DB types: all money fields are `DECIMAL(14,2)` — never `FLOAT` (spec §9).
- Forecast formulas must replicate the Excel exactly: `probabilidad` = ALTA ≤15 días, MEDIA 16-30, BAJA >30; `pronostico_ponderado` = `total_pipeline × 0.30` flat; semáforo bands = rojo ≤30% meta, ámbar 31-80%, verde >80% (spec §6, §9).
- `estado` (`ES`/`POC`/`COC`/`PF`/`OTROS`) is informational only — never feeds into probability or forecast calculations (spec §9).
- `oportunidades` rows are never hard-deleted — deactivate via `activa = 0` (spec §5).
- One `reportes_semanales` row per `(ejecutivo_id, fecha_reporte)` — enforced by a DB unique key, not just app logic (spec §9).
- Two roles only: `ejecutivo` (own data) and `admin` (consolidated view + user/parameter management) — no other roles in this MVP (spec §3).
- No file upload/Excel import anywhere — all data entry is native web forms (spec §3, decision log).

---

## Task 1: Project scaffolding and Docker environment

**Files:**
- Create: `composer.json`
- Create: `Dockerfile`
- Create: `docker-compose.yml`

**Interfaces:**
- Produces: a running `web` container serving `/var/www/html/public` on `http://localhost:8090`, a `db` container (MySQL 8.0) on host port `3309`, and `phpmyadmin` on `http://localhost:8091`. Composer autoloads `App\` → `app/` and `Tests\` → `tests/`.

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "compulago/forecast-system",
  "description": "Sistema de forecast semanal para ejecutivos corporativos de Compulago",
  "type": "project",
  "license": "MIT",
  "require": {
    "php": ">=8.1"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit --testdox tests"
  }
}
```

- [ ] **Step 2: Write `Dockerfile`**

```dockerfile
FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libzip-dev unzip git \
    && docker-php-ext-install pdo pdo_mysql

RUN sed -i -e 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
```

- [ ] **Step 3: Write `docker-compose.yml`**

```yaml
services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: forecast_web
    ports:
      - "8090:80"
    volumes:
      - .:/var/www/html
    environment:
      - DB_HOST=db
      - DB_NAME=forecast_db
      - DB_USER=root
      - DB_PASSWORD=rootpass
      - DB_PORT=3306
    depends_on:
      - db

  db:
    image: mysql:8.0
    container_name: forecast_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: forecast_db
    ports:
      - "3309:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/init.sql:/docker-entrypoint-initdb.d/init.sql

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: forecast_phpmyadmin
    ports:
      - "8091:80"
    environment:
      PMA_HOST: db
      MYSQL_ROOT_PASSWORD: rootpass
    depends_on:
      - db

volumes:
  db_data:
```

- [ ] **Step 4: Create the public docroot placeholder so Apache has something to serve**

```bash
mkdir -p public
echo "<?php echo 'Forecast System booting...';" > public/index.php
```

- [ ] **Step 5: Build and start the containers**

Run: `docker compose up -d --build`
Expected: three containers (`forecast_web`, `forecast_db`, `forecast_phpmyadmin`) reported as running. Verify with `docker compose ps`.

- [ ] **Step 6: Verify the web container serves the placeholder page**

Run: `curl -s http://localhost:8090/`
Expected output: `Forecast System booting...`

- [ ] **Step 7: Commit**

```bash
git add composer.json Dockerfile docker-compose.yml public/index.php
git commit -m "chore: scaffold PHP+MySQL+Docker project"
```

---

## Task 2: Database schema

**Files:**
- Create: `docker/init.sql`

**Interfaces:**
- Produces: tables `usuarios`, `oportunidades`, `metas_mensuales`, `reportes_semanales`, `parametros` in the `forecast_db` schema, with `parametros` pre-seeded with the five default values from spec §9.

- [ ] **Step 1: Write `docker/init.sql`**

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
```

- [ ] **Step 2: Recreate the db container so the init script runs**

The MySQL init script only runs on first volume creation, so wipe the volume and start clean:

Run: `docker compose down -v && docker compose up -d --build`
Expected: containers start; no errors in `docker compose logs db`.

- [ ] **Step 3: Verify all five tables exist**

Run: `docker compose exec db mysql -uroot -prootpass forecast_db -e "SHOW TABLES;"`
Expected output (5 rows): `metas_mensuales`, `oportunidades`, `parametros`, `reportes_semanales`, `usuarios`.

- [ ] **Step 4: Verify the parametros seed data**

Run: `docker compose exec db mysql -uroot -prootpass forecast_db -e "SELECT clave, valor FROM parametros;"`
Expected: 5 rows matching the `INSERT` statement above.

- [ ] **Step 5: Commit**

```bash
git add docker/init.sql
git commit -m "feat: add database schema for usuarios, oportunidades, metas, reportes y parametros"
```

---

## Task 3: DB connection bootstrap and session config

**Files:**
- Create: `includes/config.php`

**Interfaces:**
- Consumes: env vars `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_PORT` set in `docker-compose.yml` (Task 1).
- Produces: global function `db(): PDO` (a cached PDO connection) and an active PHP session, used by every Model and page from Task 4 onward.

- [ ] **Step 1: Write `includes/config.php`**

```php
<?php
session_start();

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'forecast_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'rootpass');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

require_once __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 2: Install Composer dependencies inside the container**

The bind mount in `docker-compose.yml` overwrites anything installed at image build time, so `vendor/` must be created after the container is up.

Run: `docker compose exec web composer install`
Expected: `vendor/autoload.php` created, no errors.

- [ ] **Step 3: Verify the DB connection works end-to-end**

Run:
```bash
docker compose exec web php -r "require 'includes/config.php'; var_dump(db()->query('SELECT 1')->fetchColumn());"
```
Expected output: `string(1) "1"` (or `int(1)`), no exceptions.

- [ ] **Step 4: Commit**

```bash
git add includes/config.php
git commit -m "feat: add PDO connection bootstrap and session start"
```

---

## Task 4: Auth — Usuario model, session helpers, login/logout pages

**Files:**
- Create: `app/Models/Usuario.php`
- Create: `includes/auth.php`
- Create: `public/login.php`
- Create: `public/logout.php`
- Modify: `public/index.php` (replace placeholder from Task 1 with a role-based redirect)

**Interfaces:**
- Consumes: `db()` from Task 3.
- Produces: `App\Models\Usuario::findByEmail()`, `::find()`, `::allEjecutivos()`, `::create()`, `::setActivo()`; global functions `attemptLogin()`, `logout()`, `currentUserId()`, `currentUserRol()`, `requireLogin()`, `requireRole()` — every later page calls `requireRole()`.

- [ ] **Step 1: Write `app/Models/Usuario.php`**

```php
<?php
namespace App\Models;

class Usuario
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allEjecutivos(): array
    {
        $stmt = db()->query("SELECT * FROM usuarios WHERE rol = 'ejecutivo' ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public static function create(string $nombre, string $email, string $password, string $rol): int
    {
        $stmt = db()->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT), $rol]);
        return (int) db()->lastInsertId();
    }

    public static function setActivo(int $id, bool $activo): void
    {
        $stmt = db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
        $stmt->execute([$activo ? 1 : 0, $id]);
    }
}
```

- [ ] **Step 2: Write `includes/auth.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use App\Models\Usuario;

function attemptLogin(string $email, string $password): bool
{
    $usuario = Usuario::findByEmail($email);
    if ($usuario === null || !$usuario['activo']) {
        return false;
    }
    if (!password_verify($password, $usuario['password_hash'])) {
        return false;
    }
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function currentUserId(): ?int
{
    return $_SESSION['usuario_id'] ?? null;
}

function currentUserNombre(): ?string
{
    return $_SESSION['usuario_nombre'] ?? null;
}

function currentUserRol(): ?string
{
    return $_SESSION['usuario_rol'] ?? null;
}

function requireLogin(): void
{
    if (currentUserId() === null) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $rol): void
{
    requireLogin();
    if (currentUserRol() !== $rol) {
        http_response_code(403);
        die('No tienes permiso para ver esta página.');
    }
}
```

- [ ] **Step 3: Write `public/login.php`**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (attemptLogin($email, $password)) {
        header('Location: ' . (currentUserRol() === 'admin' ? '/admin/dashboard.php' : '/dashboard.php'));
        exit;
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Iniciar sesión - Forecast Compulago</title></head>
<body>
<h1>Forecast Compulago</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <button type="submit">Entrar</button>
</form>
</body>
</html>
```

- [ ] **Step 4: Write `public/logout.php`**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /login.php');
exit;
```

- [ ] **Step 5: Replace `public/index.php` with a role-based redirect**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUserId() === null) {
    header('Location: /login.php');
} elseif (currentUserRol() === 'admin') {
    header('Location: /admin/dashboard.php');
} else {
    header('Location: /dashboard.php');
}
exit;
```

- [ ] **Step 6: Manually verify login rejects unknown users**

Run: `curl -s -i -X POST http://localhost:8090/login.php -d "email=nadie@compulago.com&password=x"`
Expected: HTTP 200 body contains `Email o contraseña incorrectos.`

- [ ] **Step 7: Commit**

```bash
git add app/Models/Usuario.php includes/auth.php public/login.php public/logout.php public/index.php
git commit -m "feat: add session-based auth with login/logout"
```

---

## Task 5: PipelineCalculator service (TDD)

**Files:**
- Create: `app/Services/PipelineCalculator.php`
- Test: `tests/PipelineCalculatorTest.php`

**Interfaces:**
- Produces: `App\Services\PipelineCalculator` with `dias()`, `probabilidad()`, `totalPipeline()`, `pronosticoPonderado()`, `semaforo()` — consumed by `public/pipeline.php`, `public/reporte.php`, `public/dashboard.php`, `public/admin/dashboard.php`, and `App\Models\ReporteSemanal::guardar()` in later tasks.

- [ ] **Step 1: Write the failing tests**

```php
<?php
namespace Tests;

use App\Services\PipelineCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PipelineCalculatorTest extends TestCase
{
    private PipelineCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PipelineCalculator();
    }

    public function test_dias_counts_days_between_creation_and_today(): void
    {
        $creada = new DateTimeImmutable('2026-06-01');
        $hoy = new DateTimeImmutable('2026-06-10');
        $this->assertSame(9, $this->calculator->dias($creada, $hoy));
    }

    public function test_probabilidad_is_alta_within_15_days(): void
    {
        $this->assertSame('ALTA', $this->calculator->probabilidad(15));
    }

    public function test_probabilidad_is_media_between_16_and_30_days(): void
    {
        $this->assertSame('MEDIA', $this->calculator->probabilidad(16));
        $this->assertSame('MEDIA', $this->calculator->probabilidad(30));
    }

    public function test_probabilidad_is_baja_after_30_days(): void
    {
        $this->assertSame('BAJA', $this->calculator->probabilidad(31));
    }

    public function test_total_pipeline_sums_all_montos(): void
    {
        $this->assertSame(450.0, $this->calculator->totalPipeline([100, 150, 200]));
    }

    public function test_pronostico_ponderado_applies_flat_30_percent(): void
    {
        $this->assertSame(97350000.0, $this->calculator->pronosticoPonderado(324500000));
    }

    public function test_semaforo_rojo_at_or_below_30_percent_of_meta(): void
    {
        $this->assertSame('rojo', $this->calculator->semaforo(20_000_000, 150_000_000));
    }

    public function test_semaforo_ambar_between_30_and_80_percent_of_meta(): void
    {
        $this->assertSame('ambar', $this->calculator->semaforo(97_350_000, 150_000_000));
    }

    public function test_semaforo_verde_above_80_percent_of_meta(): void
    {
        $this->assertSame('verde', $this->calculator->semaforo(130_000_000, 150_000_000));
    }

    public function test_semaforo_rojo_when_meta_is_zero(): void
    {
        $this->assertSame('rojo', $this->calculator->semaforo(10_000, 0));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec web vendor/bin/phpunit tests/PipelineCalculatorTest.php`
Expected: FAIL — `Class "App\Services\PipelineCalculator" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
namespace App\Services;

use DateTimeImmutable;

class PipelineCalculator
{
    public function __construct(
        private int $umbralDiasAlta = 15,
        private int $umbralDiasBaja = 30,
        private float $pctConversion = 0.30,
        private float $umbralSemaforoBajo = 0.30,
        private float $umbralSemaforoAlto = 0.80,
    ) {
    }

    public function dias(DateTimeImmutable $fechaCreacion, DateTimeImmutable $hoy): int
    {
        return $hoy->diff($fechaCreacion)->days;
    }

    public function probabilidad(int $dias): string
    {
        if ($dias > $this->umbralDiasBaja) {
            return 'BAJA';
        }
        if ($dias > $this->umbralDiasAlta) {
            return 'MEDIA';
        }
        return 'ALTA';
    }

    public function totalPipeline(array $montos): float
    {
        return array_sum($montos);
    }

    public function pronosticoPonderado(float $totalPipeline): float
    {
        return $totalPipeline * $this->pctConversion;
    }

    public function semaforo(float $pronosticoPonderado, float $metaMes): string
    {
        if ($metaMes <= 0) {
            return 'rojo';
        }
        $fraccion = $pronosticoPonderado / $metaMes;
        if ($fraccion > $this->umbralSemaforoAlto) {
            return 'verde';
        }
        if ($fraccion > $this->umbralSemaforoBajo) {
            return 'ambar';
        }
        return 'rojo';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec web vendor/bin/phpunit tests/PipelineCalculatorTest.php`
Expected: `OK (10 tests, 11 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Services/PipelineCalculator.php tests/PipelineCalculatorTest.php
git commit -m "feat: add PipelineCalculator with TDD coverage"
```

---

## Task 6: ReporteSemanalService (TDD)

**Files:**
- Create: `app/Services/ReporteSemanalService.php`
- Test: `tests/ReporteSemanalServiceTest.php`

**Interfaces:**
- Produces: `App\Services\ReporteSemanalService` with `ventaOtros()` (throws `InvalidArgumentException` if `ventaEmpresas > ventaGeneral`) and `participacion()` — consumed by `public/reporte.php` and `App\Models\ReporteSemanal::guardar()`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
namespace Tests;

use App\Services\ReporteSemanalService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReporteSemanalServiceTest extends TestCase
{
    private ReporteSemanalService $service;

    protected function setUp(): void
    {
        $this->service = new ReporteSemanalService();
    }

    public function test_venta_otros_is_the_difference_between_general_and_empresas(): void
    {
        $this->assertSame(350000.0, $this->service->ventaOtros(24660000, 24310000));
    }

    public function test_venta_otros_rejects_empresas_greater_than_general(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->ventaOtros(1000, 2000);
    }

    public function test_participacion_divides_parte_by_general(): void
    {
        $this->assertEqualsWithDelta(0.9858, $this->service->participacion(24310000, 24660000), 0.0001);
    }

    public function test_participacion_is_zero_when_general_is_zero(): void
    {
        $this->assertSame(0.0, $this->service->participacion(100, 0));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec web vendor/bin/phpunit tests/ReporteSemanalServiceTest.php`
Expected: FAIL — `Class "App\Services\ReporteSemanalService" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
namespace App\Services;

use InvalidArgumentException;

class ReporteSemanalService
{
    public function ventaOtros(float $ventaGeneral, float $ventaEmpresas): float
    {
        if ($ventaEmpresas > $ventaGeneral) {
            throw new InvalidArgumentException('La venta de empresas no puede ser mayor que la venta general.');
        }
        return $ventaGeneral - $ventaEmpresas;
    }

    public function participacion(float $parte, float $ventaGeneral): float
    {
        if ($ventaGeneral <= 0) {
            return 0.0;
        }
        return $parte / $ventaGeneral;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec web vendor/bin/phpunit tests/ReporteSemanalServiceTest.php`
Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Services/ReporteSemanalService.php tests/ReporteSemanalServiceTest.php
git commit -m "feat: add ReporteSemanalService with TDD coverage"
```

---

## Task 7: Oportunidad model + shared layout + "Mi Pipeline" page

**Files:**
- Create: `app/Models/Oportunidad.php`
- Create: `includes/layout_header.php`
- Create: `includes/layout_footer.php`
- Create: `public/pipeline.php`

**Interfaces:**
- Consumes: `db()` (Task 3), `requireRole()`/`currentUserRol()` (Task 4), `PipelineCalculator` (Task 5).
- Produces: `App\Models\Oportunidad::create()`, `::activasByEjecutivo()`, `::find()`, `::update()`, `::setActiva()` — consumed by `public/reporte.php` (Task 9), `public/dashboard.php` (Task 10) and `App\Models\ReporteSemanal` (Task 9). The shared layout partials are reused by every page from this task onward.

- [ ] **Step 1: Write `app/Models/Oportunidad.php`**

```php
<?php
namespace App\Models;

class Oportunidad
{
    public static function create(int $ejecutivoId, string $cuenta, string $nit, string $tipo, string $fechaCreacion, float $monto, string $estado): int
    {
        $stmt = db()->prepare(
            'INSERT INTO oportunidades (ejecutivo_id, cuenta, nit, tipo, fecha_creacion, monto, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ejecutivoId, $cuenta, $nit, $tipo, $fechaCreacion, $monto, $estado]);
        return (int) db()->lastInsertId();
    }

    public static function activasByEjecutivo(int $ejecutivoId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM oportunidades WHERE ejecutivo_id = ? AND activa = 1 ORDER BY fecha_creacion DESC'
        );
        $stmt->execute([$ejecutivoId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM oportunidades WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, string $cuenta, string $nit, string $tipo, float $monto, string $estado): void
    {
        $stmt = db()->prepare(
            'UPDATE oportunidades SET cuenta = ?, nit = ?, tipo = ?, monto = ?, estado = ? WHERE id = ?'
        );
        $stmt->execute([$cuenta, $nit, $tipo, $monto, $estado, $id]);
    }

    public static function setActiva(int $id, bool $activa): void
    {
        $stmt = db()->prepare('UPDATE oportunidades SET activa = ? WHERE id = ?');
        $stmt->execute([$activa ? 1 : 0, $id]);
    }
}
```

- [ ] **Step 2: Write `includes/layout_header.php`**

```php
<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Forecast Compulago</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; }
        nav { background: #1c2b4a; padding: 12px 20px; }
        nav a { color: #fff; margin-right: 16px; text-decoration: none; }
        main { padding: 20px; max-width: 1100px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .rojo { background: #FF0000; color: #fff; }
        .ambar { background: #FFC000; }
        .verde { background: #00B050; color: #fff; }
        form input, form select, form textarea { display: block; margin-bottom: 10px; width: 100%; max-width: 400px; padding: 6px; }
        button { padding: 8px 16px; cursor: pointer; }
    </style>
</head>
<body>
<nav>
    <?php if (currentUserRol() === 'ejecutivo'): ?>
        <a href="/pipeline.php">Mi Pipeline</a>
        <a href="/reporte.php">Reporte Semanal</a>
        <a href="/dashboard.php">Mi Dashboard</a>
    <?php elseif (currentUserRol() === 'admin'): ?>
        <a href="/admin/dashboard.php">Dashboard Consolidado</a>
        <a href="/admin/usuarios.php">Usuarios</a>
        <a href="/admin/parametros.php">Parámetros</a>
    <?php endif; ?>
    <a href="/logout.php" style="float:right;">Salir</a>
</nav>
<main>
```

- [ ] **Step 3: Write `includes/layout_footer.php`**

```php
</main>
</body>
</html>
```

- [ ] **Step 4: Write `public/pipeline.php`**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\Oportunidad;
use App\Services\PipelineCalculator;

$calculator = new PipelineCalculator();
$ejecutivoId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        Oportunidad::create(
            $ejecutivoId,
            $_POST['cuenta'],
            $_POST['nit'],
            $_POST['tipo'],
            $_POST['fecha_creacion'],
            (float) $_POST['monto'],
            $_POST['estado']
        );
    } elseif ($accion === 'desactivar') {
        Oportunidad::setActiva((int) $_POST['id'], false);
    }
    header('Location: /pipeline.php');
    exit;
}

$oportunidades = Oportunidad::activasByEjecutivo($ejecutivoId);
$hoy = new DateTimeImmutable();

require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Mi Pipeline</h1>

<form method="post">
    <input type="hidden" name="accion" value="crear">
    <input type="text" name="cuenta" placeholder="Cuenta" required>
    <input type="text" name="nit" placeholder="NIT" required>
    <select name="tipo" required>
        <?php foreach (['COMPUTO','SERVIDOR','IMPRESION','SOFTWARE','IMAGEN_Y_VIDEO','SERVICIOS','CONECTIVIDAD','COMBINADO'] as $tipo): ?>
            <option value="<?= $tipo ?>"><?= $tipo ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="fecha_creacion" value="<?= $hoy->format('Y-m-d') ?>" required>
    <input type="number" name="monto" placeholder="Monto" step="0.01" required>
    <select name="estado" required>
        <?php foreach (['ES','POC','COC','PF','OTROS'] as $estado): ?>
            <option value="<?= $estado ?>"><?= $estado ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Agregar oportunidad</button>
</form>

<table>
    <tr><th>Cuenta</th><th>NIT</th><th>Tipo</th><th>Monto</th><th>Días</th><th>Probabilidad</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($oportunidades as $op): ?>
        <?php $dias = $calculator->dias(new DateTimeImmutable($op['fecha_creacion']), $hoy); ?>
        <tr>
            <td><?= htmlspecialchars($op['cuenta']) ?></td>
            <td><?= htmlspecialchars($op['nit']) ?></td>
            <td><?= htmlspecialchars($op['tipo']) ?></td>
            <td><?= number_format((float) $op['monto'], 0) ?></td>
            <td><?= $dias ?></td>
            <td><?= $calculator->probabilidad($dias) ?></td>
            <td><?= htmlspecialchars($op['estado']) ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="accion" value="desactivar">
                    <input type="hidden" name="id" value="<?= $op['id'] ?>">
                    <button type="submit">Desactivar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
```

- [ ] **Step 5: Manually verify (requires a test ejecutivo — create one quickly via the PHP console)**

```bash
docker compose exec web php -r "
require 'includes/config.php';
use App\Models\Usuario;
if (Usuario::findByEmail('ejecutivo.test@compulago.com') === null) {
    Usuario::create('Ejecutivo Test', 'ejecutivo.test@compulago.com', 'Test1234!', 'ejecutivo');
    echo 'creado';
}
"
```

Then log in at `http://localhost:8090/login.php` with `ejecutivo.test@compulago.com` / `Test1234!`, go to "Mi Pipeline", add an opportunity dated today, and confirm it shows `0` días and `ALTA` probabilidad in the table.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Oportunidad.php includes/layout_header.php includes/layout_footer.php public/pipeline.php
git commit -m "feat: add oportunidad CRUD and Mi Pipeline page"
```

---

## Task 8: MetaMensual + Parametro models

**Files:**
- Create: `app/Models/MetaMensual.php`
- Create: `app/Models/Parametro.php`

**Interfaces:**
- Consumes: `db()` (Task 3).
- Produces: `App\Models\MetaMensual::forEjecutivoMes()`, `::upsert()` — consumed by `public/reporte.php` and `public/admin/usuarios.php` (Tasks 9, 11). `App\Models\Parametro::allAsAssoc()`, `::set()` — consumed by `public/admin/parametros.php` (Task 11).

- [ ] **Step 1: Write `app/Models/MetaMensual.php`**

```php
<?php
namespace App\Models;

class MetaMensual
{
    public static function forEjecutivoMes(int $ejecutivoId, int $anio, int $mes): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM metas_mensuales WHERE ejecutivo_id = ? AND anio = ? AND mes = ? LIMIT 1'
        );
        $stmt->execute([$ejecutivoId, $anio, $mes]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsert(int $ejecutivoId, int $anio, int $mes, float $montoMeta): void
    {
        $stmt = db()->prepare(
            'INSERT INTO metas_mensuales (ejecutivo_id, anio, mes, monto_meta)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_meta = VALUES(monto_meta)'
        );
        $stmt->execute([$ejecutivoId, $anio, $mes, $montoMeta]);
    }
}
```

- [ ] **Step 2: Write `app/Models/Parametro.php`**

```php
<?php
namespace App\Models;

class Parametro
{
    public static function allAsAssoc(): array
    {
        $stmt = db()->query('SELECT clave, valor FROM parametros');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['clave']] = $row['valor'];
        }
        return $result;
    }

    public static function set(string $clave, string $valor): void
    {
        $stmt = db()->prepare('UPDATE parametros SET valor = ? WHERE clave = ?');
        $stmt->execute([$valor, $clave]);
    }
}
```

- [ ] **Step 3: Verify both models against the seeded data**

```bash
docker compose exec web php -r "
require 'includes/config.php';
use App\Models\Parametro;
use App\Models\MetaMensual;
var_dump(Parametro::allAsAssoc());
MetaMensual::upsert(1, 2026, 6, 150000000);
var_dump(MetaMensual::forEjecutivoMes(1, 2026, 6));
"
```
Expected: first `var_dump` shows the 5 seeded keys; second shows the upserted row with `monto_meta` `150000000.00`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/MetaMensual.php app/Models/Parametro.php
git commit -m "feat: add MetaMensual and Parametro models"
```

---

## Task 9: ReporteSemanal model + "Reporte Semanal" page

**Files:**
- Create: `app/Models/ReporteSemanal.php`
- Create: `public/reporte.php`

**Interfaces:**
- Consumes: `Oportunidad::activasByEjecutivo()` (Task 7), `PipelineCalculator` (Task 5), `ReporteSemanalService` (Task 6), `MetaMensual::forEjecutivoMes()` (Task 8).
- Produces: `App\Models\ReporteSemanal::fechaInicioSemana()`, `::findSemanaActual()`, `::historial()`, `::guardar()`, `::ultimoDeTodos()` — consumed by `public/dashboard.php` (Task 10) and `public/admin/dashboard.php` (Task 12).

- [ ] **Step 1: Write `app/Models/ReporteSemanal.php`**

```php
<?php
namespace App\Models;

use App\Services\PipelineCalculator;
use DateTimeImmutable;

class ReporteSemanal
{
    public static function fechaInicioSemana(DateTimeImmutable $fecha): string
    {
        $diasDesdeLunes = (int) $fecha->format('N') - 1;
        return $fecha->modify("-{$diasDesdeLunes} days")->format('Y-m-d');
    }

    public static function findSemanaActual(int $ejecutivoId): ?array
    {
        $fechaSemana = self::fechaInicioSemana(new DateTimeImmutable());
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? AND fecha_reporte = ? LIMIT 1'
        );
        $stmt->execute([$ejecutivoId, $fechaSemana]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function historial(int $ejecutivoId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM reportes_semanales WHERE ejecutivo_id = ? ORDER BY fecha_reporte DESC'
        );
        $stmt->execute([$ejecutivoId]);
        return $stmt->fetchAll();
    }

    public static function guardar(int $ejecutivoId, float $metaMes, float $ventaEmpresas, float $ventaGeneral, string $comentarios): void
    {
        $fechaSemana = self::fechaInicioSemana(new DateTimeImmutable());
        $montos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
        $calculator = new PipelineCalculator();
        $totalPipeline = $calculator->totalPipeline($montos);
        $pronostico = $calculator->pronosticoPonderado($totalPipeline);

        $existente = self::findSemanaActual($ejecutivoId);
        if ($existente !== null) {
            $stmt = db()->prepare(
                'UPDATE reportes_semanales
                 SET meta_mes = ?, venta_empresas = ?, venta_general = ?, comentarios = ?,
                     total_pipeline_snapshot = ?, pronostico_ponderado_snapshot = ?
                 WHERE id = ?'
            );
            $stmt->execute([$metaMes, $ventaEmpresas, $ventaGeneral, $comentarios, $totalPipeline, $pronostico, $existente['id']]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO reportes_semanales
                (ejecutivo_id, fecha_reporte, meta_mes, venta_empresas, venta_general, comentarios,
                 total_pipeline_snapshot, pronostico_ponderado_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ejecutivoId, $fechaSemana, $metaMes, $ventaEmpresas, $ventaGeneral, $comentarios, $totalPipeline, $pronostico]);
    }

    public static function ultimoDeTodos(): array
    {
        $stmt = db()->query(
            "SELECT r.*, u.nombre AS ejecutivo_nombre FROM reportes_semanales r
             INNER JOIN (
                 SELECT ejecutivo_id, MAX(fecha_reporte) AS max_fecha
                 FROM reportes_semanales
                 GROUP BY ejecutivo_id
             ) ultimo ON ultimo.ejecutivo_id = r.ejecutivo_id AND ultimo.max_fecha = r.fecha_reporte
             INNER JOIN usuarios u ON u.id = r.ejecutivo_id"
        );
        return $stmt->fetchAll();
    }
}
```

- [ ] **Step 2: Write `public/reporte.php`**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\MetaMensual;
use App\Models\Oportunidad;
use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$ejecutivoId = currentUserId();
$hoy = new DateTimeImmutable();
$reporteActual = ReporteSemanal::findSemanaActual($ejecutivoId);
$metaDelMes = MetaMensual::forEjecutivoMes($ejecutivoId, (int) $hoy->format('Y'), (int) $hoy->format('n'));
$metaMesDefault = $reporteActual['meta_mes'] ?? ($metaDelMes['monto_meta'] ?? 0);

$mensaje = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $servicio = new ReporteSemanalService();
        $ventaGeneral = (float) $_POST['venta_general'];
        $ventaEmpresas = (float) $_POST['venta_empresas'];
        $servicio->ventaOtros($ventaGeneral, $ventaEmpresas);
        ReporteSemanal::guardar($ejecutivoId, (float) $_POST['meta_mes'], $ventaEmpresas, $ventaGeneral, $_POST['comentarios']);
        $mensaje = 'Reporte guardado.';
        $reporteActual = ReporteSemanal::findSemanaActual($ejecutivoId);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$calculator = new PipelineCalculator();
$montos = array_column(Oportunidad::activasByEjecutivo($ejecutivoId), 'monto');
$totalPipeline = $calculator->totalPipeline($montos);
$pronostico = $calculator->pronosticoPonderado($totalPipeline);

require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Reporte Semanal</h1>
<?php if ($mensaje): ?><p style="color:green;"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<p>Total pipeline activo: <?= number_format($totalPipeline, 0) ?> — Pronóstico ponderado (30%): <?= number_format($pronostico, 0) ?></p>

<form method="post">
    <label>Meta del mes</label>
    <input type="number" name="meta_mes" step="0.01" value="<?= htmlspecialchars((string) $metaMesDefault) ?>" required>
    <label>Venta empresas (esta semana)</label>
    <input type="number" name="venta_empresas" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_empresas'] ?? '')) ?>" required>
    <label>Venta general (esta semana)</label>
    <input type="number" name="venta_general" step="0.01" value="<?= htmlspecialchars((string) ($reporteActual['venta_general'] ?? '')) ?>" required>
    <label>Comentarios</label>
    <textarea name="comentarios" rows="4"><?= htmlspecialchars($reporteActual['comentarios'] ?? '') ?></textarea>
    <button type="submit">Guardar reporte de esta semana</button>
</form>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
```

- [ ] **Step 3: Manually verify the form rejects venta_empresas > venta_general**

Log in as the test ejecutivo from Task 7, go to "Reporte Semanal", submit `venta_general=1000` and `venta_empresas=2000`.
Expected: page reloads showing the red error message from `ReporteSemanalService`, and no row is written (`docker compose exec db mysql -uroot -prootpass forecast_db -e "SELECT * FROM reportes_semanales;"` returns 0 rows for that exec).

- [ ] **Step 4: Manually verify a valid submission persists a snapshot**

Submit `venta_general=24660000`, `venta_empresas=24310000`, `meta_mes=150000000`.
Expected: success message; `SELECT total_pipeline_snapshot, pronostico_ponderado_snapshot FROM reportes_semanales` shows values matching the opportunity created in Task 7.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ReporteSemanal.php public/reporte.php
git commit -m "feat: add ReporteSemanal model and weekly report form"
```

---

## Task 10: "Mi Dashboard" page (ejecutivo)

**Files:**
- Create: `public/dashboard.php`

**Interfaces:**
- Consumes: `ReporteSemanal::historial()` (Task 9), `PipelineCalculator::semaforo()` (Task 5), `ReporteSemanalService::ventaOtros()`/`::participacion()` (Task 6).

- [ ] **Step 1: Write `public/dashboard.php`**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('ejecutivo');

use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;
use App\Services\ReporteSemanalService;

$ejecutivoId = currentUserId();
$historial = ReporteSemanal::historial($ejecutivoId);
$actual = $historial[0] ?? null;
$calculator = new PipelineCalculator();
$servicio = new ReporteSemanalService();

require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Mi Dashboard</h1>

<?php if ($actual === null): ?>
    <p>Aún no has guardado ningún reporte semanal.</p>
<?php else: ?>
    <?php
    $semaforo = $calculator->semaforo((float) $actual['pronostico_ponderado_snapshot'], (float) $actual['meta_mes']);
    $ventaOtros = $servicio->ventaOtros((float) $actual['venta_general'], (float) $actual['venta_empresas']);
    $pctEmpresas = $servicio->participacion((float) $actual['venta_empresas'], (float) $actual['venta_general']) * 100;
    $pctOtros = $servicio->participacion($ventaOtros, (float) $actual['venta_general']) * 100;
    ?>
    <p class="<?= $semaforo ?>">Semáforo actual: <?= strtoupper($semaforo) ?></p>
    <p>Total pipeline: <?= number_format((float) $actual['total_pipeline_snapshot'], 0) ?></p>
    <p>Pronóstico ponderado: <?= number_format((float) $actual['pronostico_ponderado_snapshot'], 0) ?></p>

    <h2>Venta semanal por categoría</h2>
    <div style="display:flex; height:24px; width:100%; max-width:600px; border:1px solid #ccc;">
        <div style="background:#1c2b4a; width:<?= $pctEmpresas ?>%;"></div>
        <div style="background:#90a4d4; width:<?= $pctOtros ?>%;"></div>
    </div>
    <p>
        Empresas: <?= number_format((float) $actual['venta_empresas'], 0) ?> (<?= number_format($pctEmpresas, 1) ?>%) —
        Otros: <?= number_format($ventaOtros, 0) ?> (<?= number_format($pctOtros, 1) ?>%)
    </p>
<?php endif; ?>

<h2>Histórico de reportes</h2>
<table>
    <tr><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    <?php foreach ($historial as $reporte): ?>
        <?php $s = $calculator->semaforo((float) $reporte['pronostico_ponderado_snapshot'], (float) $reporte['meta_mes']); ?>
        <tr>
            <td><?= $reporte['fecha_reporte'] ?></td>
            <td><?= number_format((float) $reporte['meta_mes'], 0) ?></td>
            <td><?= number_format((float) $reporte['venta_general'], 0) ?></td>
            <td><?= number_format((float) $reporte['pronostico_ponderado_snapshot'], 0) ?></td>
            <td class="<?= $s ?>"><?= strtoupper($s) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
```

- [ ] **Step 2: Manually verify**

Log in as the test ejecutivo, open "Mi Dashboard". Expected: the report saved in Task 9 (Step 4) appears with `pronostico_ponderado_snapshot` / `meta_mes` = 97,350,000 / 150,000,000 ≈ 64.9%, so the semáforo cell shows `AMBAR` with the amber background; the bar chart shows ~98.6% empresas / ~1.4% otros, matching `venta_empresas=24310000` / `venta_general=24660000`.

- [ ] **Step 3: Commit**

```bash
git add public/dashboard.php
git commit -m "feat: add ejecutivo dashboard with semaforo and historial"
```

---

## Task 11: Admin — Usuarios and Parámetros pages

**Files:**
- Create: `public/admin/usuarios.php`
- Create: `public/admin/parametros.php`

**Interfaces:**
- Consumes: `Usuario::create()`, `::setActivo()`, `::allEjecutivos()` (Task 4), `MetaMensual::upsert()` (Task 8), `Parametro::allAsAssoc()`, `::set()` (Task 8).

- [ ] **Step 1: Write `public/admin/usuarios.php`**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\MetaMensual;
use App\Models\Usuario;

$hoy = new DateTimeImmutable();
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        Usuario::create($_POST['nombre'], $_POST['email'], $_POST['password'], 'ejecutivo');
        $mensaje = 'Ejecutivo creado.';
    } elseif ($accion === 'desactivar') {
        Usuario::setActivo((int) $_POST['id'], false);
        $mensaje = 'Ejecutivo desactivado.';
    } elseif ($accion === 'activar') {
        Usuario::setActivo((int) $_POST['id'], true);
        $mensaje = 'Ejecutivo activado.';
    } elseif ($accion === 'meta') {
        MetaMensual::upsert((int) $_POST['ejecutivo_id'], (int) $hoy->format('Y'), (int) $hoy->format('n'), (float) $_POST['monto_meta']);
        $mensaje = 'Meta del mes actualizada.';
    }
}

$ejecutivos = Usuario::allEjecutivos();

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Gestión de usuarios</h1>
<?php if ($mensaje): ?><p style="color:green;"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>

<h2>Crear ejecutivo</h2>
<form method="post">
    <input type="hidden" name="accion" value="crear">
    <input type="text" name="nombre" placeholder="Nombre" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Contraseña temporal" required>
    <button type="submit">Crear</button>
</form>

<h2>Ejecutivos</h2>
<table>
    <tr><th>Nombre</th><th>Email</th><th>Activo</th><th>Meta del mes</th><th>Acciones</th></tr>
    <?php foreach ($ejecutivos as $ej): ?>
        <tr>
            <td><?= htmlspecialchars($ej['nombre']) ?></td>
            <td><?= htmlspecialchars($ej['email']) ?></td>
            <td><?= $ej['activo'] ? 'Sí' : 'No' ?></td>
            <td>
                <form method="post" style="display:flex; gap:6px;">
                    <input type="hidden" name="accion" value="meta">
                    <input type="hidden" name="ejecutivo_id" value="<?= $ej['id'] ?>">
                    <input type="number" name="monto_meta" step="0.01" placeholder="Monto">
                    <button type="submit">Guardar</button>
                </form>
            </td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $ej['id'] ?>">
                    <input type="hidden" name="accion" value="<?= $ej['activo'] ? 'desactivar' : 'activar' ?>">
                    <button type="submit"><?= $ej['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
```

- [ ] **Step 2: Write `public/admin/parametros.php`**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\Parametro;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['parametros'] as $clave => $valor) {
        Parametro::set($clave, $valor);
    }
}

$parametros = Parametro::allAsAssoc();

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Parámetros del sistema</h1>
<form method="post">
    <?php foreach ($parametros as $clave => $valor): ?>
        <label><?= htmlspecialchars($clave) ?></label>
        <input type="text" name="parametros[<?= htmlspecialchars($clave) ?>]" value="<?= htmlspecialchars($valor) ?>">
    <?php endforeach; ?>
    <button type="submit">Guardar parámetros</button>
</form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
```

- [ ] **Step 3: Manually verify role enforcement**

Log in as the test ejecutivo (Task 7) and request `http://localhost:8090/admin/usuarios.php` directly.
Expected: HTTP 403 body `No tienes permiso para ver esta página.` (from `requireRole()` in Task 4).

- [ ] **Step 4: Commit**

```bash
git add public/admin/usuarios.php public/admin/parametros.php
git commit -m "feat: add admin usuarios and parametros pages"
```

---

## Task 12: Admin — Dashboard consolidado

**Files:**
- Create: `public/admin/dashboard.php`

**Interfaces:**
- Consumes: `ReporteSemanal::ultimoDeTodos()` (Task 9), `PipelineCalculator::semaforo()` (Task 5).

- [ ] **Step 1: Write `public/admin/dashboard.php`**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

use App\Models\ReporteSemanal;
use App\Services\PipelineCalculator;

$calculator = new PipelineCalculator();
$ultimos = ReporteSemanal::ultimoDeTodos();

$totalGeneral = array_sum(array_column($ultimos, 'venta_general'));
$totalPipeline = array_sum(array_column($ultimos, 'total_pipeline_snapshot'));

require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Dashboard consolidado</h1>
<p>Venta general (última semana reportada por cada ejecutivo): <?= number_format((float) $totalGeneral, 0) ?></p>
<p>Pipeline total de la empresa: <?= number_format((float) $totalPipeline, 0) ?></p>

<table>
    <tr><th>Ejecutivo</th><th>Semana</th><th>Meta mes</th><th>Venta general</th><th>Pronóstico</th><th>Semáforo</th></tr>
    <?php foreach ($ultimos as $r): ?>
        <?php $s = $calculator->semaforo((float) $r['pronostico_ponderado_snapshot'], (float) $r['meta_mes']); ?>
        <tr>
            <td><?= htmlspecialchars($r['ejecutivo_nombre']) ?></td>
            <td><?= $r['fecha_reporte'] ?></td>
            <td><?= number_format((float) $r['meta_mes'], 0) ?></td>
            <td><?= number_format((float) $r['venta_general'], 0) ?></td>
            <td><?= number_format((float) $r['pronostico_ponderado_snapshot'], 0) ?></td>
            <td class="<?= $s ?>"><?= strtoupper($s) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
```

- [ ] **Step 2: Manually verify the consolidated total**

Log in as admin (seeded in Task 13 — run that task first, or temporarily promote the test ejecutivo via `UPDATE usuarios SET rol='admin' WHERE email='ejecutivo.test@compulago.com'` for this check only and revert after). Open `/admin/dashboard.php`.
Expected: one row for the test ejecutivo with the same `venta_general` (24,660,000) and `AMBAR` semáforo saved in Task 9, and the totals at the top equal that single row's values.

- [ ] **Step 3: Commit**

```bash
git add public/admin/dashboard.php
git commit -m "feat: add consolidated admin dashboard"
```

---

## Task 13: Seed admin script + end-to-end smoke test

**Files:**
- Create: `scripts/seed_admin.php`

**Interfaces:**
- Consumes: `Usuario::findByEmail()`, `::create()` (Task 4).
- Produces: one `admin` row in `usuarios`, used to log in and exercise the whole system end-to-end.

- [ ] **Step 1: Write `scripts/seed_admin.php`**

```php
<?php
require_once __DIR__ . '/../includes/config.php';

use App\Models\Usuario;

$email = 'admin@compulago.com';
$existing = Usuario::findByEmail($email);
if ($existing !== null) {
    echo "El admin ya existe ({$email}).\n";
    exit;
}

Usuario::create('Administrador', $email, 'Cambiar123!', 'admin');
echo "Admin creado: {$email} / Cambiar123! (cámbiala después de iniciar sesión).\n";
```

- [ ] **Step 2: Run the seed script**

Run: `docker compose exec web php scripts/seed_admin.php`
Expected: `Admin creado: admin@compulago.com / Cambiar123! (cámbiala después de iniciar sesión).`

- [ ] **Step 3: Run the full automated test suite**

Run: `docker compose exec web vendor/bin/phpunit --testdox`
Expected: all `PipelineCalculatorTest` and `ReporteSemanalServiceTest` cases pass (`OK (14 tests, 15 assertions)`).

- [ ] **Step 4: End-to-end manual smoke test**

1. Log in at `http://localhost:8090/login.php` as `admin@compulago.com` / `Cambiar123!` → redirected to `/admin/dashboard.php`.
2. Go to "Usuarios", create a fresh ejecutivo (different from the Task 7 test account), assign a meta del mes of `100000000`.
3. Log out, log in as the new ejecutivo.
4. In "Mi Pipeline", add two opportunities (e.g. `$10,000,000` dated today, `$20,000,000` dated 20 days ago).
5. In "Reporte Semanal", confirm `meta_mes` is prefilled with `100000000`, submit `venta_general=5000000`, `venta_empresas=5000000`.
6. In "Mi Dashboard", confirm: total pipeline = `30,000,000`, pronóstico = `9,000,000`, semáforo = `ROJO` (9% of 100,000,000).
7. Log back in as admin, open "Dashboard Consolidado", confirm the new ejecutivo's row appears with the same numbers.

- [ ] **Step 5: Commit**

```bash
git add scripts/seed_admin.php
git commit -m "feat: add admin seed script"
```

- [ ] **Step 6: Push everything to the remote**

```bash
git push
```
