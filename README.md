# Sistema web de reservas de una cancha deportiva
 
Proyecto de DSI406 Tecnología Backend (Laravel + PostgreSQL en Supabase).
 
## Requisitos
- PHP 8.3 o superior con pdo_pgsql
- Composer 2
- Un proyecto en Supabase con la extensión btree_gist activada
 
## Instalación
1. git clone https://github.com/TU-USUARIO/reservas-cancha.git
2. cd reservas-cancha
3. composer install
4. copy .env.example .env
5. Completar DB_HOST, DB_USERNAME y DB_PASSWORD con los datos
   del Session pooler de Supabase (botón Connect)
6. php artisan key:generate
7. php artisan migrate
8. php artisan serve
 
Autor: Carlos Marcial Olivera Palomino

