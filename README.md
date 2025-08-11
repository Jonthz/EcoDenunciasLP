# EcoDenunciasLP

Este proyecto tiene como objetivo principal el desarrollo de una plataforma para la gestión y seguimiento de propuestas de Licitación Pública (LP) para el año 2025, orientada a optimizar los procesos internos y mejorar la toma de decisiones.

## Funcionalidades Implementadas

### 1. Registro y Gestión de Propuestas
- **Alta de propuestas:** Permite registrar nuevas propuestas de licitación, capturando información relevante como nombre, descripción, fecha de inicio y responsables.
- **Edición y actualización:** Posibilidad de modificar los datos de propuestas existentes.
- **Eliminación lógica:** Las propuestas pueden ser marcadas como inactivas sin ser eliminadas físicamente de la base de datos.

### 2. Seguimiento de Propuestas
- **Estados de avance:** Cada propuesta puede tener un estado (en revisión, aprobada, rechazada, etc.) para facilitar el seguimiento.
- **Historial de cambios:** Registro de todas las modificaciones realizadas sobre cada propuesta, permitiendo auditoría y trazabilidad.

### 3. Gestión de Usuarios
- **Roles y permisos:** Implementación de diferentes roles de usuario (administrador, gestor, consultor) con permisos diferenciados según el tipo de usuario.
- **Autenticación:** Sistema de login seguro para acceso a la plataforma.

### 4. Reportes y Estadísticas
- **Generación de reportes:** Exportación de reportes en formatos PDF y Excel sobre el estado y avance de las propuestas.
- **Panel de estadísticas:** Visualización gráfica de indicadores clave como número de propuestas por estado, responsables más activos, etc.

### 5. Notificaciones
- **Alertas automáticas:** Envío de notificaciones por correo electrónico ante cambios importantes en las propuestas o fechas límite próximas.

---

## Tecnologías y dependencias

- **Versión de PHP:** 8.2.12
- **Servidor:** Apache/2.4.58 (Win64)
- **Base de datos:** MySQL/MariaDB **local** (XAMPP)
- **Librerías principales:**
  - **PDO:** Para acceso seguro a la base de datos.
  - **PHPMailer:** (si está implementado) para envío de correos electrónicos.
  - **PHPExcel o PhpSpreadsheet:** (si está implementado) para exportación de reportes en Excel.
  - **TCPDF o Dompdf:** (si está implementado) para generación de reportes PDF.
  - **Otras:** Las que se indiquen en el archivo `composer.json` o documentación técnica.

---

## Instalación

1. Clona el repositorio.
2. Instala las dependencias con `composer install` (si aplica) o el gestor correspondiente.
3. Configura las variables de entorno según el archivo `.env.example` o los archivos de configuración.
4. Ejecuta el proyecto en tu servidor local (XAMPP, WAMP, etc.) o despliega en tu entorno de preferencia.

## Contribuciones

Las contribuciones son bienvenidas. Por favor, abre un issue o pull request para sugerencias o mejoras.

---

## Licencia

Este proyecto está bajo la licencia MIT.

---

**Nota:** Este README.md está basado en las funcionalidades actualmente implementadas según la propuesta inicial. Para información más detallada, consulta la documentación técnica o contacta al equipo
