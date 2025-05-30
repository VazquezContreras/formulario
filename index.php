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

// --- Función para detectar WAF ---
function detectarWAF() {
    $wafActivo = false;
    
    // Intento de payload que sería bloqueado por el WAF
    $payload = '<script>alert("XSS Test")</script>';
    
    // Verificar si el WAF bloquea el payload en los headers
    if (isset($_SERVER['HTTP_X_AZURE_FDID']) {
        // Header específico de Azure Front Door con WAF
        $wafActivo = true;
    }
    
    // Verificar si hay headers de seguridad de Azure
    if (isset($_SERVER['HTTP_X_SECURITY_PROVIDER']) {
        $wafActivo = true;
    }
    
    // Intento de inyección SQL simulada
    if (!$wafActivo) {
        try {
            $testSql = "SELECT * FROM usuarios WHERE 1=1 AND 1=CONVERT(int,(SELECT table_name FROM information_schema.tables))";
            $stmt = sqlsrv_query($GLOBALS['conexion'], $testSql);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                if (isset($errors[0]['code']) && $errors[0]['code'] == 40500) {
                    $wafActivo = true;
                }
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'WAF') !== false || 
                strpos($e->getMessage(), 'blocked') !== false) {
                $wafActivo = true;
            }
        }
    }
    
    return $wafActivo;
}

$wafStatus = detectarWAF();

// --- Insertar datos si se envió el formulario ---
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
    <title>Formulario PHP - Detección WAF Azure</title>
    <style>
        .waf-status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            font-size: 18px;
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
        .waf-test {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="waf-status <?php echo $wafStatus ? 'waf-on' : 'waf-off'; ?>">
        <?php 
        if ($wafStatus) {
            echo 'WAF DE AZURE: ACTIVADO - Su aplicación está protegida contra ataques.';
            echo '<script>console.log("WAF ACTIVO: Azure Web Application Firewall está protegiendo esta aplicación");</script>';
        } else {
            echo 'WAF DE AZURE: DESACTIVADO - Su aplicación podría ser vulnerable a ataques.';
            echo '<script>console.warn("WAF INACTIVO: Azure Web Application Firewall no está protegiendo esta aplicación");</script>';
        }
        ?>
    </div>

    <div class="waf-test">
        <h3>Prueba de WAF</h3>
        <p>Para verificar manualmente el estado del WAF:</p>
        <ol>
            <li><strong>Con WAF activado:</strong> Intente ingresar <code>&lt;script&gt;alert("test")&lt;/script&gt;</code> en los campos del formulario</li>
            <li><strong>Con WAF desactivado:</strong> El script anterior se guardará sin problemas</li>
        </ol>
    </div>

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

    <?php if ($wafStatus): ?>
        <script>alert("EL WAF ESTA PRENDIDO");</script>
    <?php else: ?>
        <script>alert("EL WAF ESTA APAGADO");</script>
    <?php endif; ?>
</body>
</html>
