<?php
// api/index.php - Router principal para las funcionalidades de Jonathan
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../controllers/ResumenSemanalController.php';
require_once '../controllers/ComentariosController.php';

// Obtener información de la request
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Limpiar la URI removiendo el prefijo de la API
$api_prefix = '/EcoDenunciasLP/api'; // Ajustar según tu estructura
$path = str_replace($api_prefix, '', parse_url($request_uri, PHP_URL_PATH));

// Remover slash inicial si existe
$path = ltrim($path, '/');

// Instanciar controladores
$resumenController = new ResumenSemanalController();
$comentariosController = new ComentariosController();

// Función para manejar errores 404
function notFound() {
    sendJsonResponse(array(
        "success" => false,
        "message" => "Endpoint no encontrado",
        "available_endpoints" => array(
            "GET /denuncias/resumen-semanal" => "Obtener resumen semanal de denuncias",
            "POST /comentarios" => "Crear nuevo comentario",
            "GET /comentarios/{denuncia_id}" => "Obtener comentarios de una denuncia",
            "GET /docs" => "Documentación de la API"
        )
    ), 404);
}

// Router principal
try {
    switch(true) {
        
        // ============= FUNCIONALIDAD 1: RESUMEN SEMANAL =============
        case ($path === 'denuncias/resumen-semanal' && $request_method === 'GET'):
            $resumenController->obtenerResumenSemanal();
            break;
            
        // ============= FUNCIONALIDAD 2: SISTEMA DE COMENTARIOS =============
        case ($path === 'comentarios' && $request_method === 'POST'):
            $comentariosController->crearComentario();
            break;
            
        // Obtener comentarios por denuncia ID (ruta dinámica)
        case (preg_match('/^comentarios\/(\d+)$/', $path, $matches) && $request_method === 'GET'):
            $denuncia_id = $matches[1];
            $comentariosController->obtenerComentariosPorDenuncia($denuncia_id);
            break;
            
        // ============= ENDPOINTS AUXILIARES =============
        
        // Documentación de la API
        case ($path === 'docs' && $request_method === 'GET'):
            mostrarDocumentacion();
            break;
            
        // Health check
        case ($path === 'health' && $request_method === 'GET'):
            healthCheck();
            break;
            
        // Setup inicial de la base de datos (solo desarrollo)
        case ($path === 'setup' && $request_method === 'GET'):
            setupDatabase();
            break;
            
        // Página principal de la API
        case ($path === '' && $request_method === 'GET'):
            paginaPrincipal();
            break;
            
        // Manejar OPTIONS para CORS
        case ($request_method === 'OPTIONS'):
            http_response_code(200);
            exit();
            break;
            
        // Endpoint no encontrado
        default:
            notFound();
            break;
    }
    
} catch(Exception $e) {
    sendJsonResponse(array(
        "success" => false,
        "message" => "Error interno del servidor",
        "error" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ), 500);
}

/**
 * Documentación completa de la API
 */
function mostrarDocumentacion() {
    $documentation = array(
        "api_info" => array(
            "name" => "EcoDenuncia API",
            "version" => "1.0.0",
            "developer" => "Jonathan Paul Zambrano Arriaga",
            "description" => "API para gestión de denuncias ambientales ciudadanas",
            "base_url" => API_URL
        ),
        "funcionalidades" => array(
            "resumen_semanal" => array(
                "descripcion" => "Ver resumen semanal de denuncias recientes en su zona",
                "endpoint" => "GET /denuncias/resumen-semanal",
                "parametros" => array(
                    "zona" => "(opcional) Filtrar por zona/ubicación",
                    "categoria" => "(opcional) Filtrar por tipo de problema",
                    "limite" => "(opcional) Cantidad máxima de denuncias (1-50, default: 10)"
                ),
                "ejemplo_request" => API_URL . "denuncias/resumen-semanal?zona=Guayaquil&categoria=contaminacion_agua&limite=5",
                "ejemplo_response" => array(
                    "success" => true,
                    "data" => array(
                        "resumen" => array(
                            "total_denuncias" => 3,
                            "pendientes" => 2,
                            "en_proceso" => 1,
                            "resueltas" => 0
                        ),
                        "denuncias" => array(
                            array(
                                "id" => 1,
                                "tipo_problema" => "contaminacion_agua",
                                "descripcion_corta" => "Vertido de residuos industriales...",
                                "ubicacion" => "Río Verde, Guayaquil",
                                "estado" => "pendiente",
                                "dias_transcurridos" => 2
                            )
                        )
                    )
                )
            ),
            "comentarios" => array(
                "crear_comentario" => array(
                    "descripcion" => "Permitir a los usuarios dejar comentarios en denuncias existentes",
                    "endpoint" => "POST /comentarios",
                    "body_required" => array(
                        "denuncia_id" => "ID de la denuncia (requerido)",
                        "nombre_usuario" => "Nombre del usuario (2-100 caracteres)",
                        "comentario" => "Texto del comentario (5-1000 caracteres)"
                    ),
                    "ejemplo_request" => array(
                        "denuncia_id" => 1,
                        "nombre_usuario" => "Jonathan Zambrano",
                        "comentario" => "Esta denuncia requiere atención urgente por parte de las autoridades ambientales"
                    )
                ),
                "obtener_comentarios" => array(
                    "descripcion" => "Obtener todos los comentarios de una denuncia específica",
                    "endpoint" => "GET /comentarios/{denuncia_id}",
                    "parametros" => array(
                        "denuncia_id" => "ID de la denuncia (en la URL)",
                        "pagina" => "(opcional) Número de página (default: 1)",
                        "limite" => "(opcional) Comentarios por página (1-50, default: 20)"
                    ),
                    "ejemplo_request" => API_URL . "comentarios/1?pagina=1&limite=10"
                )
            )
        ),
        "codigos_estado" => array(
            "200" => "OK - Solicitud exitosa",
            "201" => "Created - Recurso creado exitosamente",
            "400" => "Bad Request - Datos inválidos o faltantes",
            "404" => "Not Found - Endpoint no encontrado",
            "405" => "Method Not Allowed - Método HTTP incorrecto",
            "500" => "Internal Server Error - Error interno del servidor"
        ),
        "ejemplos_uso" => array(
            "obtener_resumen_todas_denuncias" => array(
                "url" => API_URL . "denuncias/resumen-semanal",
                "metodo" => "GET"
            ),
            "filtrar_por_zona" => array(
                "url" => API_URL . "denuncias/resumen-semanal?zona=Guayaquil",
                "metodo" => "GET"
            ),
            "filtrar_por_categoria" => array(
                "url" => API_URL . "denuncias/resumen-semanal?categoria=contaminacion_agua",
                "metodo" => "GET"
            ),
            "crear_comentario" => array(
                "url" => API_URL . "comentarios",
                "metodo" => "POST",
                "headers" => array("Content-Type: application/json"),
                "body" => '{"denuncia_id": 1, "nombre_usuario": "Juan Pérez", "comentario": "Excelente reporte"}'
            ),
            "ver_comentarios" => array(
                "url" => API_URL . "comentarios/1",
                "metodo" => "GET"
            )
        )
    );
    
    sendJsonResponse($documentation, 200);
}

/**
 * Health check del API
 */
function healthCheck() {
    try {
        // Probar conexión a base de datos
        $database = new Database();
        $conn = $database->getConnection();
        
        $db_status = $conn ? "connected" : "disconnected";
        
        // Probar consulta básica
        if ($conn) {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM denuncias");
            $denuncias_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM comentarios");
            $comentarios_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
        
        sendJsonResponse(array(
            "success" => true,
            "status" => "healthy",
            "timestamp" => date('Y-m-d H:i:s'),
            "database" => array(
                "status" => $db_status,
                "denuncias_count" => isset($denuncias_count) ? (int)$denuncias_count : 0,
                "comentarios_count" => isset($comentarios_count) ? (int)$comentarios_count : 0
            ),
            "environment" => array(
                "php_version" => phpversion(),
                "server" => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            )
        ), 200);
        
    } catch(Exception $e) {
        sendJsonResponse(array(
            "success" => false,
            "status" => "unhealthy",
            "error" => $e->getMessage(),
            "timestamp" => date('Y-m-d H:i:s')
        ), 500);
    }
}

/**
 * Setup inicial de la base de datos (solo desarrollo)
 */
function setupDatabase() {
    try {
        if (ENVIRONMENT !== 'development') {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Setup solo disponible en entorno de desarrollo"
            ), 403);
        }
        
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "No se pudo conectar a la base de datos"
            ), 500);
        }
        
        // Verificar tablas existentes
        $verificacion = $database->verificarTablas();
        
        if (isset($verificacion['error'])) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al verificar tablas: " . $verificacion['mensaje']
            ), 500);
        }
        
        $setup_resultado = array();
        
        // Crear tablas si no existen
        if (!$verificacion['todas_existen']) {
            $crear_tablas = $database->crearTablasDesarrollo();
            $setup_resultado['crear_tablas'] = $crear_tablas;
            
            if ($crear_tablas['success']) {
                // Insertar datos de prueba
                $datos_prueba = $database->insertarDatosPrueba();
                $setup_resultado['datos_prueba'] = $datos_prueba;
            }
        }
        
        sendJsonResponse(array(
            "success" => true,
            "message" => "Setup de base de datos completado",
            "data" => array(
                "verificacion_tablas" => $verificacion,
                "setup_resultado" => $setup_resultado,
                "proximos_pasos" => array(
                    "1. Verificar que las tablas se crearon correctamente",
                    "2. Probar endpoint: GET /denuncias/resumen-semanal",
                    "3. Probar endpoint: POST /comentarios",
                    "4. Ver documentación: GET /docs"
                )
            )
        ), 200);
        
    } catch(Exception $e) {
        sendJsonResponse(array(
            "success" => false,
            "message" => "Error en setup: " . $e->getMessage()
        ), 500);
    }
}

/**
 * Página principal de bienvenida
 */
function paginaPrincipal() {
    sendJsonResponse(array(
        "success" => true,
        "message" => "Bienvenido a EcoDenuncia API",
        "developer" => "Jonathan Paul Zambrano Arriaga",
        "version" => "1.0.0",
        "description" => "Sistema de denuncias ambientales ciudadanas",
        "quick_links" => array(
            "documentacion" => API_URL . "docs",
            "health_check" => API_URL . "health",
            "resumen_semanal" => API_URL . "denuncias/resumen-semanal",
            "crear_comentario" => API_URL . "comentarios"
        ),
        "timestamp" => date('Y-m-d H:i:s')
    ), 200);
}
?>
