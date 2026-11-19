# Tema 02 · Base de datos: migraciones, modelos, factories y seeders

## 1. Objetivo
Convertir el modelo de datos de `docs/modelo-datos.md` en código versionado: migraciones que crean las tablas en Supabase con todas sus restricciones, modelos Eloquent con sus relaciones, factories y seeders con datos de prueba realistas, y comprobar que la propia base de datos rechaza los datos que violan las reglas de negocio.

## 2. Guía de referencia y adaptación
Guía 02 del docente (Posta de Salud). Se mantienen sus 7 bloques (A–G), su forma de verificar y sus commits por bloque. Cambios:

| Guía 02 (posta) | Este proyecto |
|---|---|
| `users.rol` como `enum` | Tabla `roles` + `users.rol_id` (FK). Bloque B crea `roles` y luego modifica `users` |
| pacientes, personal_medico, citas, atenciones | tipos_cancha, canchas, clientes, reservas |
| `PersonalMedico` y `Atencion` necesitan `$table` | `Rol` (`roles`) y `TipoCancha` (`tipos_cancha`) necesitan `$table` y su migración creada aparte |
| Citas de 30 min, `fecha_hora` / `fecha_hora_fin` | Reservas por horas completas, `inicio` / `fin` (`dateTime`), pueden cruzar la medianoche |
| Cruces solo en la aplicación (Guía 06) | Cruces **también en la base**: `EXCLUDE USING gist` con `btree_gist` |
| Solo `UNIQUE`, FK y `enum` | Además `CHECK` con `DB::statement` (horas completas, DNI, precio > 0) |
| Seeder de agenda 8:00–12:00 | Seeder de reservas según el horario de cada cancha, incluida una 24/7 |
| Verificación en pgAdmin | Verificación con `db:table` y el Table Editor de Supabase |
| 4 intentos de romper reglas (bloque G) | 6 intentos, incluido el cruce (SQLSTATE 23P01) |

Estilo de modelos: los nuevos usan `protected $table`, `protected $fillable` y `casts()`; `User` conserva el atributo `#[Fillable([...])]` de Laravel 13.

## 3. Requerimientos y reglas cubiertos
Base de datos de todos los RF. Protegidas directamente en la BD: RN01 (sin cruce), RN02 (horas completas), RN05 (estados válidos, nace pendiente), RN08 (DNI), RN09 (estado de cancha válido), RN10 (FK restrict), RN11 (`user_id` obligatorio). RNF04 (migraciones y seeders).

## 4. Dependencias
Tema 01 terminado: proyecto conectado a Supabase y extensión `btree_gist` activada.

## 5. Qué se construye

### Bloque A · Modelo de datos (sin código)
- Confirmar que `docs/modelo-datos.md` es la referencia; no se escribe código.

### Bloque B · Roles y rol de los usuarios
- `php artisan make:model Rol` + `php artisan make:migration create_roles_table` (no usar `-m`: generaría `rols`).
- `php artisan make:migration add_rol_id_to_users_table --table=users`:
  - `up()`: `foreignId('rol_id')->constrained('roles')->restrictOnDelete()`.
  - `down()`: `dropConstrainedForeignId('rol_id')`.
  - Si `users` ya tiene filas, la columna NOT NULL fallará: en desarrollo, **preguntar al usuario** antes de usar `migrate:fresh`.
- `Rol`: `$table = 'roles'`, `$fillable = ['nombre', 'descripcion']`, `users(): HasMany`.
- `User`: agregar `'rol_id'` en `#[Fillable]`; `rol(): BelongsTo`; `esAdministrador(): bool`; `esRecepcionista(): bool`; `reservas(): HasMany`.
- `UserFactory`: asignar `rol_id` del rol `recepcionista` (creándolo si no existe) para que la factory siga funcionando.
- Probar `migrate`, `migrate:rollback --step=1` y `migrate` otra vez.
- **Commit:** `feat: agrega tabla de roles y rol a los usuarios`

### Bloque C · Migraciones del sistema
Comandos, uno por uno y en este orden (el orden de las FK depende de la fecha del archivo):
```
php artisan make:model TipoCancha -f
php artisan make:migration create_tipos_cancha_table
php artisan make:model Cancha -mf
php artisan make:model Cliente -mf
php artisan make:model Reserva -mf
```
- Columnas, tipos y restricciones: exactamente las de `docs/modelo-datos.md`.
- Estados con `enum(...)->default(...)`.
- `CHECK` y `EXCLUDE` con `DB::statement` dentro de `up()` (código en `CLAUDE.md`, sección 5). En `down()`, `Schema::dropIfExists` basta.
- Índices de `reservas`: `(cancha_id, inicio)` y `(inicio, estado)`.
- **Commit:** `feat: crea migraciones de tipos de cancha, canchas, clientes y reservas`

### Bloque D · Modelos y relaciones
- `TipoCancha`: `$table = 'tipos_cancha'`, `canchas(): HasMany`.
- `Cancha`: `tipoCancha(): BelongsTo`, `reservas(): HasMany`; casts `precio_hora` → `decimal:2`.
- `Cliente`: `reservas(): HasMany`.
- `Reserva`: `cancha()`, `cliente()`, `user()` (`BelongsTo`); casts `inicio`/`fin` → `datetime`, `precio_hora` → `decimal:2`; accessors `horas` y `total` con `Attribute::make`.
- Prueba en Tinker: crear un cliente y una reserva a mano y leer `$reserva->horas` y `$reserva->total`.
- **Commit:** `feat: define modelos Eloquent y sus relaciones`

### Bloque E · Factories
- `TipoCanchaFactory`, `CanchaFactory` (estados `veinticuatroHoras()` y `enMantenimiento()`), `ClienteFactory` (DNI único de 8 dígitos, teléfono `9########`, datos es_PE), `ReservaFactory` (inicio en hora completa futura, 1–2 horas, `precio_hora` copiado de la cancha; estados `pagada()` y `cancelada()`).
- Probar con `make()` y `create()` en Tinker.
- **Commit:** `feat: agrega factories con datos de prueba peruanos`

### Bloque F · Seeders
- `RolSeeder`, `UserSeeder`, `TipoCanchaSeeder`, `CanchaSeeder`, `ClienteSeeder`, `ReservaSeeder`, llamados en ese orden desde `DatabaseSeeder` (eliminar el Test User de ejemplo).
- Escenario: el de `CLAUDE.md`, sección 9.
- `ReservaSeeder`: para cada cancha disponible y cada día de −3 a +3, recorre las horas dentro de su horario, deja huecos al azar, crea bloques de 1–2 h sin cruces y fuerza una reserva 23:00 → 01:00 en la cancha 24/7. Pasadas: pagada (≈80 %) o cancelada; futuras: pendiente o pagada. `user_id` del recepcionista.
- Ejecutar `php artisan migrate:fresh --seed` **solo después de que el usuario lo autorice** (borra la base de Supabase).
- **Commit:** `feat: agrega seeders de roles, usuarios, canchas, clientes y reservas`

### Bloque G · Comprobar las reglas en la base
Desde Tinker, cada intento debe fallar y no cambiar datos:

| Intento | Resultado esperado |
|---|---|
| Cliente con DNI repetido | 23505 (UNIQUE) |
| Cliente con DNI `1234ABCD` | 23514 (CHECK) |
| Borrar un cliente o una cancha con reservas | 23503 (FK) |
| Reserva con estado `perdida` | 23514 (CHECK del enum) |
| Reserva de 10:30 a 11:30 | 23514 (horas completas) |
| Reserva que se cruza con otra activa de la misma cancha | **23P01** (exclusión) |

Prueba adicional: una reserva en el mismo horario que una **cancelada** sí se guarda.
- Agregar al `README.md` la sección «Datos de prueba» con el comando y los dos usuarios.
- **Commit:** `docs: documenta datos de prueba y reglas verificadas`

## 6. Criterios de aceptación
- [ ] `php artisan migrate:status` muestra todas las migraciones como Ran, en el orden de la sección 5.
- [ ] `db:table users` muestra `rol_id` con su FK; el rollback del bloque B funciona.
- [ ] `db:table reservas` muestra 3 FK, 2 índices compuestos, los CHECK y la restricción `reservas_sin_cruce`.
- [ ] En Supabase (Table Editor, esquema `public`) existen `roles`, `tipos_cancha`, `canchas`, `clientes`, `reservas`; no existen `rols` ni `tipo_canchas`.
- [ ] Tras `migrate:fresh --seed`: 2 roles, 2 usuarios, 3 tipos, 4 canchas (una 24/7, una en mantenimiento), 40 clientes y reservas sin cruces, ninguna en la cancha en mantenimiento.
- [ ] Existe al menos una reserva que cruza la medianoche en la cancha 24/7.
- [ ] `$reserva->total` = horas × `precio_hora` en Tinker.
- [ ] Los 6 intentos del bloque G fallan con el SQLSTATE esperado y la reserva sobre una cancelada se guarda.
- [ ] README con datos de prueba; 6 commits nuevos en GitHub.

## 7. Fuera de alcance de este tema
Controladores, vistas, rutas, Form Requests, Policies, autenticación, API y pruebas con Pest (temas 03 en adelante). La validación con mensajes en español de RN01–RN03 se hace en el tema 06.
