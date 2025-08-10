<?php
// api/index.php - Router principal actualizado con todas las funcionalidades
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../controllers/ResumenSemanalController.php';
require_once '../controllers/ComentariosController.php';
require_once '../controllers/EstadoDenunciaController.php';
require_once '../controllers/ReportesController.php';

// Obtener información de la request
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Debug en desarrollo
if (ENVIRONMENT === 'development') {
    error_log("REQUEST_URI: " . $request_uri);
    error_log("REQUEST_METHOD: " . $request_method);
}


// Limpiar la URI removiendo el prefijo de la API
$api_prefix = '/EcoDenunciasLP/api'; // Ajustar según tu estructura
$path = str_replace($api_prefix, '', parse_url($request_uri, PHP_URL_PATH));

// Si no encuentra el prefijo, intentar sin él (acceso directo)
if ($path === parse_url($request_uri, PHP_URL_PATH)) {
    $path = ltrim(parse_url($request_uri, PHP_URL_PATH), '/');
    // Remover 'EcoDenunciasLP/api/' si está al inicio
    if (strpos($path, 'EcoDenunciasLP/api/') === 0) {
        $path = substr($path, strlen('EcoDenunciasLP/api/'));
    }
}

// Remover slash inicial si existe
$path = ltrim($path, '/');

// Debug en desarrollo
if (ENVIRONMENT === 'development') {
    error_log("PARSED PATH: " . $path);
}

// Instanciar controladores
$resumenController = new ResumenSemanalController();
$comentariosController = new ComentariosController();
$estadoController = new EstadoDenunciaController();
$reportesController = new ReportesController();

// Función para manejar errores 404
function notFound() {
    sendJsonResponse(array(
        "success" => false,
        "message" => "Endpoint no encontrado",
        "available_endpoints" => array(
            "GET /" => "Página principal de la API",
            "GET /health" => "Health check del sistema",
            "GET /docs" => "Documentación de la API",
            "GET /setup" => "Setup inicial (solo desarrollo)",
            "GET /denuncias/resumen-semanal" => "Obtener resumen semanal de denuncias",
            "POST /comentarios" => "Crear nuevo comentario",
            "GET /comentarios/{denuncia_id}" => "Obtener comentarios de una denuncia",
            "PUT /denuncias/{id}/estado" => "Actualizar estado de denuncia",
            "GET /denuncias/{id}" => "Obtener detalles de una denuncia",
            "GET /reportes" => "Generar reportes de denuncias",
            "GET /reportes/categorias" => "Reporte por categorías",
            "GET /reportes/ubicaciones" => "Reporte por ubicaciones",
            "GET /reportes/temporal" => "Reporte temporal (tendencias)",
            "GET /docs" => "Documentación de la API"
        )
    ), 404);
}

// Router principal
try {
    switch(true) {
        
        // ============= FUNCIONALIDAD 1: RESUMEN SEMANAL (Jonathan) =============
        case ($path === 'denuncias/resumen-semanal' && $request_method === 'GET'):
            $resumenController->obtenerResumenSemanal();
            break;
            
        // ============= FUNCIONALIDAD 2: SISTEMA DE COMENTARIOS (Jonathan) =============
        case ($path === 'comentarios' && $request_method === 'POST'):
            $comentariosController->crearComentario();
            break;
            
        // Obtener comentarios por denuncia ID
        case (preg_match('/^comentarios\/(\d+)$/', $path, $matches) && $request_method === 'GET'):
            $denuncia_id = $matches[1];
            $comentariosController->obtenerComentariosPorDenuncia($denuncia_id);
            break;
            
        // ============= FUNCIONALIDAD 3: ACTUALIZAR ESTADO (Darwin) =============
        // Actualizar estado de denuncia
        case (preg_match('/^denuncias\/(\d+)\/estado$/', $path, $matches) && $request_method === 'PUT'):
            $denuncia_id = $matches[1];
            $estadoController->actualizarEstado($denuncia_id);
            break;
            
        // Obtener detalles de una denuncia específica
        case (preg_match('/^denuncias\/(\d+)$/', $path, $matches) && $request_method === 'GET'):
            $denuncia_id = $matches[1];
            $estadoController->obtenerDenuncia($denuncia_id);
            break;
            
        // Obtener historial de estados
        case (preg_match('/^denuncias\/(\d+)\/historial$/', $path, $matches) && $request_method === 'GET'):
            $denuncia_id = $matches[1];
            $estadoController->obtenerHistorialEstados($denuncia_id);
            break;
            
        // ============= FUNCIONALIDAD 4: GENERAR REPORTES (Darwin) =============
        // Reporte general
        case ($path === 'reportes' && $request_method === 'GET'):
            $reportesController->generarReporteGeneral();
            break;
            
        // Reporte por categorías
        case ($path === 'reportes/categorias' && $request_method === 'GET'):
            $reportesController->generarReportePorCategorias();
            break;
            
        // Reporte por ubicaciones
        case ($path === 'reportes/ubicaciones' && $request_method === 'GET'):
            $reportesController->generarReportePorUbicaciones();
            break;
            
        // Reporte temporal (tendencias)
        case ($path === 'reportes/temporal' && $request_method === 'GET'):
            $reportesController->generarReporteTemporal();
            break;
            
        // Exportar reporte (CSV/JSON)
        case ($path === 'reportes/exportar' && $request_method === 'GET'):
            $reportesController->exportarReporte();
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
 * Documentación completa de la API actualizada
 */
function mostrarDocumentacion() {
    $documentation = array(
        "api_info" => array(
            "name" => "EcoDenuncia API",
            "version" => "2.0.0",
            "developers" => array(
                "Jonathan Paul Zambrano Arriaga",
                "Darwin Javier Pacheco Paredes"
            ),
            "description" => "API completa para gestión de denuncias ambientales ciudadanas",
            "base_url" => API_URL
        ),
        "funcionalidades" => array(
            "resumen_semanal" => array(
                "developer" => "Jonathan Zambrano",
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
                "developer" => "Jonathan Zambrano",
                "crear_comentario" => array(
                    "descripcion" => "Permitir a los usuarios dejar comentarios en denuncias existentes",
                    "endpoint" => "POST /comentarios",
                    "body_required" => array(
                        "denuncia_id" => "ID de la denuncia",
                        "nombre_usuario" => "Nombre del usuario",
                        "comentario" => "Texto del comentario"
                    )
                ),
                "obtener_comentarios" => array(
                    "descripcion" => "Obtener todos los comentarios de una denuncia específica",
                    "endpoint" => "GET /comentarios/{denuncia_id}",
                    "parametros" => array(
                        "pagina" => "(opcional) Número de página",
                        "limite" => "(opcional) Comentarios por página"
                    )
                )
            ),
            "actualizar_estado" => array(
                "developer" => "Darwin Pacheco",
                "descripcion" => "Actualizar estado de denuncia",
                "actualizar" => array(
                    "endpoint" => "PUT /denuncias/{id}/estado",
                    "body_required" => array(
                        "estado" => "pendiente | en_proceso | resuelta",
                        "notas" => "(opcional) Notas sobre el cambio",
                        "usuario_responsable" => "(opcional) Quien realiza el cambio"
                    )
                ),
                "obtener_denuncia" => array(
                    "endpoint" => "GET /denuncias/{id}",
                    "descripcion" => "Obtener detalles completos de una denuncia"
                ),
                "historial" => array(
                    "endpoint" => "GET /denuncias/{id}/historial",
                    "descripcion" => "Ver historial de cambios de estado"
                )
            ),
            "reportes" => array(
                "developer" => "Darwin Pacheco",
                "descripcion" => "Generar reportes estadísticos de denuncias",
                "reporte_general" => array(
                    "endpoint" => "GET /reportes",
                    "parametros" => array(
                        "fecha_inicio" => "(opcional) Fecha inicio YYYY-MM-DD",
                        "fecha_fin" => "(opcional) Fecha fin YYYY-MM-DD",
                        "incluir_graficos" => "(opcional) true/false"
                    )
                ),
                "reporte_categorias" => array(
                    "endpoint" => "GET /reportes/categorias",
                    "descripcion" => "Estadísticas agrupadas por tipo de problema"
                ),
                "reporte_ubicaciones" => array(
                    "endpoint" => "GET /reportes/ubicaciones",
                    "descripcion" => "Estadísticas agrupadas por zona/ubicación"
                ),
                "reporte_temporal" => array(
                    "endpoint" => "GET /reportes/temporal",
                    "descripcion" => "Tendencias temporales y evolución"
                ),
                "exportar" => array(
                    "endpoint" => "GET /reportes/exportar",
                    "parametros" => array(
                        "formato" => "csv | json (default: json)",
                        "tipo" => "general | categorias | ubicaciones"
                    )
                )
            )
        ),
        "codigos_estado" => array(
            "200" => "OK - Solicitud exitosa",
            "201" => "Created - Recurso creado exitosamente",
            "400" => "Bad Request - Datos inválidos o faltantes",
            "404" => "Not Found - Recurso no encontrado",
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
 * Health check actualizado
 */
function healthCheck() {
    try {
        // Probar conexión a base de datos
        $database = new Database();
        $conn = $database->getConnection();
        
        $db_status = $conn ? "connected" : "disconnected";
        
        if ($conn) {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM denuncias");
            $denuncias_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM comentarios");
            $comentarios_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Verificar tabla de historial si existe
            try {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM historial_estados");
                $historial_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch(Exception $e) {
                $historial_count = 0;
            }
        }
        
        sendJsonResponse(array(
            "success" => true,
            "status" => "healthy",
            "timestamp" => date('Y-m-d H:i:s'),
            "database" => array(
                "status" => $db_status,
                "denuncias_count" => isset($denuncias_count) ? (int)$denuncias_count : 0,
                "comentarios_count" => isset($comentarios_count) ? (int)$comentarios_count : 0,
                "historial_count" => isset($historial_count) ? (int)$historial_count : 0
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
 * Página principal actualizada
 */
function paginaPrincipal() {
    sendJsonResponse(array(
        "success" => true,
        "message" => "Bienvenido a EcoDenuncia API v2.0",
        "developers" => array(
            "Jonathan Paul Zambrano Arriaga" => array("Resumen Semanal", "Sistema de Comentarios"),
            "Darwin Javier Pacheco Paredes" => array("Actualización de Estados", "Generación de Reportes")
        ),
        "version" => "2.0.0",
        "description" => "Sistema completo de denuncias ambientales ciudadanas",
        "quick_links" => array(
            "documentacion" => API_URL . "docs",
            "health_check" => API_URL . "health",
            "resumen_semanal" => API_URL . "denuncias/resumen-semanal",
            "crear_comentario" => API_URL . "comentarios",
            "actualizar_estado" => API_URL . "denuncias/{id}/estado",
            "generar_reportes" => API_URL . "reportes"
        ),
        "timestamp" => date('Y-m-d H:i:s')
    ), 200);
}
?>
