# Modelo de datos — Sistema de reservas de cancha deportiva (Fase 1)

Motor: PostgreSQL (Supabase). Tablas creadas con migraciones de Laravel. Datos iniciales con seeders.
Zona horaria de la aplicación: America/Lima (`'timezone' => 'America/Lima'` en `config/app.php`). Los timestamps de reservas se guardan como hora local (timestamp without time zone).

## Decisiones tomadas
- 6 tablas de negocio: roles, users, tipos_cancha, canchas, clientes, reservas.
- Rol y tipo de cancha en tablas catálogo (escalabilidad: agregar opciones sin cambiar la estructura). Se aparta del `enum users.rol` de la Guía 02 del docente: se adaptan el bloque B de esa guía y la Guía 05.
- La FK del rol se llama `rol_id` (modelo `Rol`, relación `User::rol()`), para que Laravel deduzca la FK y el código quede en español.
- Estados (`canchas.estado`, `reservas.estado`) con `$table->enum(...)` (en PostgreSQL: varchar + CHECK): están fijados por reglas de negocio (RN05, RN06, RN07, RN09).
- `reservas` NO guarda `horas` ni `total` (datos derivados, romperían 3FN). Se calculan con accessors en el modelo `Reserva`.
- `reservas.precio_hora` SÍ se guarda: es el precio histórico al momento de reservar (RN04).
- La disponibilidad por hora NO se guarda: se calcula (horario de atención de la cancha − reservas no canceladas − horas pasadas) en `DisponibilidadService`.
- Todas las FK con `ON DELETE RESTRICT` (RN10).
- Las canchas pueden atender 24/7 y las reservas pueden cruzar la medianoche: por eso la reserva usa `inicio` y `fin` como timestamp (fecha + hora) en lugar de `fecha` + `hora_inicio` + `hora_fin`.

## Modelos Eloquent
| Tabla | Modelo | Nota |
|---|---|---|
| roles | `Rol` | `$table = 'roles'`; FK con `constrained('roles')` |
| users | `User` | conserva `#[Fillable]` de Laravel 13; relación `rol()` |
| tipos_cancha | `TipoCancha` | `$table = 'tipos_cancha'`; FK con `constrained('tipos_cancha')` |
| canchas | `Cancha` | relación `tipoCancha()` |
| clientes | `Cliente` | |
| reservas | `Reserva` | relaciones `cancha()`, `cliente()`, `user()`; accessors `horas` y `total` |

Orden de migraciones: roles → add_rol_id_to_users → tipos_cancha → canchas → clientes → reservas.

## Relaciones (1:N)
- roles → users
- tipos_cancha → canchas
- canchas → reservas
- clientes → reservas
- users → reservas (usuario que registró, RN11)

## Diccionario de datos

### roles
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| nombre | varchar(50) | No | UNIQUE |
| descripcion | varchar(255) | Sí | |
| created_at, updated_at | timestamp | Sí | |

Seeder: `administrador`, `recepcionista`.

### users
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| rol_id | bigint | No | FK → roles.id, RESTRICT, índice |
| name | varchar(255) | No | |
| email | varchar(255) | No | UNIQUE |
| email_verified_at | timestamp | Sí | (estándar Laravel) |
| password | varchar(255) | No | hash bcrypt (RNF01) |
| remember_token | varchar(100) | Sí | (estándar Laravel) |
| created_at, updated_at | timestamp | Sí | |

Seeder (contraseña `password`): `admin@cancha.test` (administrador) y `recepcion@cancha.test` (recepcionista).

### tipos_cancha
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| nombre | varchar(50) | No | UNIQUE |
| descripcion | varchar(255) | Sí | |
| created_at, updated_at | timestamp | Sí | |

Seeder: `Fútbol`, `Vóley`, `Básquet`.

### canchas
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| tipo_cancha_id | bigint | No | FK → tipos_cancha.id, RESTRICT, índice |
| nombre | varchar(100) | No | UNIQUE |
| precio_hora | numeric(8,2) | No | CHECK > 0 |
| hora_apertura | time | No | minutos y segundos en 00 |
| hora_cierre | time | No | minutos y segundos en 00 |
| estado | enum | No | DEFAULT 'disponible', valores 'disponible', 'mantenimiento' (RN09) |
| created_at, updated_at | timestamp | Sí | |

Interpretación del horario de atención:
- `hora_cierre > hora_apertura` → horario dentro del mismo día (ej. 08:00–22:00).
- `hora_cierre < hora_apertura` → horario que cruza la medianoche (ej. 18:00–02:00).
- `hora_cierre = hora_apertura` → atiende 24 horas (ej. 00:00–00:00).

### clientes
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| dni | char(8) | No | UNIQUE, CHECK dni ~ '^[0-9]{8}$' (RN08) |
| nombres | varchar(100) | No | |
| apellidos | varchar(100) | No | |
| telefono | varchar(15) | No | |
| created_at, updated_at | timestamp | Sí | |

### reservas
| Columna | Tipo | Nulo | Restricciones |
|---|---|---|---|
| id | bigint | No | PK |
| cancha_id | bigint | No | FK → canchas.id, RESTRICT |
| cliente_id | bigint | No | FK → clientes.id, RESTRICT, índice |
| user_id | bigint | No | FK → users.id, RESTRICT, índice (RN11) |
| inicio | timestamp | No | minutos y segundos en 00 (RN02) |
| fin | timestamp | No | CHECK fin > inicio; minutos y segundos en 00 (RN02) |
| precio_hora | numeric(8,2) | No | CHECK > 0, copia del precio de la cancha (RN04) |
| estado | enum | No | DEFAULT 'pendiente', valores 'pendiente', 'pagada', 'cancelada' (RN05) |
| created_at, updated_at | timestamp | Sí | |

Índices: (cancha_id, inicio), (inicio, estado).

CHECK de horas completas:
```sql
CHECK (date_trunc('hour', inicio) = inicio AND date_trunc('hour', fin) = fin)
```

Restricción anti-cruce (RN01), requiere extensión `btree_gist` (ya activada en Supabase):
```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE reservas ADD CONSTRAINT reservas_sin_cruce
  EXCLUDE USING gist (
    cancha_id WITH =,
    tsrange(inicio, fin) WITH &&
  ) WHERE (estado <> 'cancelada');
```

Calculados en el modelo `Reserva` (accessors):
- `horas` = (fin − inicio) en horas
- `total` = horas × precio_hora (RF06)

Reportes por día (ingresos, reservas del día): se filtra por el rango `[día 00:00, día+1 00:00)` sobre `inicio`. Una reserva que cruza la medianoche cuenta en el día en que empieza.

## Reglas que se validan en Laravel (no en la BD)
- RN03: inicio no pasado y toda la reserva dentro del horario de atención de la cancha (considerando horarios que cruzan medianoche y 24 h) (Form Request).
- RN05/RN06: transiciones de estado válidas (pendiente → pagada | cancelada; cancelada no se reactiva).
- RN07: reserva pagada no se modifica; solo el administrador la elimina (Policy).
- RN09: solo se reserva una cancha en estado 'disponible'.
- RN01 también se valida en el Form Request para dar mensaje en español; la restricción de BD es la última barrera ante concurrencia.

## Disponibilidad (DisponibilidadService)
Para una cancha y una fecha: generar los bloques de 1 hora del día según el horario de atención (24 bloques si atiende 24 h), quitar los que se cruzan con reservas no canceladas (incluidas las que empezaron el día anterior y terminan ese día) y, si la fecha es hoy, quitar las horas ya pasadas.

## Escenario de datos de prueba (aprobado)
- Tipos: Fútbol, Vóley, Básquet.
- Canchas: una de fútbol 24/7 (00:00–00:00), dos de 08:00 a 23:00 y una en mantenimiento.
- 40 clientes con datos peruanos (es_PE).
- Reservas de −3 a +3 días respecto de hoy, en bloques de 1 o 2 horas, sin cruces y dentro del horario; al menos una cruza la medianoche en la cancha 24/7. Pasadas: pagada o cancelada. Futuras: pendiente o pagada.

## Tablas propias de Laravel/Sanctum (no son del negocio)
personal_access_tokens, sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches, failed_jobs, migrations.
