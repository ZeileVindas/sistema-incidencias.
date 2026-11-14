# Sistema de Gestión de Incidencias de TI

Plataforma web centralizada para el registro, asignación, seguimiento y resolución de requerimientos técnicos[span_0](start_span)[span_0](end_span)[span_1](start_span)[span_1](end_span)[span_2](start_span)[span_2](end_span).

**Proyecto Final - Programación Avanzada (UISIL)**[span_3](start_span)[span_3](end_span)[span_4](start_span)[span_4](end_span)

## Descripcion
Sistema web desarrollado para optimizar la atención de soporte técnico, permitiendo a los usuarios reportar incidencias y al personal técnico gestionar la cola de trabajo, actualizar estados y registrar notas en una bitácora persistente[span_5](start_span)[span_5](end_span)[span_6](start_span)[span_6](end_span)[span_7](start_span)[span_7](end_span).

## Stack Tecnologico
* **Backend:** PHP 8.1, PDO[span_8](start_span)[span_8](end_span)[span_9](start_span)[span_9](end_span)[span_10](start_span)[span_10](end_span)
* **Base de Datos:** MySQL 8.0 (InnoDB)[span_11](start_span)[span_11](end_span)[span_12](start_span)[span_12](end_span)
* **Frontend:** HTML5, CSS3, Bootstrap 5[span_13](start_span)[span_13](end_span)[span_14](start_span)[span_14](end_span)
* **Seguridad:** Hashing de contraseñas (`password_hash`), sentencias preparadas y control de acceso por roles (RBAC)[span_15](start_span)[span_15](end_span)[span_16](start_span)[span_16](end_span).

## Modulos Principales
1. **Autenticación (`login.php`):** Control de acceso seguro por roles[span_17](start_span)[span_17](end_span)[span_18](start_span)[span_18](end_span).
2. **Registro (`crear_incidencia.php`):** Apertura de tickets con categoría y prioridad[span_19](start_span)[span_19](end_span)[span_20](start_span)[span_20](end_span).
3. **Historial (`mis_incidencias.php`):** Consulta en tiempo real de los reportes del usuario[span_21](start_span)[span_21](end_span).
4. **Panel Técnico (`admin_incidencias.php`):** Cola general de tickets para el personal de soporte[span_22](start_span)[span_22](end_span).
5. **Bitácora (`ver_incidencia.php`):** Actualización de estados y notas técnicas de seguimiento[span_23](start_span)[span_23](end_span)[span_24](start_span)[span_24](end_span)[span_25](start_span)[span_25](end_span).

## Autora
**Zeile Nahomy Vindas Valverde**[span_26](start_span)[span_26](end_span)
