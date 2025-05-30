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

// --- Detección de WAF ---
$wafActivo = false;
$testResultado = '';

// Procesar prueba WAF
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['waf_test'])) {
    $testPayload = $_POST['waf_test'];
    
    // Intento de ejecución directa (para probar WAF)
    ob_start();
    echo $testPayload;
    $output = ob_get_clean();
    
    // Verificar si el payload fue ejecutado o bloqueado
    if (strpos($output, '<script>') !== false) {
        $wafActivo = false;
        $testResultado = '<div style="color:red;font-weight:bold;">WAF DESACTIVADO: El script se ejecutó correctamente</div>';
        echo '<script>alert("EL WAF ESTA APAGADO");</script>';
    } else {
        $wafActivo = true;
        $testResultado = '<div style="color:green;font-weight:bold;">WAF ACTIVADO: El script fue bloqueado</div>';
        echo '<script>alert("EL WAF ESTA PRENDIDO");</script>';
    }
}

// --- Insertar datos si se envió el formulario normal ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['waf_test'])) {
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
        .waf-test-section {
            margin: 30px auto;
            padding: 20px;
            background: #f0f0f0;
            border: 2px solid red;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <!-- ==== [SECCIÓN DE PRUEBA WAF ] ==== -->
    <div class="waf-test-section">
        <h3>Prueba WAF</h3>
        <?php if (!empty($testResultado)) echo $testResultado; ?>
        
        <form method="post">
            <input
                type="text"
                name="waf_test"
                style="width: 95%; padding: 4px; margin-bottom: 4px;"
                placeholder='Ingrese: &lt;script&gt;alert("TEST")&lt;/script&gt;'
            >
            <button type="submit">Probar WAF</button>
        </form>
        
        <p><strong>Instrucciones:</strong></p>
        <ol>
            <li>Con WAF <strong>apagado</strong>, ingrese: <code>&lt;script&gt;alert("EL WAF ESTA APAGADO")&lt;/script&gt;</code></li>
            <li>Con WAF <strong>prendido</strong>, ingrese: <code>&lt;script&gt;alert("EL WAF ESTA PRENDIDO")&lt;/script&gt;</code></li>
        </ol>
    </div>

    <div class="waf-status <?php echo $wafActivo ? 'waf-on' : 'waf-off'; ?>">
        <?php 
        if ($wafActivo) {
            echo 'WAF DE AZURE: ACTIVADO - Su aplicación está protegida contra ataques.';
        } else {
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
</body>
</html>
