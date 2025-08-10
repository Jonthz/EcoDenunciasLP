<?php
// config/config.php - Configuración final para EcoDenuncia
// Jonathan Paul Zambrano Arriaga

// Detectar entorno (desarrollo vs producción)
$is_local = (
    $_SERVER['SERVER_NAME'] === 'localhost' || 
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'], '.local') !== false
);

// Detectar si necesitamos HTTPS (para 127.0.0.1 en algunas configuraciones)
$is_https = (
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ||
    isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1'
);

// Configuración según entorno
if ($is_local) {
    // ============= CONFIGURACIÓN DESARROLLO (XAMPP) =============
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    $protocol = $is_https ? 'https' : 'http';
    
    // Solo agregar puerto si no es el estándar para el protocolo
    $port_suffix = '';
    if (($protocol === 'http' && $port != '80') || 
        ($protocol === 'https' && $port != '443')) {
        $port_suffix = ':' . $port;
    }
    
    define('BASE_URL', $protocol . '://' . $_SERVER['SERVER_NAME'] . $port_suffix . '/EcoDenunciasLP/');
    define('API_URL', BASE_URL . 'api/');
    define('UPLOAD_DIR', BASE_URL . 'uploads/');
    define('ENVIRONMENT', 'development');
    
    // Mostrar errores en desarrollo
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    
} else {
    // ============= CONFIGURACIÓN PRODUCCIÓN (000webhost) =============
    define('BASE_URL', 'https://ecodenuncia-jonathan.000webhostapp.com/');
    define('API_URL', BASE_URL . 'api/');
    define('UPLOAD_DIR', BASE_URL . 'uploads/');
    define('ENVIRONMENT', 'production');
    
    // Ocultar errores en producción
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ============= CONFIGURACIÓN CORS PARA REACT =============
// Permitir requests desde el frontend de React
$allowed_origins = array(
    'http://localhost:3000',                    // React dev server
    'http://localhost:3001',                    // React alternativo
    'https://ecodenuncia-app.netlify.app',      // Frontend en producción
    'https://ecodenuncia-frontend.vercel.app'   // Frontend alternativo
);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *'); // Solo para desarrollo
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=UTF-8');

// Manejar preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============= CONFIGURACIÓN GENERAL =============
date_default_timezone_set('America/Guayaquil');

// Configuración de uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB máximo
define('ALLOWED_EXTENSIONS', array('jpg', 'jpeg', 'png', 'gif'));

// Configuración de paginación
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 50);

// ============= FUNCIONES GLOBALES =============

/**
 * Enviar respuesta JSON estandarizada
 */
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    
    // Estructura base de respuesta
    $response = array(
        'timestamp' => date('c'), // ISO 8601
        'environment' => ENVIRONMENT,
        'success' => $status_code < 400
    );
    
    // Combinar con datos proporcionados
    $response = array_merge($response, $data);
    
    // Formatear JSON según entorno
    $json_flags = JSON_UNESCAPED_UNICODE;
    if (ENVIRONMENT === 'development') {
        $json_flags |= JSON_PRETTY_PRINT;
    }
    
    echo json_encode($response, $json_flags);
    exit();
}

/**
 * Validar y limpiar input
 */
function validateInput($data) {
    if (is_array($data)) {
        return array_map('validateInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Logging de errores
 */
function logError($message, $context = array()) {
    $log_entry = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'environment' => ENVIRONMENT,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    
    // Crear directorio de logs si no existe
    $log_dir = dirname(__DIR__) . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/api_' . date('Y-m-d') . '.log';
    $log_message = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Validar parámetros requeridos
 */
function validateRequiredParams($data, $required_params) {
    $missing = array();
    
    foreach ($required_params as $param) {
        if (!isset($data[$param]) || empty(trim($data[$param]))) {
            $missing[] = $param;
        }
    }
    
    if (!empty($missing)) {
        sendJsonResponse(array(
            'success' => false,
            'message' => 'Parámetros requeridos faltantes',
            'missing_params' => $missing,
            'required_params' => $required_params
        ), 400);
    }
    
    return true;
}

/**
 * Generar respuesta de error estándar
 */
function errorResponse($message, $code = 400, $details = null) {
    $response = array(
        'success' => false,
        'message' => $message,
        'error_code' => $code
    );
    
    if ($details && ENVIRONMENT === 'development') {
        $response['details'] = $details;
    }
    
    sendJsonResponse($response, $code);
}

/**
 * Validar formato de email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar ID único para archivos
 */
function generateUniqueId() {
    return uniqid() . '_' . date('YmdHis');
}

/**
 * Formatear fecha para mostrar
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Calcular diferencia de tiempo en formato legible
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace menos de un minuto';
    if ($time < 3600) return 'Hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'Hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'Hace ' . floor($time/86400) . ' días';
    
    return 'Hace más de un mes';
}

// ============= CONFIGURACIÓN DE SEGURIDAD =============

// Prevenir ataques XSS básicos
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// Rate limiting básico (solo para producción)
if (ENVIRONMENT === 'production') {
    session_start();
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $current_time = time();
    $time_window = 60; // 1 minuto
    $max_requests = 100; // máximo 100 requests por minuto
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = array();
    }
    
    // Limpiar requests antiguos
    $_SESSION['rate_limit'] = array_filter(
        $_SESSION['rate_limit'], 
        function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        }
    );
    
    // Verificar límite
    if (count($_SESSION['rate_limit']) >= $max_requests) {
        sendJsonResponse(array(
            'success' => false,
            'message' => 'Límite de requests excedido. Intente nuevamente en un minuto.',
            'error_code' => 'RATE_LIMIT_EXCEEDED'
        ), 429);
    }
    
    // Registrar request actual
    $_SESSION['rate_limit'][] = $current_time;
}

// ACTUALIZACIÓN Giovanni Sambonino - Configuración para subida de archivos de denuncias
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', '../uploads/');

// ACTUALIZACIÓN Giovanni Sambonino - Crear directorios de uploads automáticamente
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(UPLOAD_DIR . 'denuncias/')) {
    mkdir(UPLOAD_DIR . 'denuncias/', 0755, true);
}

// ============= INFORMACIÓN DE DEBUG =============
if (ENVIRONMENT === 'development') {
    // Solo en desarrollo: mostrar información útil
    define('DEBUG_INFO', array(
        'php_version' => phpversion(),
        'base_url' => BASE_URL,
        'api_url' => API_URL,
        'timezone' => date_default_timezone_get(),
        'current_time' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ));
}

// Log del inicio de la aplicación
logError('API initialized', array(
    'environment' => ENVIRONMENT,
    'base_url' => BASE_URL,
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
));
?>
