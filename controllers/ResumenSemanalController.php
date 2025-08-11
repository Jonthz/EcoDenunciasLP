<?php
// controllers/ResumenSemanalController.php - Controlador específico
require_once '../config/config.php';
require_once '../models/Denuncia.php';

class ResumenSemanalController {
    
    /**
     * ENDPOINT: GET /api/denuncias/resumen-semanal
     * Funcionalidad 1: Ver resumen semanal de denuncias recientes en su zona
     */
    public function obtenerResumenSemanal() {
        try {
            // Obtener parámetros de consulta
            $zona = isset($_GET['zona']) ? trim($_GET['zona']) : null;
            $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : null;
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
            
            // Validar límite
            if ($limite < 1 || $limite > 50) {
                $limite = 10;
            }
            
            $denuncia = new Denuncia();
            
            // Obtener denuncias del resumen semanal
            $stmt = $denuncia->obtenerResumenSemanal($zona, $categoria, $limite);
            $denuncias = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $denuncias[] = array(
                    "id" => (int)$row['id'],
                    "tipo_problema" => $row['tipo_problema'],
                    "descripcion_corta" => $this->truncarTexto($row['descripcion'], 100),
                    "descripcion_completa" => $row['descripcion'],
                    "ubicacion" => $row['ubicacion_direccion'],
                    "estado" => $row['estado'],
                    "fecha" => date('d/m/Y H:i', strtotime($row['fecha_creacion'])),
                    "fecha_relativa" => $this->tiempoTranscurrido($row['fecha_creacion']),
                    "dias_transcurridos" => (int)$row['dias_transcurridos'],
                    "imagen" => $row['imagen_url'],
                    "prioridad" => $this->calcularPrioridad($row['estado'], $row['dias_transcurridos'])
                );
            }
            
            // Obtener estadísticas
            $estadisticas = $denuncia->obtenerEstadisticasSemanal($zona, $categoria);
            
            // Respuesta estructurada
            $response = array(
                "success" => true,
                "message" => "Resumen obtenido exitosamente",
                "data" => array(
                    "resumen" => array(
                        "total_denuncias" => (int)$estadisticas['total_denuncias'],
                        "pendientes" => (int)$estadisticas['pendientes'],
                        "en_proceso" => (int)$estadisticas['en_proceso'],
                        "resueltas" => (int)$estadisticas['resueltas'],
                        "periodo" => "Últimos 7 días",
                        "fecha_consulta" => date('Y-m-d H:i:s')
                    ),
                    "filtros_aplicados" => array(
                        "zona" => $zona,
                        "categoria" => $categoria,
                        "limite" => $limite
                    ),
                    "denuncias" => $denuncias
                ),
                "meta" => array(
                    "categorias_disponibles" => $denuncia->obtenerCategorias(),
                    "zonas_disponibles" => $denuncia->obtenerZonas()
                )
            );
            
            sendJsonResponse($response, 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al obtener resumen semanal: " . $e->getMessage(),
                "error_code" => "RESUMEN_SEMANAL_ERROR"
            ), 500);
        }
    }
    
    /**
     * Truncar texto para descripción corta
     */
    private function truncarTexto($texto, $limite) {
        if (strlen($texto) <= $limite) {
            return $texto;
        }
        return substr($texto, 0, $limite) . "...";
    }
    
    /**
     * Calcular tiempo transcurrido en formato legible
     */
    private function tiempoTranscurrido($fecha) {
        $fecha_creacion = new DateTime($fecha);
        $fecha_actual = new DateTime();
        $diferencia = $fecha_actual->diff($fecha_creacion);
        
        if ($diferencia->days > 0) {
            return "Hace " . $diferencia->days . " día" . ($diferencia->days > 1 ? "s" : "");
        } elseif ($diferencia->h > 0) {
            return "Hace " . $diferencia->h . " hora" . ($diferencia->h > 1 ? "s" : "");
        } else {
            return "Hace " . $diferencia->i . " minuto" . ($diferencia->i > 1 ? "s" : "");
        }
    }
    
    /**
     * Calcular prioridad basada en estado y días transcurridos
     */
    private function calcularPrioridad($estado, $dias) {
        if ($estado === 'pendiente' && $dias >= 5) {
            return 'alta';
        } elseif ($estado === 'pendiente' && $dias >= 2) {
            return 'media';
        } elseif ($estado === 'en_proceso') {
            return 'media';
        } else {
            return 'baja';
        }
    }
}
?>
