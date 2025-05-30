<?php
// --- Conexión a SQL Server ---
$serverName = "tcp:bd-app-server.database.windows.net,1433";
$connectionInfo = [
    "UID" => "Hector",
    "pwd" => "Mario-12345",
    "Database" => "bd_app",
    "LoginTimeout" => 30,
    "Encrypt" => 1,
    "TrustServerCertificate" => 0
];

$conexion = sqlsrv_connect($serverName, $connectionInfo);

if ($conexion === false) {
    die("Error de conexión a SQL Server: " . print_r(sqlsrv_errors(), true));
}

// --- Simulación de detección de WAF ---
$wafActivo = false;

// Intento de inyección XSS para detectar WAF
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['test_waf'])) {
    $testPayload = '<script>alert("TEST WAF")</script>';
    
    // Intento de inyección SQL para detectar WAF
    $sqlTest = "SELECT * FROM usuarios WHERE nombre = '" . $testPayload . "'";
    $stmt = sqlsrv_query($conexion, $sqlTest);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        // Si hay errores específicos de WAF (esto puede variar según la implementación)
        if (isset($errors[0]['code']) && $errors[0]['code'] == 40500) {
            $wafActivo = true;
        }
    }
}

// --- Insertar datos si se envió el formulario normal ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['test_waf'])) {
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
    <title>Formulario PHP - Prueba WAF Azure</title>
    <style>
        .waf-status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .waf-on {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .waf-off {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="waf-status <?php echo $wafActivo ? 'waf-on' : 'waf-off'; ?>">
        <?php 
        if ($wafActivo) {
            echo '<script>alert("EL WAF ESTA PRENDIDO")</script>';
            echo 'WAF DE AZURE: ACTIVADO - Su aplicación está protegida contra ataques.';
        } else {
            echo '<script>alert("EL WAF ESTA APAGADO")</script>';
            echo 'WAF DE AZURE: DESACTIVADO - Su aplicación es vulnerable a ataques.';
        }
        ?>
    </div>

    <h2>Formulario de Captura</h2>
    <form method="POST" action="">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br><br>
        <label>Correo:</label><br>
        <input type="email" name="correo" required><br><br>
        <input type="submit" value="Guardar">
    </form>

    <!-- Formulario oculto para probar WAF -->
    <form method="POST" action="" style="display: none;">
        <input type="hidden" name="test_waf" value="1">
        <input type="submit" id="wafTestSubmit">
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
                        <td>".htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8')."</td>
                        <td>".htmlspecialchars($fila['correo'], ENT_QUOTES, 'UTF-8')."</td>
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

    <script>
        // Ejecutar prueba WAF automáticamente al cargar la página
        window.onload = function() {
            document.getElementById('wafTestSubmit').click();
        };
    </script>
</body>
</html>
