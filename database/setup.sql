-- ================================================
-- Base de datos EcoDenunciasLP - Jonathan Zambrano
-- ================================================

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS ecodenuncia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Seleccionar la base de datos
USE ecodenuncia_db;

-- Crear tabla denuncias
CREATE TABLE denuncias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_problema ENUM(
        'contaminacion_agua',
        'contaminacion_aire', 
        'deforestacion',
        'manejo_residuos',
        'ruido_excesivo',
        'contaminacion_suelo',
        'otros'
    ) NOT NULL,
    descripcion TEXT NOT NULL,
    ubicacion_lat DECIMAL(10, 8) NULL,
    ubicacion_lng DECIMAL(11, 8) NULL,
    ubicacion_direccion VARCHAR(255) NOT NULL,
    imagen_url VARCHAR(500) NULL,
    estado ENUM('pendiente', 'en_proceso', 'resuelta') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para mejorar performance
    INDEX idx_fecha_creacion (fecha_creacion),
    INDEX idx_estado (estado),
    INDEX idx_tipo_problema (tipo_problema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla comentarios
CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denuncia_id INT NOT NULL,
    nombre_usuario VARCHAR(100) NOT NULL,
    comentario TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Clave foránea y índices
    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE,
    INDEX idx_denuncia_id (denuncia_id),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- DATOS DE PRUEBA PARA DESARROLLO
-- ================================================

-- Insertar denuncias de ejemplo
INSERT INTO denuncias (tipo_problema, descripcion, ubicacion_direccion, estado, fecha_creacion) VALUES
('contaminacion_agua', 'Vertido de residuos industriales en el Río Verde afectando la calidad del agua', 'Río Verde, Sector Industrial, Guayaquil', 'pendiente', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('deforestacion', 'Tala ilegal de árboles en área protegida sin permisos correspondientes', 'Área Verde Protegida, Vía a la Costa', 'pendiente', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('manejo_residuos', 'Acumulación de basura en parque público sin recolección por más de una semana', 'Parque Central, Samborondón', 'en_proceso', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('contaminacion_aire', 'Emisión excesiva de gases contaminantes de fábrica textil', 'Zona Industrial Norte, Guayaquil', 'en_proceso', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('ruido_excesivo', 'Construcción nocturna con maquinaria pesada en zona residencial', 'Ciudadela Las Flores, Guayaquil', 'pendiente', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('contaminacion_suelo', 'Derrame de químicos en terreno cerca de escuela primaria', 'Sector Los Ceibos, Guayaquil', 'pendiente', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Insertar comentarios de ejemplo
INSERT INTO comentarios (denuncia_id, nombre_usuario, comentario) VALUES
(1, 'Sofia Ramirez', 'Esto requiere atención inmediata de las autoridades'),
(1, 'Carlos Gomez', 'Estoy de acuerdo, la situación es muy grave'),
(1, 'Jonathan Zambrano', 'He documentado el problema con fotografías adicionales'),
(2, 'Ana Lopez', 'He visto camiones llevándose los árboles ilegalmente'),
(2, 'Miguel Santos', 'Deberían implementar más vigilancia en la zona'),
(3, 'Miguel Torres', 'La gestión de residuos en esta zona es deficiente'),
(3, 'Lucia Mendez', 'Es un problema recurrente que afecta a toda la comunidad'),
(4, 'Pedro Vargas', 'Los vapores llegan hasta mi casa, es preocupante'),
(5, 'Maria Flores', 'No podemos dormir por el ruido constante'),
(6, 'Roberto Silva', 'Situación de emergencia cerca de niños');

-- ================================================
-- VERIFICAR LA INSTALACIÓN
-- ================================================

-- Mostrar estadísticas de las tablas creadas
SELECT 'denuncias' as tabla, COUNT(*) as total_registros FROM denuncias
UNION ALL
SELECT 'comentarios' as tabla, COUNT(*) as total_registros FROM comentarios;

-- Mostrar resumen por estado
SELECT estado, COUNT(*) as cantidad 
FROM denuncias 
GROUP BY estado 
ORDER BY cantidad DESC;

-- Mostrar resumen por tipo de problema
SELECT tipo_problema, COUNT(*) as cantidad 
FROM denuncias 
GROUP BY tipo_problema 
ORDER BY cantidad DESC;

-- ================================================
-- ENDPOINTS PARA PROBAR DESPUÉS DE LA INSTALACIÓN
-- ================================================

/*
OPCIÓN 1: Con localhost (HTTP)
1. Página principal de la API:
   http://localhost:8080/EcoDenunciasLP/api/

2. Setup automático:
   http://localhost:8080/EcoDenunciasLP/api/setup

3. Health check:
   http://localhost:8080/EcoDenunciasLP/api/health

4. Documentación completa:
   http://localhost:8080/EcoDenunciasLP/api/docs

5. Resumen semanal:
   http://localhost:8080/EcoDenunciasLP/api/denuncias/resumen-semanal

OPCIÓN 2: Con 127.0.0.1 (puede requerir HTTPS)
1. Página principal de la API:
   https://127.0.0.1:8080/EcoDenunciasLP/api/

2. Setup automático:
   https://127.0.0.1:8080/EcoDenunciasLP/api/setup

3. Health check:
   https://127.0.0.1:8080/EcoDenunciasLP/api/health

4. Documentación completa:
   https://127.0.0.1:8080/EcoDenunciasLP/api/docs

5. Resumen semanal:
   https://127.0.0.1:8080/EcoDenunciasLP/api/denuncias/resumen-semanal

FUNCIONALIDADES ADICIONALES:
6. Resumen filtrado por zona:
   http://localhost:8080/EcoDenunciasLP/api/denuncias/resumen-semanal?zona=Guayaquil

7. Resumen filtrado por tipo:
   http://localhost:8080/EcoDenunciasLP/api/denuncias/resumen-semanal?categoria=contaminacion_agua

8. Comentarios de una denuncia:
   http://localhost:8080/EcoDenunciasLP/api/comentarios/1

9. Crear comentario (POST):
   http://localhost:8080/EcoDenunciasLP/api/comentarios
   Body: {"denuncia_id": 1, "nombre_usuario": "Tu Nombre", "comentario": "Tu comentario aquí"}
*/
