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
