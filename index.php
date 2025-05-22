<?php
// --- Conexión a SQL Server ---
$serverName = "tcp:hector-server.database.windows.net,1433";
$connectionInfo = [
    "UID" => "hector",
    "pwd" => "Mario-12345", // Reemplaza con tu contraseña real
    "Database" => "formulario_app",
    "LoginTimeout" => 30,
    "Encrypt" => 1,
    "TrustServerCertificate" => 0
];

$conexion = sqlsrv_connect($serverName, $connectionInfo);

if ($conexion === false) {
    die("Error de conexión a SQL Server: " . print_r(sqlsrv_errors(), true));
}

// --- Insertar datos si se envió el formulario ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombreUsuario = $_POST['nombre'];
    $correoUsuario = $_POST['correo'];

    if (!empty($nombreUsuario) && !empty($correoUsuario)) {
        $sql = "INSERT INTO usuarios (nombre, correo) VALUES (?, ?)";
        $params = array($nombreUsuario, $correoUsuario);
        $stmt = sqlsrv_prepare($conexion, $sql, $params);
        if ($stmt) {
            sqlsrv_execute($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Formulario PHP - Captura y Consulta</title>
</head>
<body>
    <h2>Formulario de Captura</h2>
    <form method="POST" action="">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br><br>
        <label>Correo:</label><br>
        <input type="email" name="correo" required><br><br>
        <input type="submit" value="Guardar">
    </form>

    <h2>Consulta de Información</h2>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Correo</th>
        </tr>
        <?php
        $query = "SELECT id, nombre, correo FROM usuarios";
        $resultado = sqlsrv_query($conexion, $query);

        if ($resultado) {
            $hayRegistros = false;
            while ($fila = sqlsrv_fetch_array($resultado, SQLSRV_FETCH_ASSOC)) {
                $hayRegistros = true;
                echo "<tr>
                        <td>{$fila['id']}</td>
                        <td>{$fila['nombre']}</td>
                        <td>{$fila['correo']}</td>
                      </tr>";
            }
            if (!$hayRegistros) {
                echo "<tr><td colspan='3'>No hay registros.</td></tr>";
            }
        } else {
            echo "<tr><td colspan='3'>Error en la consulta.</td></tr>";
        }

        sqlsrv_close($conexion);
        ?>
    </table>
</body>
</html>
