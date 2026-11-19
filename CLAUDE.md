# CLAUDE.md — Sistema web de reservas de una cancha deportiva

Instrucciones para Claude Code en este repositorio. Léelas completas antes de cualquier cambio.

## 1. Proyecto

Sistema web que gestiona las reservas de una cancha deportiva por fecha y hora: impide los cruces de horario, muestra la disponibilidad y controla el estado de pago de cada reserva. Reemplaza la coordinación por WhatsApp y el cuaderno de anotaciones.

- Proyecto académico: DSI406 · Tecnología Backend (IES Túpac Amaru). Docente: Ing. Timoteo Calderón Letona. Autor: Carlos Marcial Olivera Palomino.
- Se implementa siguiendo la serie de guías de laboratorio del docente (escritas para otro sistema, una posta de salud). **Cada guía se adapta a este proyecto**; cuando la guía y este archivo no coincidan, manda este archivo.
- Idioma: todo en **español** (interfaz, mensajes de validación, nombres de tablas, columnas, modelos y relaciones, commits). Excepción: las columnas y archivos que Laravel ya trae (`users.name`, `email`, `password`, `created_at`…).

## 2. Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 (PHP 8.3+), patrón MVC |
| Base de datos | PostgreSQL en **Supabase** (región São Paulo), conexión por **Session pooler** (puerto 5432, `DB_SSLMODE=require`) |
| Vistas | Blade con herencia (`@extends('layouts.app')` / `@section`), Tailwind CSS 4 + Vite |
| Autenticación | Laravel Sanctum (API) y sesión web; roles con Policies |
| API | REST en `/api/v1` con API Resources |
| Pruebas | Pest, contra una base **local** `reservas_cancha_test` (PostgreSQL de Laragon), nunca contra Supabase |
| Zona horaria | `America/Lima` (`config/app.php`) |

Los datos de conexión (sin contraseña) están en `docs/conexion.md`. La Data API de Supabase está desactivada: **la única puerta de entrada a los datos es Laravel**.

## 3. Comandos

```bash
php artisan serve                 # servidor (terminal 1)
npm run dev                       # Vite (terminal 2)
php artisan migrate               # migraciones pendientes
php artisan migrate:fresh --seed  # reconstruye la base con datos de prueba (BORRA TODO)
php artisan db:show / db:table <tabla>
php artisan test                  # Pest
./vendor/bin/pint                 # formato de código
```

## 4. Reglas de trabajo para Claude Code

1. **Pregunta antes de ejecutar comandos destructivos** contra Supabase: `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe` o cualquier `DROP`/`TRUNCATE`. La base de desarrollo está en la nube y no tiene copias de seguridad en el plan gratuito.
2. **Nunca** leas, muestres, copies ni subas el contenido de `.env` (contiene la contraseña de Supabase). Si falta un valor, pídeselo al usuario.
3. **Nunca** crees ni modifiques tablas desde el panel de Supabase ni con SQL suelto: todo cambio de estructura va en una **migración**. No edites una migración que ya se ejecutó en el repositorio remoto: crea una nueva.
4. Trabaja **por bloques de la guía**: termina un bloque, verifica su resultado esperado y haz **un commit por bloque** (ver sección 10). No hagas `git push` sin que el usuario lo pida.
5. Antes de crear un archivo, revisa si Laravel ya trae uno equivalente (por ejemplo `resources/css/app.css`, `vite.config.js`, `app/Models/User.php`) y modifícalo en lugar de duplicarlo.
6. Si una decisión no está en este archivo ni en `docs/`, pregunta en lugar de suponer.
7. Las **pruebas** usan `RefreshDatabase` y borran la base en cada ejecución: deben correr solo contra `reservas_cancha_test` en `127.0.0.1`. Esos valores están fijados en `phpunit.xml`; la contraseña local va en `.env.testing` (no se sube). No cambies `phpunit.xml` para apuntar a Supabase. No uses SQLite para pruebas: la restricción anti-cruce necesita PostgreSQL.

## 5. Modelo de datos

Detalle completo (tipos, longitudes, nulos, índices): `docs/modelo-datos.md`. Resumen:

| Tabla | Modelo | Columnas |
|---|---|---|
| `roles` | `Rol` (`$table = 'roles'`) | id, nombre (único), descripcion |
| `users` | `User` | + `rol_id` → roles |
| `tipos_cancha` | `TipoCancha` (`$table = 'tipos_cancha'`) | id, nombre (único), descripcion |
| `canchas` | `Cancha` | id, tipo_cancha_id, nombre (único), precio_hora, hora_apertura, hora_cierre, estado |
| `clientes` | `Cliente` | id, dni (char 8, único), nombres, apellidos, telefono |
| `reservas` | `Reserva` | id, cancha_id, cliente_id, user_id, inicio, fin, precio_hora, estado |

Decisiones que no se deben revertir:

- **Roles en tabla** (`roles` + `users.rol_id`), no `enum` en `users`. Se adapta el bloque B de la Guía 02 y la Guía 05: el rol se consulta con `$user->rol->nombre` o con los métodos `esAdministrador()` / `esRecepcionista()` del modelo `User`.
- **Estados con `enum`** (Laravel crea `varchar` + `CHECK`): `canchas.estado` ∈ {disponible, mantenimiento} por defecto `disponible`; `reservas.estado` ∈ {pendiente, pagada, cancelada} por defecto `pendiente`.
- `reservas` usa **`inicio` y `fin` como `dateTime`** (fecha + hora): una cancha puede atender 24/7 y una reserva puede cruzar la medianoche. No existen columnas `fecha`, `hora_inicio` ni `hora_fin`.
- `reservas` **no guarda `horas` ni `total`** (datos derivados, 3FN). Se calculan con accessors en `Reserva`: `horas` = (fin − inicio) en horas; `total` = horas × `precio_hora`.
- `reservas.precio_hora` **sí se guarda**: es la copia del precio de la cancha al momento de reservar (RN04).
- La **disponibilidad no se guarda**: se calcula en `App\Services\DisponibilidadService`.
- Horario de atención de la cancha: `hora_cierre > hora_apertura` → mismo día; `hora_cierre < hora_apertura` → cruza la medianoche; `hora_cierre = hora_apertura` → 24 horas.
- Todas las FK con `restrictOnDelete()` (RN10). `Rol` y `TipoCancha` necesitan `constrained('roles')` y `constrained('tipos_cancha')` porque Laravel deduciría `rols` y `tipo_canchas`.

### Orden de las migraciones

1. `create_roles_table`
2. `add_rol_id_to_users_table` (modifica `users`; `down()` elimina la FK y la columna)
3. `create_tipos_cancha_table`
4. `create_canchas_table`
5. `create_clientes_table`
6. `create_reservas_table`

### Restricciones que Laravel no expresa (usar `DB::statement` en la migración)

```php
// canchas: horas completas
DB::statement("ALTER TABLE canchas ADD CONSTRAINT canchas_horas_completas CHECK (
    extract(minute from hora_apertura) = 0 AND extract(second from hora_apertura) = 0 AND
    extract(minute from hora_cierre) = 0 AND extract(second from hora_cierre) = 0)");
DB::statement('ALTER TABLE canchas ADD CONSTRAINT canchas_precio_positivo CHECK (precio_hora > 0)');

// clientes: DNI de 8 dígitos (RN08)
DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_dni_formato CHECK (dni ~ '^[0-9]{8}$')");

// reservas: bloques de horas completas, mínimo 1 hora (RN02)
DB::statement("ALTER TABLE reservas ADD CONSTRAINT reservas_rango_valido CHECK (fin > inicio)");
DB::statement("ALTER TABLE reservas ADD CONSTRAINT reservas_horas_completas CHECK (
    date_trunc('hour', inicio) = inicio AND date_trunc('hour', fin) = fin)");

// reservas: sin cruces en la misma cancha, salvo canceladas (RN01)
DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
DB::statement("ALTER TABLE reservas ADD CONSTRAINT reservas_sin_cruce EXCLUDE USING gist (
    cancha_id WITH =, tsrange(inicio, fin) WITH &&) WHERE (estado <> 'cancelada')");
```

Índices de `reservas`: `(cancha_id, inicio)` y `(inicio, estado)`.

## 6. Reglas de negocio: dónde se protege cada una

| Regla | Base de datos | Aplicación |
|---|---|---|
| RN01 Sin cruces en la misma cancha | `EXCLUDE` con `btree_gist` | Form Request con mensaje en español |
| RN02 Horas completas, mínimo 1 h | `CHECK` | Form Request |
| RN03 Ni pasado ni fuera del horario | — | Form Request (considerar 24 h y cruce de medianoche) |
| RN04 Total = horas × precio al reservar | `precio_hora` copiado | Accessor `total` |
| RN05 Nace pendiente; solo a pagada o cancelada | `enum` + default | Lógica de transición en el modelo/controlador |
| RN06 Cancelada no se reactiva | — | Transición prohibida |
| RN07 Pagada no se modifica; solo el admin la elimina | — | Policy `ReservaPolicy` |
| RN08 DNI de 8 dígitos único | `UNIQUE` + `CHECK` | Form Request |
| RN09 Solo canchas disponibles | `enum` | Form Request |
| RN10 No borrar cancha/cliente con reservas | FK `restrict` | Mensaje claro antes de intentar borrar |
| RN11 La reserva guarda quién la registró | `user_id` NOT NULL | Se asigna `auth()->id()`, nunca desde el formulario |

Códigos SQLSTATE esperados al violar la base: 23505 (único), 23503 (FK), 23514 (CHECK/enum), **23P01** (exclusión: cruce de reservas).

## 7. Convenciones de código

- **Modelos:** singular PascalCase en español (`Cancha`, `Reserva`). Los modelos nuevos usan las propiedades clásicas `protected $table`, `protected $fillable` y el método `casts()`; **`User` conserva el atributo `#[Fillable([...])]`** que trae Laravel 13 (no mezclar estilos en un mismo modelo).
- **Relaciones:** métodos en camelCase que permitan a Laravel deducir la FK: `User::rol()`, `Cancha::tipoCancha()`, `Reserva::cancha()`, `Reserva::cliente()`, `Reserva::user()` (quién la registró), y sus inversas `hasMany`.
- **Casts:** `inicio`/`fin` → `datetime`; `precio_hora` → `decimal:2`.
- **Controladores:** resource controllers; validación siempre en **Form Requests** con mensajes y atributos en español; autorización con **Policies**.
- **Consultas:** listados con `paginate()` y `with()` para evitar N+1 (RNF05).
- **Rutas:** siempre con nombre (`->name(...)`). Web en `routes/web.php`; API en `routes/api.php` bajo el prefijo `v1`.
- **Vistas:** `resources/views/layouts/app.blade.php` es la plantilla madre; cada módulo en su carpeta (`canchas/`, `clientes/`, `reservas/`); fragmentos reutilizables en `resources/views/partials/`. Tailwind para que todo se adapte a celular (RNF06).
- **Formato:** ejecutar Pint antes de cada commit.

## 8. Roles y permisos

| Acción | Administrador | Recepcionista | Público |
|---|---|---|---|
| Ver disponibilidad | ✔ | ✔ | ✔ (sin sesión) |
| Gestionar canchas, tipos y tarifas | ✔ | — | — |
| Gestionar usuarios y roles | ✔ | — | — |
| Registrar y editar clientes | ✔ | ✔ | — |
| Crear, cancelar y marcar pagada una reserva | ✔ | ✔ | — |
| Eliminar reservas (incluidas las pagadas) | ✔ | — | — |
| Ver ingresos | ✔ | — | — |

## 9. Datos de prueba (seeders)

`php artisan migrate:fresh --seed` debe dejar:

- **Roles:** `administrador`, `recepcionista`.
- **Usuarios** (contraseña `password`): `admin@cancha.test` (administrador) y `recepcion@cancha.test` (recepcionista).
- **Tipos de cancha:** Fútbol, Vóley, Básquet.
- **Canchas:** una de fútbol que atiende **24/7** (00:00–00:00); dos con horario 08:00–23:00; una en **mantenimiento**.
- **Clientes:** 40, con `APP_FAKER_LOCALE=es_PE`, DNI de 8 dígitos únicos y teléfono que empieza con 9.
- **Reservas:** desde 3 días antes hasta 3 días después de hoy, solo en canchas disponibles, en bloques de 1 o 2 horas, **sin cruces** y dentro del horario de cada cancha; al menos una reserva en la cancha 24/7 que cruce la medianoche. Las pasadas quedan pagadas o canceladas; las futuras, pendientes o pagadas. `precio_hora` copiado de la cancha y `user_id` del recepcionista.
- Orden en `DatabaseSeeder`: `RolSeeder` → `UserSeeder` → `TipoCanchaSeeder` → `CanchaSeeder` → `ClienteSeeder` → `ReservaSeeder`.

## 10. Git y GitHub

- Repositorio: `reservas-cancha`, rama `main`.
- Commits convencionales en español: `feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`. Un commit por bloque de guía.
- Nunca se suben `.env`, `.env.testing`, `vendor/`, `node_modules/` ni `public/build/` (están en `.gitignore`).
- Todo commit generado por Claude Code termina con la línea `Co-Authored-By` que indique la herramienta.

## 11. Documentación del proyecto

| Archivo | Contenido |
|---|---|
| `docs/modelo-datos.md` | Diccionario de datos y decisiones del modelo |
| `docs/conexion.md` | Conexión a Supabase (sin contraseña) |
| `docs/temas/*.md` | Un archivo por módulo/guía con su alcance y criterios de aceptación |
| `.claude/agents/*.md` | Subagentes con sus permisos y responsabilidades |

## 12. Fuera del alcance (Fase 1)

Pagos en línea, facturación electrónica, notificaciones por WhatsApp/SMS/correo, reservas recurrentes, descuentos o tarifas por horario, y app móvil nativa.
