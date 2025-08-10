<?php
// controllers/EstadoDenunciaController.php - Funcionalidad de Darwin Pacheco
require_once '../config/config.php';
require_once '../models/Denuncia.php';

class EstadoDenunciaController {
    
    /**
     * ENDPOINT: PUT /api/denuncias/{id}/estado
     * Funcionalidad 3: Actualizar estado de denuncia
     * Responsable: Darwin Javier Pacheco Paredes
     */
    public function actualizarEstado($denuncia_id) {
        try {
            // Validar método HTTP
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Método no permitido. Use PUT."
                ), 405);
            }
            
            // Validar ID de denuncia
            if (!is_numeric($denuncia_id) || $denuncia_id <= 0) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "ID de denuncia inválido"
                ), 400);
            }
            
            // Obtener datos JSON del request
            $json = file_get_contents("php://input");
            $data = json_decode($json, true);
            
            if (!$data) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Datos JSON inválidos o vacíos"
                ), 400);
            }
            
            // Validar campo requerido: estado
            if (!isset($data['estado']) || empty(trim($data['estado']))) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "El campo 'estado' es requerido",
                    "estados_validos" => array("pendiente", "en_proceso", "resuelta")
                ), 400);
            }
            
            // Validar que el estado sea válido
            $estados_validos = array('pendiente', 'en_proceso', 'resuelta');
            if (!in_array($data['estado'], $estados_validos)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Estado inválido",
                    "estados_validos" => $estados_validos,
                    "estado_recibido" => $data['estado']
                ), 400);
            }
            
            // Crear instancia del modelo
            $denuncia = new Denuncia();
            
            // Verificar que la denuncia existe y obtener estado actual
            $denuncia_actual = $denuncia->obtenerPorId($denuncia_id);
            
            if (!$denuncia_actual) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Denuncia no encontrada",
                    "denuncia_id" => $denuncia_id
                ), 404);
            }
            
            // Verificar si el estado es diferente
            if ($denuncia_actual['estado'] === $data['estado']) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "La denuncia ya tiene este estado",
                    "estado_actual" => $denuncia_actual['estado']
                ), 400);
            }
            
            // Preparar datos para actualización
            $notas = isset($data['notas']) ? trim($data['notas']) : null;
            $usuario_responsable = isset($data['usuario_responsable']) ? trim($data['usuario_responsable']) : 'Sistema';
            
            // Actualizar estado
            $resultado = $denuncia->actualizarEstado(
                $denuncia_id, 
                $data['estado'], 
                $notas, 
                $usuario_responsable
            );
            
            if ($resultado['success']) {
                // Obtener información actualizada
                $denuncia_actualizada = $denuncia->obtenerPorId($denuncia_id);
                
                sendJsonResponse(array(
                    "success" => true,
                    "message" => "Estado actualizado exitosamente",
                    "data" => array(
                        "denuncia_id" => (int)$denuncia_id,
                        "estado_anterior" => $denuncia_actual['estado'],
                        "estado_nuevo" => $data['estado'],
                        "actualizado_por" => $usuario_responsable,
                        "notas" => $notas,
                        "fecha_actualizacion" => date('Y-m-d H:i:s'),
                        "denuncia" => array(
                            "id" => (int)$denuncia_actualizada['id'],
                            "tipo_problema" => $denuncia_actualizada['tipo_problema'],
                            "descripcion" => $denuncia_actualizada['descripcion'],
                            "ubicacion" => $denuncia_actualizada['ubicacion_direccion'],
                            "estado" => $denuncia_actualizada['estado'],
                            "fecha_creacion" => $denuncia_actualizada['fecha_creacion'],
                            "fecha_actualizacion" => $denuncia_actualizada['fecha_actualizacion']
                        )
                    )
                ), 200);
            } else {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Error al actualizar el estado",
                    "error" => $resultado['error'] ?? "Error desconocido"
                ), 500);
            }
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al actualizar estado: " . $e->getMessage(),
                "error_code" => "ESTADO_UPDATE_ERROR"
            ), 500);
        }
    }
    
    /**
     * ENDPOINT: GET /api/denuncias/{id}
     * Obtener detalles completos de una denuncia
     */
    public function obtenerDenuncia($denuncia_id) {
        try {
            // Validar ID
            if (!is_numeric($denuncia_id) || $denuncia_id <= 0) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "ID de denuncia inválido"
                ), 400);
            }
            
            $denuncia = new Denuncia();
            
            // Obtener denuncia
            $denuncia_data = $denuncia->obtenerDenunciaCompleta($denuncia_id);
            
            if (!$denuncia_data) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Denuncia no encontrada",
                    "denuncia_id" => $denuncia_id
                ), 404);
            }
            
            // Obtener estadísticas adicionales
            $estadisticas = $denuncia->obtenerEstadisticasDenuncia($denuncia_id);
            
            // Formatear respuesta
            sendJsonResponse(array(
                "success" => true,
                "message" => "Denuncia obtenida exitosamente",
                "data" => array(
                    "denuncia" => array(
                        "id" => (int)$denuncia_data['id'],
                        "tipo_problema" => $denuncia_data['tipo_problema'],
                        "descripcion" => $denuncia_data['descripcion'],
                        "ubicacion" => array(
                            "direccion" => $denuncia_data['ubicacion_direccion'],
                            "latitud" => $denuncia_data['ubicacion_lat'],
                            "longitud" => $denuncia_data['ubicacion_lng']
                        ),
                        "estado" => $denuncia_data['estado'],
                        "imagen_url" => $denuncia_data['imagen_url'],
                        "fecha_creacion" => $denuncia_data['fecha_creacion'],
                        "fecha_actualizacion" => $denuncia_data['fecha_actualizacion'],
                        "dias_transcurridos" => (int)$denuncia_data['dias_transcurridos'],
                        "tiempo_transcurrido" => $this->calcularTiempoTranscurrido($denuncia_data['fecha_creacion'])
                    ),
                    "estadisticas" => array(
                        "total_comentarios" => (int)$estadisticas['total_comentarios'],
                        "cambios_estado" => (int)$estadisticas['cambios_estado'],
                        "tiempo_resolucion" => $estadisticas['tiempo_resolucion']
                    ),
                    "acciones_disponibles" => $this->obtenerAccionesDisponibles($denuncia_data['estado'])
                )
            ), 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al obtener denuncia: " . $e->getMessage(),
                "error_code" => "DENUNCIA_FETCH_ERROR"
            ), 500);
        }
    }
    
    /**
     * ENDPOINT: GET /api/denuncias/{id}/historial
     * Obtener historial de cambios de estado
     */
    public function obtenerHistorialEstados($denuncia_id) {
        try {
            // Validar ID
            if (!is_numeric($denuncia_id) || $denuncia_id <= 0) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "ID de denuncia inválido"
                ), 400);
            }
            
            $estadoDenuncia = new EstadoDenuncia();
            
            // Verificar que la denuncia existe
            if (!$denuncia->obtenerPorId($denuncia_id)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Denuncia no encontrada"
                ), 404);
            }
            
            // Obtener historial
            $historial = $denuncia->obtenerHistorial($denuncia_id);
            
            // Formatear historial
            $historial_formateado = array();
            foreach ($historial as $cambio) {
                $historial_formateado[] = array(
                    "id" => (int)$cambio['id'],
                    "estado_anterior" => $cambio['estado_anterior'],
                    "estado_nuevo" => $cambio['estado_nuevo'],
                    "fecha_cambio" => $cambio['fecha_cambio'],
                    "usuario_responsable" => $cambio['usuario_responsable'],
                    "notas" => $cambio['notas'],
                    "tiempo_transcurrido" => $this->calcularTiempoTranscurrido($cambio['fecha_cambio'])
                );
            }
            
            sendJsonResponse(array(
                "success" => true,
                "message" => "Historial obtenido exitosamente",
                "data" => array(
                    "denuncia_id" => (int)$denuncia_id,
                    "total_cambios" => count($historial_formateado),
                    "historial" => $historial_formateado
                )
            ), 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al obtener historial: " . $e->getMessage(),
                "error_code" => "HISTORIAL_FETCH_ERROR"
            ), 500);
        }
    }
    
    /**
     * Calcular tiempo transcurrido en formato legible
     */
    private function calcularTiempoTranscurrido($fecha) {
        $fecha_creacion = new DateTime($fecha);
        $fecha_actual = new DateTime();
        $diferencia = $fecha_actual->diff($fecha_creacion);
        
        if ($diferencia->days > 30) {
            $meses = floor($diferencia->days / 30);
            return "Hace " . $meses . " mes" . ($meses > 1 ? "es" : "");
        } elseif ($diferencia->days > 0) {
            return "Hace " . $diferencia->days . " día" . ($diferencia->days > 1 ? "s" : "");
        } elseif ($diferencia->h > 0) {
            return "Hace " . $diferencia->h . " hora" . ($diferencia->h > 1 ? "s" : "");
        } else {
            return "Hace " . $diferencia->i . " minuto" . ($diferencia->i > 1 ? "s" : "");
        }
    }
    
    /**
     * Obtener acciones disponibles según el estado actual
     */
    private function obtenerAccionesDisponibles($estado_actual) {
        $acciones = array();
        
        switch($estado_actual) {
            case 'pendiente':
                $acciones = array(
                    array(
                        "accion" => "procesar",
                        "nuevo_estado" => "en_proceso",
                        "descripcion" => "Marcar como en proceso"
                    ),
                    array(
                        "accion" => "resolver",
                        "nuevo_estado" => "resuelta",
                        "descripcion" => "Marcar como resuelta"
                    )
                );
                break;
                
            case 'en_proceso':
                $acciones = array(
                    array(
                        "accion" => "resolver",
                        "nuevo_estado" => "resuelta",
                        "descripcion" => "Marcar como resuelta"
                    ),
                    array(
                        "accion" => "reabrir",
                        "nuevo_estado" => "pendiente",
                        "descripcion" => "Volver a pendiente"
                    )
                );
                break;
                
            case 'resuelta':
                $acciones = array(
                    array(
                        "accion" => "reabrir",
                        "nuevo_estado" => "pendiente",
                        "descripcion" => "Reabrir denuncia"
                    ),
                    array(
                        "accion" => "revisar",
                        "nuevo_estado" => "en_proceso",
                        "descripcion" => "Volver a revisar"
                    )
                );
                break;
        }
        
        return $acciones;
    }
}
?>