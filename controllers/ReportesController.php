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
            $incluir_graficos = isset($_GET['incluir_graficos']) ? 
                filter_var($_GET['incluir_graficos'], FILTER_VALIDATE_BOOLEAN) : true;
            
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
            $evolucion_temporal = $reportes->obtenerEvolucionTemporal($fecha_inicio, $fecha_fin);
            
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
                    "tendencias" => array(
                        "evolucion_temporal" => $evolucion_temporal,
                        "promedio_denuncias_diarias" => round($estadisticas_generales['promedio_denuncias_diarias'], 2),
                        "tendencia" => $this->calcularTendencia($evolucion_temporal)
                    )
                )
            );
            
            // Agregar datos para gráficos si se solicitan
            if ($incluir_graficos) {
                $response['data']['graficos'] = array(
                    "pie_estados" => $this->generarDatosGraficoPie($distribucion_estados),
                    "barras_categorias" => $this->generarDatosGraficoBarras($top_categorias),
                    "linea_temporal" => $this->generarDatosGraficoLinea($evolucion_temporal)
                );
            }
            
            sendJsonResponse($response, 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al generar reporte temporal: " . $e->getMessage(),
                "error_code" => "REPORTE_TEMPORAL_ERROR"
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
            
            // Validar formato
            $formatos_validos = array('json', 'csv');
            if (!in_array($formato, $formatos_validos)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Formato inválido",
                    "formatos_validos" => $formatos_validos
                ), 400);
            }
            
            // Validar tipo
            $tipos_validos = array('general', 'categorias', 'ubicaciones');
            if (!in_array($tipo, $tipos_validos)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Tipo de reporte inválido",
                    "tipos_validos" => $tipos_validos
                ), 400);
            }
            
            $reportes = new Reportes();
            
            // Obtener datos según el tipo
            $datos = array();
            switch($tipo) {
                case 'general':
                    $datos = $reportes->obtenerDatosExportacionGeneral($fecha_inicio, $fecha_fin);
                    break;
                case 'categorias':
                    $datos = $reportes->obtenerDatosExportacionCategorias($fecha_inicio, $fecha_fin);
                    break;
                case 'ubicaciones':
                    $datos = $reportes->obtenerDatosExportacionUbicaciones($fecha_inicio, $fecha_fin);
                    break;
            }
            
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
    
    // ========== MÉTODOS AUXILIARES ==========
    
    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha) {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
    
    /**
     * Calcular tendencia basada en evolución temporal
     */
    private function calcularTendencia($evolucion) {
        if (empty($evolucion)) return "sin datos";
        
        $valores = array_column($evolucion, 'total');
        $n = count($valores);
        
        if ($n < 2) return "datos insuficientes";
        
        // Calcular pendiente simple
        $primera_mitad = array_sum(array_slice($valores, 0, floor($n/2)));
        $segunda_mitad = array_sum(array_slice($valores, floor($n/2)));
        
        if ($segunda_mitad > $primera_mitad * 1.1) {
            return "creciente";
        } elseif ($segunda_mitad < $primera_mitad * 0.9) {
            return "decreciente";
        } else {
            return "estable";
        }
    }
    
    /**
     * Generar datos para gráfico de pie
     */
    private function generarDatosGraficoPie($distribucion) {
        $datos = array();
        $colores = array(
            'pendiente' => '#FF6B6B',
            'en_proceso' => '#FFD93D',
            'resuelta' => '#6BCF7F'
        );
        
        foreach ($distribucion as $estado => $cantidad) {
            $datos[] = array(
                "label" => ucfirst(str_replace('_', ' ', $estado)),
                "value" => $cantidad,
                "color" => $colores[$estado] ?? '#95A5A6'
            );
        }
        
        return $datos;
    }
    
    /**
     * Generar datos para gráfico de barras
     */
    private function generarDatosGraficoBarras($categorias) {
        $datos = array(
            "labels" => array(),
            "datasets" => array(
                array(
                    "label" => "Total de denuncias",
                    "data" => array(),
                    "backgroundColor" => "#3498DB"
                )
            )
        );
        
        foreach ($categorias as $categoria) {
            $datos['labels'][] = $categoria['categoria'];
            $datos['datasets'][0]['data'][] = $categoria['total'];
        }
        
        return $datos;
    }
    
    /**
     * Generar datos para gráfico de línea
     */
    private function generarDatosGraficoLinea($evolucion) {
        $datos = array(
            "labels" => array(),
            "datasets" => array(
                array(
                    "label" => "Denuncias por período",
                    "data" => array(),
                    "borderColor" => "#8E44AD",
                    "fill" => false
                )
            )
        );
        
        foreach ($evolucion as $periodo) {
            $datos['labels'][] = $periodo['periodo'];
            $datos['datasets'][0]['data'][] = $periodo['total'];
        }
        
        return $datos;
    }
    
    /**
     * Calcular prioridad de categoría
     */
    private function calcularPrioridadCategoria($categoria) {
        $score = 0;
        
        // Más puntos por más denuncias pendientes
        $score += $categoria['pendientes'] * 3;
        $score += $categoria['en_proceso'] * 2;
        
        // Menos puntos por alta tasa de resolución
        $score -= $categoria['tasa_resolucion'] * 0.5;
        
        if ($score > 50) return "alta";
        if ($score > 20) return "media";
        return "baja";
    }
    
    /**
     * Obtener categoría con mejor tasa de resolución
     */
    private function obtenerMejorCategoria($categorias) {
        if (empty($categorias)) return null;
        
        usort($categorias, function($a, $b) {
            return floatval(str_replace('%', '', $b['tasa_resolucion'])) - 
                   floatval(str_replace('%', '', $a['tasa_resolucion']));
        });
        
        return $categorias[0]['categoria'];
    }
    
    /**
     * Generar recomendaciones basadas en categorías
     */
    private function generarRecomendacionesCategorias($categorias) {
        $recomendaciones = array();
        
        foreach ($categorias as $cat) {
            if ($cat['prioridad'] === 'alta') {
                $recomendaciones[] = "Atención urgente requerida para denuncias de tipo '{$cat['categoria']}'";
            }
            if (floatval(str_replace('%', '', $cat['tasa_resolucion'])) < 30) {
                $recomendaciones[] = "Mejorar proceso de resolución para '{$cat['categoria']}'";
            }
        }
        
        if (empty($recomendaciones)) {
            $recomendaciones[] = "Sistema funcionando dentro de parámetros normales";
        }
        
        return $recomendaciones;
    }
    
    /**
     * Generar mapa de calor para ubicaciones
     */
    private function generarMapaCalor($ubicaciones) {
        $mapa = array();
        
        foreach ($ubicaciones as $ubicacion) {
            $intensidad = min(100, ($ubicacion['total_denuncias'] / 10) * 100);
            $mapa[] = array(
                "zona" => $ubicacion['zona'],
                "intensidad" => round($intensidad, 2),
                "color" => $this->calcularColorCalor($intensidad)
            );
        }
        
        return $mapa;
    }
    
    /**
     * Calcular color para mapa de calor
     */
    private function calcularColorCalor($intensidad) {
        if ($intensidad > 75) return "#FF0000"; // Rojo
        if ($intensidad > 50) return "#FF7F00"; // Naranja
        if ($intensidad > 25) return "#FFFF00"; // Amarillo
        return "#00FF00"; // Verde
    }
    
    /**
     * Generar recomendaciones basadas en ubicaciones
     */
    private function generarRecomendacionesUbicaciones($ubicaciones) {
        $recomendaciones = array();
        
        foreach ($ubicaciones as $ub) {
            if ($ub['es_zona_critica']) {
                $recomendaciones[] = "Zona crítica detectada: {$ub['zona']} requiere intervención inmediata";
            }
        }
        
        return empty($recomendaciones) ? 
            array("No se detectaron zonas críticas") : $recomendaciones;
    }
    
    /**
     * Calcular proyección futura
     */
    private function calcularProyeccion($evolucion) {
        if (count($evolucion) < 3) return null;
        
        // Tomar últimos 3 períodos
        $ultimos = array_slice($evolucion, -3);
        $promedio = array_sum(array_column($ultimos, 'total')) / 3;
        
        // Calcular tendencia
        $tendencia = $this->calcularTendencia($evolucion);
        
        if ($tendencia === 'creciente') {
            $promedio *= 1.1;
        } elseif ($tendencia === 'decreciente') {
            $promedio *= 0.9;
        }
        
        return round($promedio);
    }
    
    /**
     * Analizar tendencia detallada
     */
    private function analizarTendencia($evolucion) {
        $tendencia = $this->calcularTendencia($evolucion);
        
        return array(
            "direccion" => $tendencia,
            "descripcion" => $this->obtenerDescripcionTendencia($tendencia),
            "confianza" => $this->calcularConfianzaTendencia($evolucion)
        );
    }
    
    /**
     * Obtener descripción de tendencia
     */
    private function obtenerDescripcionTendencia($tendencia) {
        $descripciones = array(
            'creciente' => 'Las denuncias están aumentando con el tiempo',
            'decreciente' => 'Las denuncias están disminuyendo con el tiempo',
            'estable' => 'Las denuncias se mantienen estables',
            'sin datos' => 'No hay suficientes datos para determinar tendencia'
        );
        
        return $descripciones[$tendencia] ?? 'Tendencia no determinada';
    }
    
    /**
     * Calcular confianza de la tendencia
     */
    private function calcularConfianzaTendencia($evolucion) {
        if (count($evolucion) < 5) return "baja";
        if (count($evolucion) < 10) return "media";
        return "alta";
    }
    
    /**
     * Obtener período pico
     */
    private function obtenerPeriodoPico($evolucion) {
        if (empty($evolucion)) return null;
        
        usort($evolucion, function($a, $b) {
            return $b['total'] - $a['total'];
        });
        
        return array(
            "periodo" => $evolucion[0]['periodo'],
            "total_denuncias" => $evolucion[0]['total']
        );
    }
    
    /**
     * Calcular variación promedio
     */
    private function calcularVariacion($evolucion) {
        if (count($evolucion) < 2) return 0;
        
        $variaciones = array();
        for ($i = 1; $i < count($evolucion); $i++) {
            $anterior = $evolucion[$i-1]['total'];
            $actual = $evolucion[$i]['total'];
            if ($anterior > 0) {
                $variaciones[] = (($actual - $anterior) / $anterior) * 100;
            }
        }
        
        return empty($variaciones) ? 0 : round(array_sum($variaciones) / count($variaciones), 2);
    }
    
    /**
     * Generar insights temporales
     */
    private function generarInsightsTemporal($evolucion, $comparacion) {
        $insights = array();
        
        $variacion = $this->calcularVariacion($evolucion);
        if (abs($variacion) > 20) {
            $insights[] = "Variación significativa detectada: " . 
                         ($variacion > 0 ? "incremento" : "disminución") . 
                         " del " . abs($variacion) . "%";
        }
        
        $pico = $this->obtenerPeriodoPico($evolucion);
        if ($pico) {
            $insights[] = "Período con más denuncias: {$pico['periodo']} con {$pico['total_denuncias']} denuncias";
        }
        
        return $insights;
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
?> general: " . $e->getMessage(),
                "error_code" => "REPORTE_GENERAL_ERROR"
            ), 500);
        }
    }
    
    /**
     * ENDPOINT: GET /api/reportes/categorias
     * Generar reporte por categorías/tipos de problema
     */
    public function generarReportePorCategorias() {
        try {
            // Obtener parámetros
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : null;
            
            $reportes = new Reportes();
            
            // Obtener estadísticas por categoría
            $categorias = $reportes->obtenerEstadisticasPorCategoria($fecha_inicio, $fecha_fin, $limite);
            
            // Calcular totales
            $total_general = array_sum(array_column($categorias, 'total'));
            
            // Formatear respuesta
            $categorias_formateadas = array();
            foreach ($categorias as $categoria) {
                $porcentaje = $total_general > 0 ? 
                    round(($categoria['total'] / $total_general) * 100, 2) : 0;
                
                $categorias_formateadas[] = array(
                    "categoria" => $categoria['tipo_problema'],
                    "total_denuncias" => (int)$categoria['total'],
                    "pendientes" => (int)$categoria['pendientes'],
                    "en_proceso" => (int)$categoria['en_proceso'],
                    "resueltas" => (int)$categoria['resueltas'],
                    "porcentaje_del_total" => $porcentaje . "%",
                    "tasa_resolucion" => round($categoria['tasa_resolucion'], 2) . "%",
                    "promedio_dias_resolucion" => round($categoria['promedio_dias_resolucion'], 1),
                    "prioridad" => $this->calcularPrioridadCategoria($categoria)
                );
            }
            
            sendJsonResponse(array(
                "success" => true,
                "message" => "Reporte por categorías generado exitosamente",
                "data" => array(
                    "periodo" => array(
                        "fecha_inicio" => $fecha_inicio ?? "Inicio de registros",
                        "fecha_fin" => $fecha_fin ?? date('Y-m-d')
                    ),
                    "resumen" => array(
                        "total_categorias" => count($categorias_formateadas),
                        "total_denuncias" => $total_general,
                        "categoria_mas_frecuente" => $categorias_formateadas[0]['categoria'] ?? null,
                        "categoria_mejor_resolucion" => $this->obtenerMejorCategoria($categorias_formateadas)
                    ),
                    "categorias" => $categorias_formateadas,
                    "recomendaciones" => $this->generarRecomendacionesCategorias($categorias_formateadas)
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
     * Generar reporte por ubicaciones/zonas
     */
    public function generarReportePorUbicaciones() {
        try {
            // Obtener parámetros
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : null;
            
            $reportes = new Reportes();
            
            // Obtener estadísticas por ubicación
            $ubicaciones = $reportes->obtenerEstadisticasPorUbicacion($fecha_inicio, $fecha_fin, $limite);
            
            // Calcular totales
            $total_general = array_sum(array_column($ubicaciones, 'total'));
            
            // Formatear respuesta
            $ubicaciones_formateadas = array();
            $zonas_criticas = array();
            
            foreach ($ubicaciones as $ubicacion) {
                $porcentaje = $total_general > 0 ? 
                    round(($ubicacion['total'] / $total_general) * 100, 2) : 0;
                
                $es_zona_critica = $ubicacion['pendientes'] > 5 || 
                                  ($ubicacion['pendientes'] / max($ubicacion['total'], 1)) > 0.7;
                
                $ubicacion_data = array(
                    "zona" => $ubicacion['zona'],
                    "total_denuncias" => (int)$ubicacion['total'],
                    "pendientes" => (int)$ubicacion['pendientes'],
                    "en_proceso" => (int)$ubicacion['en_proceso'],
                    "resueltas" => (int)$ubicacion['resueltas'],
                    "porcentaje_del_total" => $porcentaje . "%",
                    "tasa_resolucion" => round($ubicacion['tasa_resolucion'], 2) . "%",
                    "es_zona_critica" => $es_zona_critica,
                    "problemas_principales" => $reportes->obtenerProblemasPorZona($ubicacion['zona'], 3)
                );
                
                $ubicaciones_formateadas[] = $ubicacion_data;
                
                if ($es_zona_critica) {
                    $zonas_criticas[] = $ubicacion['zona'];
                }
            }
            
            sendJsonResponse(array(
                "success" => true,
                "message" => "Reporte por ubicaciones generado exitosamente",
                "data" => array(
                    "periodo" => array(
                        "fecha_inicio" => $fecha_inicio ?? "Inicio de registros",
                        "fecha_fin" => $fecha_fin ?? date('Y-m-d')
                    ),
                    "resumen" => array(
                        "total_zonas" => count($ubicaciones_formateadas),
                        "total_denuncias" => $total_general,
                        "zonas_criticas" => $zonas_criticas,
                        "zona_mas_afectada" => $ubicaciones_formateadas[0]['zona'] ?? null
                    ),
                    "ubicaciones" => $ubicaciones_formateadas,
                    "mapa_calor" => $this->generarMapaCalor($ubicaciones_formateadas),
                    "recomendaciones" => $this->generarRecomendacionesUbicaciones($ubicaciones_formateadas)
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
    
    /**
     * ENDPOINT: GET /api/reportes/temporal
     * Generar reporte temporal (tendencias)
     */
    public function generarReporteTemporal() {
        try {
            // Obtener parámetros
            $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes'; // dia, semana, mes
            $cantidad = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 12;
            
            // Validar periodo
            $periodos_validos = array('dia', 'semana', 'mes');
            if (!in_array($periodo, $periodos_validos)) {
                sendJsonResponse(array(
                    "success" => false,
                    "message" => "Periodo inválido",
                    "periodos_validos" => $periodos_validos
                ), 400);
            }
            
            $reportes = new Reportes();
            
            // Obtener datos temporales
            $evolucion = $reportes->obtenerEvolucionPorPeriodo($periodo, $cantidad);
            $comparacion = $reportes->obtenerComparacionPeriodos($periodo);
            $proyeccion = $this->calcularProyeccion($evolucion);
            $estacionalidad = $reportes->analizarEstacionalidad();
            
            sendJsonResponse(array(
                "success" => true,
                "message" => "Reporte temporal generado exitosamente",
                "data" => array(
                    "configuracion" => array(
                        "periodo" => $periodo,
                        "cantidad_periodos" => $cantidad,
                        "fecha_generacion" => date('Y-m-d H:i:s')
                    ),
                    "evolucion" => $evolucion,
                    "comparacion_periodos" => $comparacion,
                    "analisis" => array(
                        "tendencia_general" => $this->analizarTendencia($evolucion),
                        "periodo_pico" => $this->obtenerPeriodoPico($evolucion),
                        "variacion_promedio" => $this->calcularVariacion($evolucion),
                        "proyeccion_siguiente_periodo" => $proyeccion
                    ),
                    "estacionalidad" => $estacionalidad,
                    "insights" => $this->generarInsightsTemporal($evolucion, $comparacion)
                )
            ), 200);
            
        } catch(Exception $e) {
            sendJsonResponse(array(
                "success" => false,
                "message" => "Error al generar reporte