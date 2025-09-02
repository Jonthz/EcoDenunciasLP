<?php
require_once '../models/Denuncia.php';

class DenunciasController {
    
    /**
     * Crear nueva denuncia ambiental
     */
    public function crearDenuncia() {
        try {
            // Validar método HTTP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJsonResponse([
                    "success" => false,
                    "message" => "Método no permitido. Use POST"
                ], 405);
                return;
            }

            // Obtener datos tanto de JSON como form-data
            $input_data = $this->obtenerDatosEntrada();
            
            // Validación completa de datos
            $validacion = $this->validarDatosDenuncia($input_data);
            if (!$validacion['valido']) {
                sendJsonResponse([
                    "success" => false,
                    "message" => "Datos inválidos",
                    "errores" => $validacion['errores']
                ], 400);
                return;
            }

            // Procesar imagen si existe
            $imagen_path = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagen_resultado = $this->procesarImagen($_FILES['imagen']);
                if ($imagen_resultado['success']) {
                    $imagen_path = $imagen_resultado['path'];
                } else {
                    sendJsonResponse([
                        "success" => false,
                        "message" => "Error al procesar imagen: " . $imagen_resultado['error']
                    ], 400);
                    return;
                }
            }

            // Usar el modelo existente
            $denuncia = new Denuncia();
            
            // Preparar datos según estructura de BD
            $datos_denuncia = [
                'tipo_problema' => $input_data['categoria'], // Mapear categoria -> tipo_problema
                'descripcion' => trim($input_data['descripcion']),
                'ubicacion_direccion' => trim($input_data['ubicacion']), // Mapear ubicacion -> ubicacion_direccion
                'ubicacion_lat' => !empty($input_data['latitud']) ? (float)$input_data['latitud'] : null, // Mapear latitud -> ubicacion_lat
                'ubicacion_lng' => !empty($input_data['longitud']) ? (float)$input_data['longitud'] : null, // Mapear longitud -> ubicacion_lng
                'imagen_url' => $imagen_path, // Mapear imagen_evidencia -> imagen_url
                'estado' => 'pendiente',
                'fecha_creacion' => date('Y-m-d H:i:s')
            ];

            // Insertar usando método del modelo
            $resultado = $denuncia->crear($datos_denuncia);
            
            if ($resultado['success']) {
                // Respuesta completa con información útil
                sendJsonResponse([
                    "success" => true,
                    "message" => "Denuncia creada exitosamente",
                    "data" => [
                        "denuncia_id" => $resultado['id'],
                        "numero_folio" => $this->generarFolio($resultado['id']),
                        "estado" => "pendiente",
                        "prioridad" => $this->determinarPrioridad($input_data),
                        "fecha_creacion" => $datos_denuncia['fecha_creacion'],
                        "imagen_subida" => $imagen_path ? true : false,
                        "coordenadas_registradas" => ($datos_denuncia['ubicacion_lat'] && $datos_denuncia['ubicacion_lng']) ? true : false,
                        "contacto_registrado" => !empty($input_data['email_reportante']) ? true : false
                    ]
                ], 201);
            } else {
                sendJsonResponse([
                    "success" => false,
                    "message" => "Error al crear denuncia: " . $resultado['error']
                ], 500);
            }

        } catch (Exception $e) {
            sendJsonResponse([
                "success" => false,
                "message" => "Error interno del servidor",
                "error" => ENVIRONMENT === 'development' ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Obtener datos de entrada (JSON o form-data)
     */
    private function obtenerDatosEntrada() {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($content_type, 'application/json') !== false) {
            // Datos JSON
            $json_input = file_get_contents('php://input');
            $datos = json_decode($json_input, true);
            return $datos ?? [];
        } else {
            // Datos de formulario
            return $_POST;
        }
    }

    /**
     * Validar datos de la denuncia
     */
    private function validarDatosDenuncia($datos) {
        $errores = [];
        
        // Descripción requerida y con longitud mínima
        if (empty($datos['descripcion'])) {
            $errores[] = "La descripción es requerida";
        } elseif (strlen(trim($datos['descripcion'])) < 10) {
            $errores[] = "La descripción debe tener al menos 10 caracteres";
        } elseif (strlen(trim($datos['descripcion'])) > 2000) {
            $errores[] = "La descripción no puede exceder 2000 caracteres";
        }
        
        // Categoría requerida y válida
        $categorias_validas = [
            'contaminacion_agua', 'contaminacion_aire', 'deforestacion', 
            'manejo_residuos', 'ruido_excesivo', 'contaminacion_suelo', 'otros'
        ];
        
        if (empty($datos['categoria'])) {
            $errores[] = "La categoría es requerida";
        } elseif (!in_array($datos['categoria'], $categorias_validas)) {
            $errores[] = "Categoría inválida. Valores permitidos: " . implode(', ', $categorias_validas);
        }
        
        // Ubicación requerida y específica
        if (empty($datos['ubicacion'])) {
            $errores[] = "La ubicación es requerida";
        } elseif (strlen(trim($datos['ubicacion'])) < 5) {
            $errores[] = "La ubicación debe ser más específica (mínimo 5 caracteres)";
        } elseif (strlen(trim($datos['ubicacion'])) > 255) {
            $errores[] = "La ubicación no puede exceder 255 caracteres";
        }
        
        // Validar coordenadas si se proporcionan
        $latitud_presente = !empty($datos['latitud']);
        $longitud_presente = !empty($datos['longitud']);
        
        if ($latitud_presente || $longitud_presente) {
            if (!$latitud_presente || !$longitud_presente) {
                $errores[] = "Si proporciona coordenadas, debe incluir tanto latitud como longitud";
            } else {
                $lat = (float)$datos['latitud'];
                $lng = (float)$datos['longitud'];
                
                if ($lat < -90 || $lat > 90) {
                    $errores[] = "La latitud debe estar entre -90 y 90 grados";
                }
                if ($lng < -180 || $lng > 180) {
                    $errores[] = "La longitud debe estar entre -180 y 180 grados";
                }
            }
        }
        
        // Validar email si se proporciona
        if (!empty($datos['email_reportante'])) {
            if (!filter_var($datos['email_reportante'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El email proporcionado no es válido";
            } elseif (strlen($datos['email_reportante']) > 100) {
                $errores[] = "El email no puede exceder 100 caracteres";
            }
        }
        
        // Validar nombre si se proporciona
        if (!empty($datos['nombre_reportante'])) {
            if (strlen(trim($datos['nombre_reportante'])) < 2) {
                $errores[] = "El nombre debe tener al menos 2 caracteres";
            } elseif (strlen(trim($datos['nombre_reportante'])) > 100) {
                $errores[] = "El nombre no puede exceder 100 caracteres";
            }
        }
        
        // Validar teléfono si se proporciona
        if (!empty($datos['telefono_reportante'])) {
            $telefono = preg_replace('/[^0-9+\-\s]/', '', $datos['telefono_reportante']);
            if (strlen($telefono) < 7 || strlen($telefono) > 20) {
                $errores[] = "El teléfono debe tener entre 7 y 20 dígitos";
            }
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Procesar imagen subida
     */
    private function procesarImagen($archivo) {
        // Verificar errores de subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco',
                UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión'
            ];
            
            return [
                'success' => false, 
                'error' => $errores[$archivo['error']] ?? 'Error desconocido al subir archivo'
            ];
        }

        // Validar tipo de archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, UPLOAD_ALLOWED_TYPES)) {
            return [
                'success' => false, 
                'error' => 'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', UPLOAD_ALLOWED_TYPES)
            ];
        }

        // Validar tamaño
        if ($archivo['size'] > UPLOAD_MAX_SIZE) {
            return [
                'success' => false, 
                'error' => 'Archivo muy grande. Máximo permitido: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB'
            ];
        }

        // Validar que sea realmente una imagen
        $image_info = getimagesize($archivo['tmp_name']);
        if ($image_info === false) {
            return ['success' => false, 'error' => 'El archivo no es una imagen válida'];
        }

        // Generar nombre único y seguro
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $filename = 'denuncia_' . uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . strtolower($extension);

        // Ruta física en el servidor
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/EcoDenunciasLP/uploads/denuncias/';
        $filepath = $upload_path . $filename;

        // Crear directorio si no existe
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        // Mover archivo
        if (move_uploaded_file($archivo['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'path' => 'uploads/denuncias/' . $filename, // Esta es la ruta relativa para la URL
                'size' => $archivo['size'],
                'type' => $mime_type
            ];
        } else {
            return ['success' => false, 'error' => 'Error al mover archivo al directorio de destino'];
        }
    }

    /**
     * Determinar prioridad basada en categoría y palabras clave
     */
    private function determinarPrioridad($datos) {
        $descripcion = strtolower($datos['descripcion'] ?? '');
        $categoria = $datos['categoria'] ?? '';
        
        // Palabras clave que indican alta prioridad
        $palabras_criticas = ['urgente', 'crítico', 'peligroso', 'tóxico', 'muerte', 'hospital', 'emergencia'];
        $palabras_altas = ['grave', 'severo', 'importante', 'preocupante', 'riesgo', 'salud'];
        
        // Verificar palabras críticas
        foreach ($palabras_criticas as $palabra) {
            if (strpos($descripcion, $palabra) !== false) {
                return 'critica';
            }
        }
        
        // Verificar palabras de alta prioridad
        foreach ($palabras_altas as $palabra) {
            if (strpos($descripcion, $palabra) !== false) {
                return 'alta';
            }
        }
        
        // Prioridad por categoría
        $prioridades_categoria = [
            'contaminacion_agua' => 'alta',
            'contaminacion_suelo' => 'alta',
            'contaminacion_aire' => 'media',
            'deforestacion' => 'media',
            'manejo_residuos' => 'media',
            'ruido_excesivo' => 'baja',
            'otros' => 'media'
        ];
        
        return $prioridades_categoria[$categoria] ?? 'media';
    }

    /**
     * Generar número de folio único
     */
    private function generarFolio($id) {
        return 'ECO-' . date('Y') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener denuncia por ID
     */
    public function obtenerDenuncia($id) {
        try {
            if (!is_numeric($id) || $id <= 0) {
                sendJsonResponse([
                    "success" => false,
                    "message" => "ID de denuncia inválido"
                ], 400);
                return;
            }

            $denuncia = new Denuncia();
            $resultado = $denuncia->obtenerDenunciaCompleta($id);

            if ($resultado) {
                sendJsonResponse([
                    "success" => true,
                    "data" => $resultado
                ], 200);
            } else {
                sendJsonResponse([
                    "success" => false,
                    "message" => "Denuncia no encontrada"
                ], 404);
            }

        } catch (Exception $e) {
            sendJsonResponse([
                "success" => false,
                "message" => "Error al obtener denuncia",
                "error" => ENVIRONMENT === 'development' ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }
}
?>