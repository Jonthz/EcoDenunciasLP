<?php
// test.php - Archivo de prueba simple
echo "<h1>Test de conectividad</h1>";
echo "<p>Si ves esto, el archivo PHP se está ejecutando correctamente.</p>";
echo "<p>Servidor: " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "</p>";
echo "<p>Puerto: " . ($_SERVER['SERVER_PORT'] ?? 'unknown') . "</p>";
echo "<p>Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "</p>";
echo "<p>Hora: " . date('Y-m-d H:i:s') . "</p>";

// Probar que config.php se carga
try {
    require_once '../config/config.php';
    echo "<p style='color: green;'>✅ Config.php cargado correctamente</p>";
    echo "<p>BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'No definido') . "</p>";
    echo "<p>API_URL: " . (defined('API_URL') ? API_URL : 'No definido') . "</p>";
    echo "<p>ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'No definido') . "</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error cargando config.php: " . $e->getMessage() . "</p>";
}

// Probar conexión a base de datos
echo "<h2>Diagnóstico de Base de Datos</h2>";
try {
    require_once '../config/database.php';
    $database = new Database();
    
    // Ejecutar diagnóstico
    echo "<h3>Diagnóstico de conexión:</h3>";
    $diagnostico = $database->diagnosticarConexion();
    echo "<pre>";
    foreach ($diagnostico as $key => $value) {
        echo "<strong>$key:</strong> $value\n";
    }
    echo "</pre>";
    
    // Intentar crear la base de datos
    echo "<h3>Creación de base de datos:</h3>";
    $crear_bd = $database->crearBaseDatos();
    if ($crear_bd['success']) {
        echo "<p style='color: green;'>✅ " . $crear_bd['message'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . $crear_bd['error'] . "</p>";
    }
    
    // Intentar conexión normal
    echo "<h3>Prueba de conexión normal:</h3>";
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
        
        // Contar registros si existen las tablas
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM denuncias");
            $denuncias_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<p>Denuncias en BD: " . $denuncias_count . "</p>";
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM comentarios");
            $comentarios_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<p>Comentarios en BD: " . $comentarios_count . "</p>";
            
        } catch(PDOException $e) {
            echo "<p style='color: orange;'>⚠️ Tablas no encontradas: " . $e->getMessage() . "</p>";
            echo "<p>Ejecuta el setup para crear las tablas.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No se pudo conectar a la base de datos</p>";
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error de base de datos: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Enlaces para probar:</h2>";
echo "<ul>";
echo "<li><a href='index.php'>index.php (página principal)</a></li>";
echo "<li><a href='index.php?test=health'>index.php?test=health</a></li>";
echo "<li><a href='health'>health (directo)</a></li>";
echo "<li><a href='docs'>docs (directo)</a></li>";
echo "</ul>";
?>
