<?php
// controllers/ComentariosController.php - Controlador específico
require_once '../config/config.php';
require_once '../models/Comentario.php';
require_once '../models/Denuncia.php';

class ComentariosController {
    
    /**
     * ENDPOINT: POST /api/comentarios
     * Funcionalidad 2: Crear nuevo comentario
     */
    public function crearComentario() {
        try {
            // Validar método HTTP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Método no permitido. Use POST."
                ), 405);
            }
            
            // Obtener datos JSON del request
            $json = file_get_contents("php://input");
            $data = json_decode($json, true);
            
            if (!$data) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Datos JSON inválidos o vacíos",
                    "received_data" => $json
                ), 400);
            }
            
            // Validar campos requeridos
            $campos_requeridos = ['denuncia_id', 'nombre_usuario', 'comentario'];
            foreach ($campos_requeridos as $campo) {
                if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
                    sendJsonResponse(array(
                        "success" => false,
                        "message" => "Campo requerido faltante: $campo",
                        "campos_requeridos" => $campos_requeridos
                    ), 400);
                }
            }
            
            // Crear comentario
            $comentario = new Comentario();
            $comentario->denuncia_id = $data['denuncia_id'];
            $comentario->nombre_usuario = $data['nombre_usuario'];
            $comentario->comentario = $data['comentario'];
            
            $comentario_id = $comentario->crear();
            
            if ($comentario_id) {
                // Obtener información actualizada
                $total_comentarios = $comentario->contarPorDenuncia($data['denuncia_id']);
                
                sendJsonResponse(array(
                    "success" => true,
                    "message" => "Comentario creado exitosamente",
                    "data" => array(
                        "comentario_id" => (int)$comentario_id,
                        "denuncia_id" => (int)$comentario->denuncia_id,
                        "nombre_usuario" => $comentario->nombre_usuario,
                        "comentario" => $comentario->comentario,
                        "fecha_creacion" => date('Y-m-d H:i:s'),
                        "total_comentarios_denuncia" => $total_comentarios
                    )
                ), 201);
            } else {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Error interno al crear el comentario"
                ), 500);
            }
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => $e->getMessage(),
                "error_code" => "COMENTARIO_CREATION_ERROR"
            ), 400);
        }
    }
    
    /**
     * ENDPOINT: GET /api/comentarios/{denuncia_id}
     * Obtener comentarios de una denuncia específica
     */
    public function obtenerComentariosPorDenuncia($denuncia_id) {
        try {
            // Validar denuncia_id
            if (!is_numeric($denuncia_id) || $denuncia_id <= 0) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "ID de denuncia inválido"
                ), 400);
            }
            
            // Parámetros de paginación
            $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
            $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 20;
            $offset = ($pagina - 1) * $limite;
            
            $comentario = new Comentario();
            
            // Obtener comentarios
            $stmt = $comentario->obtenerPorDenuncia($denuncia_id, $limite, $offset);
            $comentarios = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $comentarios[] = array(
                    "id" => (int)$row['id'],
                    "nombre_usuario" => $row['nombre_usuario'],
                    "comentario" => $row['comentario'],
                    "fecha" => date('d/m/Y H:i', strtotime($row['fecha_creacion'])),
                    "fecha_iso" => date('c', strtotime($row['fecha_creacion'])),
                    "tiempo_transcurrido" => $this->calcularTiempoTranscurrido($row['minutos_transcurridos'])
                );
            }
            
            // Obtener estadísticas
            $estadisticas = $comentario->obtenerEstadisticasPorDenuncia($denuncia_id);
            $total_comentarios = (int)$estadisticas['total_comentarios'];
            $total_paginas = ceil($total_comentarios / $limite);
            
            sendJsonResponse(array(
                "success" => true,
                "message" => "Comentarios obtenidos exitosamente",
                "data" => array(
                    "denuncia_id" => (int)$denuncia_id,
                    "comentarios" => $comentarios,
                    "estadisticas" => array(
                        "total_comentarios" => $total_comentarios,
                        "ultimo_comentario" => $estadisticas['ultimo_comentario'] ? 
                            date('d/m/Y H:i', strtotime($estadisticas['ultimo_comentario'])) : null,
                        "primer_comentario" => $estadisticas['primer_comentario'] ? 
                            date('d/m/Y H:i', strtotime($estadisticas['primer_comentario'])) : null
                    ),
                    "paginacion" => array(
                        "pagina_actual" => $pagina,
                        "total_paginas" => $total_paginas,
                        "limite_por_pagina" => $limite,
                        "total_elementos" => $total_comentarios,
                        "tiene_siguiente" => $pagina < $total_paginas,
                        "tiene_anterior" => $pagina > 1
                    )
                )
            ), 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al obtener comentarios: " . $e->getMessage(),
                "error_code" => "COMENTARIOS_FETCH_ERROR"
            ), 500);
        }
    }
    
    /**
     * Calcular tiempo transcurrido en formato legible
     */
    private function calcularTiempoTranscurrido($minutos) {
        if ($minutos < 60) {
            return "Hace " . $minutos . " minuto" . ($minutos !== 1 ? "s" : "");
        } elseif ($minutos < 1440) { // menos de 24 horas
            $horas = floor($minutos / 60);
            return "Hace " . $horas . " hora" . ($horas !== 1 ? "s" : "");
        } else { // más de 24 horas
            $dias = floor($minutos / 1440);
            return "Hace " . $dias . " día" . ($dias !== 1 ? "s" : "");
        }
    }
}
?>
