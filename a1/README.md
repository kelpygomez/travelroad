# ACTIVIDAD POSTGRESQL
## Índice:
- PostgreSQL
- Aplicación PHP
    - Entorno de desarrollo
    - Entorno de producción
    - Despliegue

### 1. PostgreSQL
Primero instalamos PostgreSQL tanto en la máquina local como en la remota usando ssh:
```
sudo apt update
```
```
sudo apt install -y apt-transport-https
```
```
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
| sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
```
```
sudo apt install -y postgresql-15
```
Instalamos Net-tools para comprobar la configuración de Postgre.
```
sudo apt install net-tools
```
```
sudo netstat -napt | grep postgres | grep -v tcp6
```
Iniciamos sesión en PostgreSQL
```
sudo -u postgres psql
```
Creamos tanto el usuario que usaremos en desarrollo como el de producción:
- Producción:
```
postgres=# CREATE USER travelroad_user WITH PASSWORD 'dpl0000';
CREATE ROLE
```
```
postgres=# CREATE DATABASE travelroad WITH OWNER travelroad_user;
CREATE DATABASE
```
```
postgres=# \q
```
- Desarrollo:
```
postgres=# CREATE USER develop_user WITH PASSWORD 'tranquilidad';
CREATE ROLE
```
```
postgres=# CREATE DATABASE develop_user WITH OWNER travelroad_user;
CREATE DATABASE
```
```
postgres=# \q
```
Creamos las tablas e importamos los datos en ambas bases de datos:
```
travelroad=> CREATE TABLE places(
id SERIAL PRIMARY KEY,
name VARCHAR(255),
visited BOOLEAN);
CREATE TABLE
```
```
curl -o /tmp/places.csv https://raw.githubusercontent.com/sdelquin/dpl/main/ut4/files/places.csv
```
```
psql -h localhost -U <nombre de usuario> -d travelroad \
-c "\copy places(name, visited) FROM '/tmp/places.csv' DELIMITER ','"
```
Instalamos pgAdmin en ambas máquinas usando el dominio 'pgadmin.local' en la de desarrollo y 'pgadmin.kelpy.arkania.es' en la de producción.
```
echo 'export PATH=~/.local/bin:$PATH' >> .bashrc && source .bashrc
```
```
sudo mkdir /var/lib/pgadmin
sudo mkdir /var/log/pgadmin
sudo chown $USER /var/lib/pgadmin
sudo chown $USER /var/log/pgadmin
```
```
cd $HOME
```
```
python -m venv pgadmin4
```
```
source pgadmin4/bin/activate
```
```
(pgadmin4) alu@a109pc14dsw:~$ pip install pgadmin4
```
```
(pgadmin4) alu@a109pc14dsw:~$ pgadmin4
NOTE: Configuring authentication for SERVER mode.

Enter the email address and password to use for the initial pgAdmin user account:

Email address: <un email para cada máquina>
Password:
Retype password:
pgAdmin 4 - Application Initialisation
```
Instalamos Gunicorn y creamos un servicio para pgAdmin:
```
pip install gunicorn
```
```
gunicorn \
--chdir pgadmin4/lib/python3.11/site-packages/pgadmin4 \
--bind unix:/tmp/pgadmin4.sock pgAdmin4:app
```
Abrimos una pestaña nueva para crear el servicio.
- En desarrollo:
```
sudo nano /etc/nginx/conf.d/pgadmin.conf
```
```
server {
    server_name pgadmin.local;

    location / {
        proxy_pass http://unix:/tmp/pgadmin4.sock;  # socket UNIX
    }
}
```
```
[Unit]
Description=pgAdmin

[Service]
User=alu
ExecStart=/bin/bash -c '\
source /home/alu/pgadmin4/bin/activate && \
gunicorn --chdir /home/alu/pgadmin4/lib/python3.11/site-packages/pgadmin4 \
--bind unix:/tmp/pgadmin4.sock \
pgAdmin4:app'
Restart=always

[Install]
WantedBy=multi-user.target

```
- En producción:
```
sudo nano /etc/nginx/conf.d/pgadmin.conf
```
```
server {
    server_name pgadmin.kelpy.arkania.es;

    location / {
        proxy_pass http://unix:/tmp/pgadmin4.sock;  # socket UNIX
    }
}
```
```
[Unit]
Description=pgAdmin

[Service]
User=kelpy
ExecStart=/bin/bash -c '\
source /home/kelpy/pgadmin4/bin/activate && \
gunicorn --chdir /home/kelpy/pgadmin4/lib/python3.11/site-packages/pgadmin4 \
--bind unix:/tmp/pgadmin4.sock \
pgAdmin4:app'
Restart=always

[Install]
WantedBy=multi-user.target

```
Modificamos /etc/hosts para añadir las dos direcciones creadas:
```
127.0.0.1       localhost
10.0.2.15       pgadmin.local
172.205.248.47  pgadmin.kelpy.arkania.es
```
Seguimos los pasos para configurar los servidores TravelRoad, con las credenciales de cada usuario creado en pgAdmin.
(En producción es travelroad_user y en desarrollo es develop_user)

<b style=color:yellow>- Las URLS de pgAdmin son 'pgadmin.local' en desarrollo y 'pgadmin.kelpy.arkania.es' en producción.</b>
### 2. Aplicación PHP
#### 2.1 Entorno de desarrollo
Instalamos la función pg_connect en ambas máquinas. Yo hice todo en una máquina y después copié esa carpeta en la otra.
```
sudo apt install -y php8.2-pgsq
```
Creamos la carpeta que añadiremos más adelante al repositorio con la aplicación index.php y la configuración config.php.
La ubicación de esa carpeta sera <b style=color:yellow;>usr/share/nginx/travelroad</b>

<b style=color:lightblue>INDEX.PHP:</b> 

[URL al código fuente de la aplicación](<https://github.com/kelpygomez/travelroad/blob/main/a1/travelroad-local/index.php>)
```
<?php
include('config.php');

$conn = pg_connect("host=$db_host dbname=$db_name user=$db_user password=$db_password");
if (!$conn) {
    echo "Error: No se pudo conectar a la base de datos.\n";
    exit;
}

$result_visited = pg_query($conn, "SELECT * FROM places WHERE visited = 't'");
if (!$result_visited) {
    echo "Error en la consulta de lugares visitados.\n";
    exit;
}

$result_not_visited = pg_query($conn, "SELECT * FROM places WHERE visited = 'f'");
if (!$result_not_visited) {
    echo "Error en la consulta de lugares no visitados.\n";
    exit;
}

echo "<h1>My Travel Bucket List</h1>";

echo "<h2>Places I'd Like to Visit</h2>";
echo "<ul>";
while ($row_not_visited = pg_fetch_assoc($result_not_visited)) {
    echo "<li>{$row_not_visited['name']}</li>";
}
echo "</ul>";

echo "<h2>Places I've Already Been To</h2>";
echo "<ul>";
while ($row_visited = pg_fetch_assoc($result_visited)) {
    echo "<li>{$row_visited['name']}</li>";
}
echo "</ul>";
pg_close($conn);
?>
```
<b style=color:lightblue>CONFIG.PHP:</b> 
```
<?php
$db_host = 'localhost';
$db_name = 'travelroad';
$db_user = 'develop_user' o 'travelroad_user';
$db_password = 'tranquilidad' o 'dpl0000';
?>

```
Configuramos el servidor en ambas máquinas en <b style=color:yellow> sudo nano etc/nginx/conf.d/travelroad.conf</b>

<b style=color:lightblue>TRAVELROAD.CONF:</b>
```
server {
    server_name php.travelroad.local o php.travelroad.kelpy.arkania.es;
    root /usr/share/nginx/travelroad;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

``` 
#### 2.2 Entorno de producción
Subimos los cambios al repositorio 'travelroad' y lo clonamos en la máquina de producción para hacer los mismos pasos en ella, pero cambiando las credenciales de usuario y el nombre del servidor en los documentos <b>config.php y travelroad.conf</b>

La aplicación estara desplegada en [este enlace](<http://php.travelroad.kelpy.arkania.es>).

#### 2.3 Despliegue
Creamos un shell-script dentro del repositorio, yo lo he alojado en la carpeta destinada a los archivos locales, que nos permita realizar un pull del repositorio remoto hacia el repositorio oficial.

<b style=color:lightblue>DEPLOY.SH:</b>
```
#!/bin/bash

# Conexión SSH y ejecución de git pull en la máquina remota
sudo ssh kelpy@172.205.248.47 'cd /home/kelpy/Escritorio/travelroad; git pull /home/kelpy/Escritorio/travelroad/.git/ main'
```