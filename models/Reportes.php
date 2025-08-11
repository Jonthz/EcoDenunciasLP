<?php
require_once '../config/database.php';

class Reportes {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Reporte general
    public function obtenerEstadisticasGenerales($fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        // Totales por estado
        $sql = "SELECT 
                    COUNT(*) as total_denuncias,
                    SUM(estado = 'pendiente') as pendientes,
                    SUM(estado = 'en_proceso') as en_proceso,
                    SUM(estado = 'resuelta') as resueltas,
                    AVG(DATEDIFF(fecha_actualizacion, fecha_creacion)) as promedio_dias_resolucion
                FROM denuncias
                $where_sql";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tasa de resolución
        $row['tasa_resolucion'] = $row['total_denuncias'] > 0
            ? ($row['resueltas'] / $row['total_denuncias']) * 100 : 0;

        // Promedio denuncias diarias
        $sql2 = "SELECT COUNT(*) as total, DATEDIFF(MAX(fecha_creacion), MIN(fecha_creacion)) as dias 
                 FROM denuncias $where_sql";
        $stmt2 = $this->conn->prepare($sql2);
        foreach ($params as $k => $v) $stmt2->bindValue($k, $v);
        $stmt2->execute();
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        $dias = max(1, $row2['dias']);
        $row['promedio_denuncias_diarias'] = $row2['total'] / $dias;

        return $row;
    }

    // Distribución de estados
    public function obtenerDistribucionEstados($fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT estado, COUNT(*) as cantidad FROM denuncias $where_sql GROUP BY estado";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['estado']] = (int)$row['cantidad'];
        }
        return $result;
    }

    // Top categorías
    public function obtenerTopCategorias($limite = 5, $fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT tipo_problema as categoria, COUNT(*) as total
                FROM denuncias $where_sql
                GROUP BY tipo_problema
                ORDER BY total DESC
                LIMIT :limite";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Top ubicaciones
    public function obtenerTopUbicaciones($limite = 5, $fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT ubicacion_direccion as ubicacion, COUNT(*) as total
                FROM denuncias $where_sql
                GROUP BY ubicacion_direccion
                ORDER BY total DESC
                LIMIT :limite";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Evolución temporal (por día)
    public function obtenerEvolucionTemporal($fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT DATE(fecha_creacion) as periodo, COUNT(*) as total
                FROM denuncias $where_sql
                GROUP BY DATE(fecha_creacion)
                ORDER BY periodo ASC";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Datos para exportación general
    public function obtenerDatosExportacionGeneral($fecha_inicio = null, $fecha_fin = null) {
        $where = [];
        $params = [];
        if ($fecha_inicio) {
            $where[] = "fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $where[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT id, tipo_problema, descripcion, ubicacion_direccion, estado, fecha_creacion, fecha_actualizacion
                FROM denuncias $where_sql
                ORDER BY fecha_creacion DESC";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Datos para exportación por categorías
    public function obtenerDatosExportacionCategorias($fecha_inicio = null, $fecha_fin = null) {
        $sql = "SELECT tipo_problema as categoria, COUNT(*) as total
                FROM denuncias
                WHERE (:fecha_inicio IS NULL OR fecha_creacion >= :fecha_inicio)
                  AND (:fecha_fin IS NULL OR fecha_creacion <= :fecha_fin)
                GROUP BY tipo_problema
                ORDER BY total DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':fecha_inicio', $fecha_inicio);
        $stmt->bindValue(':fecha_fin', $fecha_fin);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Datos para exportación por ubicaciones
    public function obtenerDatosExportacionUbicaciones($fecha_inicio = null, $fecha_fin = null) {
        $sql = "SELECT ubicacion_direccion as ubicacion, COUNT(*) as total
                FROM denuncias
                WHERE (:fecha_inicio IS NULL OR fecha_creacion >= :fecha_inicio)
                  AND (:fecha_fin IS NULL OR fecha_creacion <= :fecha_fin)
                GROUP BY ubicacion_direccion
                ORDER BY total DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':fecha_inicio', $fecha_inicio);
        $stmt->bindValue(':fecha_fin', $fecha_fin);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>