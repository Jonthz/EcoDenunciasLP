<?php
// controllers/ReportesController.php - Funcionalidad de Darwin Pacheco
require_once '../config/config.php';
require_once '../models/Reportes.php';

class ReportesController {
    
    /**
     * ENDPOINT: GET /api/reportes
     * Funcionalidad 4: Generar reporte general de denuncias
     * Responsable: Darwin Javier Pacheco Paredes
     */
    public function generarReporteGeneral() {
        try {
            // Obtener parámetros opcionales
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

            // Validar fechas si se proporcionan
            if ($fecha_inicio && !$this->validarFecha($fecha_inicio)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Formato de fecha_inicio inválido. Use YYYY-MM-DD"
                ), 400);
            }
            if ($fecha_fin && !$this->validarFecha($fecha_fin)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Formato de fecha_fin inválido. Use YYYY-MM-DD"
                ), 400);
            }
            
            $reportes = new Reportes();
            
            // Obtener datos del reporte general
            $estadisticas_generales = $reportes->obtenerEstadisticasGenerales($fecha_inicio, $fecha_fin);
            $distribucion_estados = $reportes->obtenerDistribucionEstados($fecha_inicio, $fecha_fin);
            $top_categorias = $reportes->obtenerTopCategorias(5, $fecha_inicio, $fecha_fin);
            $top_ubicaciones = $reportes->obtenerTopUbicaciones(5, $fecha_inicio, $fecha_fin);
            
            $explicacion = "Entre " . ($fecha_inicio ?? "el inicio de registros") . " y " . ($fecha_fin ?? date('Y-m-d')) .
                " se registraron un total de " . (int)$estadisticas_generales['total_denuncias'] . " denuncias. " .
                "De ellas, " . (int)$estadisticas_generales['pendientes'] . " están pendientes, " .
                (int)$estadisticas_generales['en_proceso'] . " en proceso y " .
                (int)$estadisticas_generales['resueltas'] . " han sido resueltas. " .
                "La tasa de resolución es del " . round($estadisticas_generales['tasa_resolucion'], 2) . "%. " .
                "Las categorías más frecuentes son: " . implode(", ", array_map(fn($c) => $c['categoria'], $top_categorias)) . ". " .
                "Las ubicaciones con más denuncias son: " . implode(", ", array_map(fn($u) => $u['ubicacion'], $top_ubicaciones)) . ".";
            
            // Construir respuesta
            $response = array(
                "success" => true,
                "message" => "Reporte general generado exitosamente",
                "data" => array(
                    "periodo" => array(
                        "fecha_inicio" => $fecha_inicio ?? "Inicio de registros",
                        "fecha_fin" => $fecha_fin ?? date('Y-m-d'),
                        "fecha_generacion" => date('Y-m-d H:i:s')
                    ),
                    "estadisticas_generales" => array(
                        "total_denuncias" => (int)$estadisticas_generales['total_denuncias'],
                        "denuncias_pendientes" => (int)$estadisticas_generales['pendientes'],
                        "denuncias_en_proceso" => (int)$estadisticas_generales['en_proceso'],
                        "denuncias_resueltas" => (int)$estadisticas_generales['resueltas'],
                        "promedio_dias_resolucion" => round($estadisticas_generales['promedio_dias_resolucion'], 1),
                        "tasa_resolucion" => round($estadisticas_generales['tasa_resolucion'], 2) . "%"
                    ),
                    "distribucion_estados" => $distribucion_estados,
                    "top_categorias" => $top_categorias,
                    "top_ubicaciones" => $top_ubicaciones,
                    "explicacion" => $explicacion
                )
            );
            
            sendJsonResponse($response, 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al generar reporte general: " . $e->getMessage(),
                "error_code" => "REPORTE_GENERAL_ERROR"
            ), 500);
        }
    }
    
    /**
     * ENDPOINT: GET /api/reportes/exportar
     * Exportar reporte en diferentes formatos
     */
    public function exportarReporte() {
        try {
            // Obtener parámetros
            $formato = isset($_GET['formato']) ? $_GET['formato'] : 'json';
            $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'general';
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

            // Solo permitir exportar el reporte general
            if ($tipo !== 'general') {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Solo se permite exportar el reporte general.",
                    "tipos_permitidos" => array('general')
                ), 400);
            }

            // Validar formato
            $formatos_validos = array('json', 'csv');
            if (!in_array($formato, $formatos_validos)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Formato inválido",
                    "formatos_validos" => $formatos_validos
                ), 400);
            }

            $reportes = new Reportes();

            // Obtener datos solo del reporte general
            $datos = $reportes->obtenerDatosExportacionGeneral($fecha_inicio, $fecha_fin);

            // Exportar según formato
            if ($formato === 'csv') {
                $this->exportarCSV($datos, $tipo);
            } else {
                sendJsonResponse(array(
                    "success" => true,
                    "message" => "Datos exportados exitosamente",
                    "data" => array(
                        "tipo_reporte" => $tipo,
                        "formato" => $formato,
                        "fecha_exportacion" => date('Y-m-d H:i:s'),
                        "total_registros" => count($datos),
                        "datos" => $datos
                    )
                ), 200);
            }

        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al exportar reporte: " . $e->getMessage(),
                "error_code" => "EXPORT_ERROR"
            ), 500);
        }
    }
    
    /**
     * ENDPOINT: GET /api/reportes/categorias
     * Generar reporte por categorías/tipos de problema
     */
    public function generarReportePorCategorias() {
        try {
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;

            $reportes = new Reportes();
            $categorias = $reportes->obtenerTopCategorias($limite, $fecha_inicio, $fecha_fin);

            $explicacion = "Las categorías con mayor cantidad de denuncias en el periodo " .
                ($fecha_inicio ?? "inicio de registros") . " a " . ($fecha_fin ?? date('Y-m-d')) .
                " son: " . implode(", ", array_map(fn($c) => $c['categoria'] . " (" . $c['total'] . ")", $categorias)) . ".";

            sendJsonResponse(array(
                "success" => true,
                "message" => "Reporte por categorías generado exitosamente",
                "data" => array(
                    "periodo" => array(
                        "fecha_inicio" => $fecha_inicio ?? "Inicio de registros",
                        "fecha_fin" => $fecha_fin ?? date('Y-m-d'),
                        "fecha_generacion" => date('Y-m-d H:i:s')
                    ),
                    "categorias" => $categorias,
                    "explicacion" => $explicacion
                )
            ), 200);

        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al generar reporte por categorías: " . $e->getMessage(),
                "error_code" => "REPORTE_CATEGORIAS_ERROR"
            ), 500);
        }
    }

    /**
     * ENDPOINT: GET /api/reportes/ubicaciones
     * Generar reporte por ubicaciones
     */
    public function generarReportePorUbicaciones() {
        try {
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;

            $reportes = new Reportes();
            $ubicaciones = $reportes->obtenerTopUbicaciones($limite, $fecha_inicio, $fecha_fin);

            $explicacion = "Las ubicaciones con mayor cantidad de denuncias en el periodo " .
                ($fecha_inicio ?? "inicio de registros") . " a " . ($fecha_fin ?? date('Y-m-d')) .
                " son: " . implode(", ", array_map(fn($u) => $u['ubicacion'] . " (" . $u['total'] . ")", $ubicaciones)) . ".";

            sendJsonResponse(array(
                "success" => true,
                "message" => "Reporte por ubicaciones generado exitosamente",
                "data" => array(
                    "periodo" => array(
                        "fecha_inicio" => $fecha_inicio ?? "Inicio de registros",
                        "fecha_fin" => $fecha_fin ?? date('Y-m-d'),
                        "fecha_generacion" => date('Y-m-d H:i:s')
                    ),
                    "ubicaciones" => $ubicaciones,
                    "explicacion" => $explicacion
                )
            ), 200);

        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al generar reporte por ubicaciones: " . $e->getMessage(),
                "error_code" => "REPORTE_UBICACIONES_ERROR"
            ), 500);
        }
    }
    
    // ========== MÉTODOS AUXILIARES ==========
    
    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha) {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
    
    /**
     * Exportar datos como CSV
     */
    private function exportarCSV($datos, $tipo) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escribir encabezados
        if (!empty($datos)) {
            fputcsv($output, array_keys($datos[0]));
        }
        
        // Escribir datos
        foreach ($datos as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}
?>