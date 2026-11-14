# Sistema de Gestión de Incidencias de TI

Plataforma web centralizada para el registro, asignación, seguimiento y resolución de requerimientos técnicos.

**Proyecto Final - Programación Avanzada (UISIL)**

## Descripcion
Sistema web desarrollado para optimizar la atención de soporte técnico, permitiendo a los usuarios reportar incidencias y al personal técnico gestionar la cola de trabajo, actualizar estados y registrar notas en una bitácora persistente.

## Stack Tecnologico
* **Backend:** PHP 8.1, PDO
* **Base de Datos:** MySQL 8.0 (InnoDB)
* **Frontend:** HTML5, CSS3, Bootstrap 5
* **Seguridad:** Hashing de contraseñas (`password_hash`), sentencias preparadas y control de acceso por roles (RBAC).

## Modulos Principales
1. **Autenticación (`login.php`):** Control de acceso seguro por roles.
2. **Registro (`crear_incidencia.php`):** Apertura de tickets con categoría y prioridad.
3. **Historial (`mis_incidencias.php`):** Consulta en tiempo real de los reportes del usuario.
4. **Panel Técnico (`admin_incidencias.php`):** Cola general de tickets para el personal de soporte.
5. **Bitácora (`ver_incidencia.php`):** Actualización de estados y notas técnicas de seguimiento.

## Autora
**Zeile Nahomy Vindas Valverde**
