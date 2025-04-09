<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/languages/english/admin/newpoints.lang.php)
 *    Author: Pirata Nervo
 *    Copyright: © 2009 Pirata Nervo
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

$l['newpoints'] = 'NewPoints';
$l['newpoints_description'] = 'Plugin NewPoints para MyBB - Un sistema de puntos complejo pero eficiente para MyBB.';
$l['newpoints_submit_button'] = 'Enviar';
$l['newpoints_reset_button'] = 'Restablecer';
$l['newpoints_error'] = 'Ha ocurrido un error desconocido.';
$l['newpoints_continue_button'] = 'Continuar';
$l['newpoints_click_continue'] = 'Haz clic en Continuar para proceder.';
$l['newpoints_delete'] = 'Eliminar';
$l['newpoints_missing_fields'] = 'Hay uno o más campos faltantes.';
$l['newpoints_edit'] = 'Editar';

///////////////// Plugins
$l['newpoints_plugins'] = 'Plugins';
$l['newpoints_plugins_description'] = 'Aquí puedes gestionar los plugins de NewPoints.';
$l['newpoints_plugin_incompatible'] = 'Este plugin es incompatible con NewPoints {1}';

$l['newpoints_plugins_check_updates'] = 'Comprobar Actualizaciones';
$l['newpoints_plugins_check_updates_description'] = 'Aquí puedes gestionar los plugins de NewPoints.';

$l['newpoints_plugins_error_version_check_no_supported_plugins'] = 'Ninguno de los plugins instalados soporta la verificación de versiones.';
$l['newpoints_plugins_error_communication_problem'] = 'Hubo un problema de comunicación con el servidor de modificaciones de MyBB. Por favor, intenta de nuevo en unos minutos.';
$l['newpoints_plugins_error_communication_problem_no_input'] = 'Código de error 1: No se especificó entrada.';
$l['newpoints_plugins_error_communication_problem_no_plugin_ids'] = 'Código de error 2: No se especificaron IDs de plugin.';
$l['newpoints_plugins_error_version_check_vulnerable'] = '[Plugin vulnerable]:';
$l['newpoints_plugins_error_version_vulnerable_notes'] = 'Esta modificación ha sido marcada como vulnerable por el personal de MyBB. Recomendamos la eliminación completa de esta modificación. Por favor, consulta las notas a continuación: ';

$l['newpoints_plugins_success_plugins_up_to_date'] = 'Felicidades, todos tus plugins están actualizados.';

$l['newpoints_plugins_plugin'] = 'Plugin';
$l['newpoints_plugins_your_version'] = 'Tu Versión';
$l['newpoints_plugins_latest_version'] = 'Última Versión';
$l['newpoints_plugins_deactivate'] = 'Desactivar';
$l['newpoints_plugins_download'] = 'Descargar';
$l['newpoints_plugins_plugin_updates'] = 'Actualizaciones de Plugin';

$l['active_plugin'] = 'Plugins Activos';
$l['inactive_plugin'] = 'Plugins Inactivos';
$l['activate'] = 'Activar';
$l['install_and_activate'] = 'Instalar y Activar';
$l['uninstall'] = 'Desinstalar';
$l['created_by'] = 'Creado por';
$l['no_plugins'] = 'No hay plugins en tu foro en este momento.';
$l['no_active_plugins'] = 'No hay plugins activos en tu foro.';
$l['no_inactive_plugins'] = 'No hay plugins inactivos disponibles.';

///////////////// Settings
$l['newpoints_settings'] = 'Configuraciones';
$l['newpoints_settings_description'] = 'Aquí puedes gestionar las configuraciones de NewPoints.';
$l['newpoints_settings_change'] = 'Cambiar';
$l['newpoints_settings_change_description'] = 'Cambiar configuraciones.';
$l['newpoints_select_plugin'] = 'Debes seleccionar un grupo.';

///////////////// Log
$l['newpoints_log'] = 'Registro';
$l['newpoints_log_description'] = 'Gestionar entradas de registro.';
$l['newpoints_log_action'] = 'Acción';
$l['newpoints_log_data'] = 'Datos';
$l['newpoints_log_user'] = 'Usuario';
$l['newpoints_log_date'] = 'Fecha';
$l['newpoints_log_options'] = 'Opciones';
$l['newpoints_no_log_entries'] = 'No se encontraron entradas de registro.';
$l['newpoints_log_entries'] = 'Entradas de registro';
$l['newpoints_log_notice'] = 'Nota: algunas estadísticas se basan en entradas de registro.';
$l['newpoints_log_deleteconfirm'] = '¿Estás seguro de que deseas eliminar la entrada de registro seleccionada?';
$l['newpoints_log_invalid'] = 'Entrada de registro inválida.';
$l['newpoints_log_deleted'] = 'Entrada de registro eliminada exitosamente.';
$l['newpoints_log_prune'] = 'Purgar entradas de registro';
$l['newpoints_older_than'] = 'Más antiguo que';
$l['newpoints_older_than_desc'] = 'Purgar entradas de registro más antiguas que el número de días que ingreses.';
$l['newpoints_log_pruned'] = 'Entradas de registro purgadas exitosamente.';
$l['newpoints_log_pruneconfirm'] = '¿Estás seguro de que deseas purgar las entradas de registro?';
$l['newpoints_invalid_username'] = 'Nombre de usuario seleccionado inválido.';
$l['newpoints_log_filter'] = 'Filtros';
$l['newpoints_filter_username'] = 'Nombre de usuario';
$l['newpoints_filter_username_desc'] = 'Ingresa un nombre de usuario para filtrar. Esto puede estar vacío.';
$l['newpoints_filter_actions'] = 'Acciones';
$l['newpoints_filter_actions_desc'] = 'Selecciona las acciones que deseas filtrar.';
$l['newpoints_select_actions'] = 'Seleccionar Acciones';
$l['newpoints_filter'] = 'Filtros habilitados:<br />{1}';
$l['newpoints_username'] = 'Nombre de usuario';

///////////////// Maintenance
$l['newpoints_recount_from_logs'] = 'Recontar NewPoints de Usuario Desde Registros';
$l['newpoints_recount_from_logs_description'] = 'Cuando esto se ejecute, la cantidad de NewPoints para cada usuario se actualizará para reflejar la resta aritmética de los registros de cargos de los registros de ingresos.';
$l['newpoints_recount_from_logs_success'] = 'La cantidad de NewPoints para los usuarios se ha reconstruido exitosamente a partir de los registros.';

$l['newpoints_recount'] = 'Recontar NewPoints de Usuario (Obsoleto)';
$l['newpoints_recount_desc'] = 'Cuando esto se ejecute, la cantidad de NewPoints para cada usuario se actualizará para reflejar su valor actual en vivo basado en la configuración de ingresos.';
$l['newpoints_recount_success'] = 'La cantidad de NewPoints para los usuarios se ha reconstruido exitosamente.';
$l['newpoints_reset'] = 'Restablecer NewPoints de Usuario';
$l['newpoints_reset_desc'] = 'Cuando esto se ejecute, la cantidad de NewPoints para cada usuario se actualizará para reflejar este valor.';
$l['newpoints_invalid_user'] = 'Usuario inválido.';

///////////////// Stats
$l['newpoints_stats'] = 'Estadísticas';
$l['newpoints_stats_description'] = 'Ver las estadísticas de tu foro.';
$l['newpoints_stats_lastdonations'] = 'Últimas Donaciones';
$l['newpoints_error_gathering'] = 'No se pudo recopilar ningún dato.';
$l['newpoints_stats_richest_users'] = 'Usuarios más ricos';
$l['newpoints_stats_from'] = 'De';
$l['newpoints_stats_to'] = 'A';
$l['newpoints_stats_date'] = 'Fecha';
$l['newpoints_stats_user'] = 'Usuario';
$l['newpoints_stats_points'] = 'Puntos';
$l['newpoints_stats_amount'] = 'Cantidad';

///////////////// Forum Rules
$l['newpoints_forumrules'] = 'Reglas del Foro';
$l['newpoints_forumrules_description'] = 'Gestionar las reglas y opciones del foro.';
$l['newpoints_forumrules_add'] = 'Agregar';
$l['newpoints_forumrules_add_description'] = 'Agregar una nueva regla.';
$l['newpoints_forumrules_edit'] = 'Editar';
$l['newpoints_forumrules_edit_description'] = 'Editar una regla existente.';
$l['newpoints_forumrules_delete'] = 'Eliminar';
$l['newpoints_forumrules_title'] = 'Título del Foro';
$l['newpoints_forumrules_name'] = 'Nombre de la Regla';
$l['newpoints_forumrules_options'] = 'Opciones';
$l['newpoints_forumrules_none'] = 'No se encontraron reglas.';
$l['newpoints_forumrules_rules'] = 'Reglas del Foro';
$l['newpoints_forumrules_addrule'] = 'Agregar Regla del Foro';
$l['newpoints_forumrules_editrule'] = 'Editar Regla del Foro';
$l['newpoints_forumrules_forum'] = 'Foro';
$l['newpoints_forumrules_forum_desc'] = 'Selecciona el foro afectado por esta regla.';
$l['newpoints_forumrules_name_desc'] = 'Ingresa el nombre de la regla.';
$l['newpoints_forumrules_desc'] = 'Descripción';
$l['newpoints_forumrules_desc_desc'] = 'Ingresa una descripción de la regla.';
$l['newpoints_forumrules_rate'] = 'Tasa de Ingreso';
$l['newpoints_forumrules_rate_desc'] = 'Ingresa la tasa de ingreso para el foro seleccionado. El valor predeterminado es 1.';
$l['newpoints_forumrules_added'] = 'Se ha agregado una nueva regla del foro exitosamente.';
$l['newpoints_select_forum'] = 'Seleccionar un foro';
$l['newpoints_forumrules_notice'] = 'Nota: los foros sin reglas tienen una tasa de ingreso de 1 y no tienen puntos mínimos para ver o publicar.';
$l['newpoints_forumrules_invalid'] = 'Regla inválida.';
$l['newpoints_forumrules_edited'] = 'La regla seleccionada ha sido editada exitosamente.';
$l['newpoints_forumrules_deleted'] = 'La regla seleccionada ha sido eliminada exitosamente.';
$l['newpoints_forumrules_deleteconfirm'] = '¿Estás seguro de que deseas eliminar la regla seleccionada?';

///////////////// Group Rules
$l['newpoints_grouprules'] = 'Reglas de Grupo de Usuario';
$l['newpoints_grouprules_description'] = 'Gestionar reglas y opciones de grupos de usuarios.';
$l['newpoints_grouprules_add'] = 'Agregar';
$l['newpoints_grouprules_add_description'] = 'Agregar una nueva regla.';
$l['newpoints_grouprules_edit'] = 'Editar';
$l['newpoints_grouprules_edit_description'] = 'Editar una regla existente.';
$l['newpoints_grouprules_delete'] = 'Eliminar';
$l['newpoints_grouprules_title'] = 'Título del Grupo';
$l['newpoints_grouprules_name'] = 'Nombre de la Regla';
$l['newpoints_grouprules_options'] = 'Opciones';
$l['newpoints_grouprules_none'] = 'No se encontraron reglas.';
$l['newpoints_grouprules_rules'] = 'Reglas de Grupo';
$l['newpoints_grouprules_addrule'] = 'Agregar Regla de Grupo';
$l['newpoints_grouprules_editrule'] = 'Editar Regla de Grupo';
$l['newpoints_grouprules_group'] = 'Grupo de Usuario';
$l['newpoints_grouprules_group_desc'] = 'Selecciona el grupo afectado por esta regla.';
$l['newpoints_grouprules_name_desc'] = 'Ingresa el nombre de la regla.';
$l['newpoints_grouprules_desc'] = 'Descripción';
$l['newpoints_grouprules_desc_desc'] = 'Ingresa una descripción de la regla.';
$l['newpoints_grouprules_rate'] = 'Tasa de Ingreso';
$l['newpoints_grouprules_rate_desc'] = 'Ingresa la tasa de ingreso para el grupo seleccionado. El valor predeterminado es 1.';
$l['newpoints_grouprules_added'] = 'Se ha agregado exitosamente una nueva regla de grupo de usuario.';
$l['newpoints_select_group'] = 'Seleccionar un grupo';
$l['newpoints_grouprules_notice'] = 'Nota: los grupos sin reglas tienen una tasa de ingreso de 1 y no tienen pagos automáticos configurados.';
$l['newpoints_grouprules_invalid'] = 'Regla inválida.';
$l['newpoints_grouprules_edited'] = 'La regla seleccionada ha sido editada exitosamente.';
$l['newpoints_grouprules_deleted'] = 'La regla seleccionada ha sido eliminada exitosamente.';
$l['newpoints_grouprules_deleteconfirm'] = '¿Estás seguro de que deseas eliminar la regla seleccionada?';

///////////////// Upgrades
$l['newpoints_upgrades'] = 'Actualizaciones';
$l['newpoints_upgrades_description'] = 'Actualiza NewPoints desde aquí.';
$l['newpoints_upgrades_name'] = 'Nombre';
$l['newpoints_upgrades_run'] = 'Ejecutar';
$l['newpoints_upgrades_confirm_run'] = '¿Estás seguro de que deseas ejecutar el archivo de actualización seleccionado?';
$l['newpoints_run'] = 'Ejecutar';
$l['newpoints_no_upgrades'] = 'No se encontraron actualizaciones.';
$l['newpoints_upgrades_notice'] = 'Deberías hacer una copia de seguridad de tu base de datos antes de ejecutar un script de actualización.<br /><small>Solo ejecuta archivos de actualización si estás seguro de lo que estás haciendo</small>';
$l['newpoints_upgrades_ran'] = 'El script de actualización se ejecutó exitosamente.';
$l['newpoints_upgrades_newversion'] = 'Nueva versión';

$l['newpoints_plugin_library'] = 'Este plugin requiere <a href="{1}">PluginLibrary</a> versión {2} o posterior para ser subido a tu foro.';

$l['setting_group_newpoints_donations'] = 'Donaciones';
$l['setting_group_newpoints_donations_desc'] = 'Estas configuraciones están relacionadas con las donaciones.';
$l['setting_newpoints_donations_flood_minutes'] = 'Control de Inundaciones: Minutos';
$l['setting_newpoints_donations_flood_minutes_desc'] = 'Número de minutos a esperar entre donaciones máximas.';
$l['setting_newpoints_donations_flood_limit'] = 'Control de Inundaciones: Donaciones Máximas';
$l['setting_newpoints_donations_flood_limit_desc'] = 'Número máximo de donaciones que un usuario puede enviar por umbral de control de inundaciones.';
$l['setting_newpoints_donations_send_private_message'] = '¿Enviar un MP al donar?';
$l['setting_newpoints_donations_send_private_message_desc'] = '¿Quieres que se envíe automáticamente un nuevo mensaje privado a un usuario que recibe una donación?';
$l['setting_newpoints_donations_stats_latest'] = 'Últimas Donaciones';
$l['setting_newpoints_donations_stats_latest_desc'] = 'Número de últimas donaciones a mostrar.';
$l['setting_newpoints_donations_menu_order'] = 'Orden del Menú';
$l['setting_newpoints_donations_menu_order_desc'] = 'Orden en el elemento del menú de NewPoints.';

$l['setting_group_newpoints_stats'] = 'Estadísticas';
$l['setting_group_newpoints_stats_desc'] = 'Estas configuraciones están relacionadas con la página de estadísticas.';
$l['setting_newpoints_stats_menu_order'] = 'Orden del Menú';
$l['setting_newpoints_stats_menu_order_desc'] = 'Orden en el elemento del menú de NewPoints.';

$l['setting_group_newpoints_main'] = 'Principal';
$l['setting_group_newpoints_main_desc'] = 'Estas configuraciones vienen con NewPoints por defecto.';
$l['setting_newpoints_main_curname'] = 'Nombre de la Moneda';
$l['setting_newpoints_main_curname_desc'] = 'Nombre de la moneda a usar en los foros.';
$l['setting_newpoints_main_curprefix'] = 'Prefijo de la Moneda';
$l['setting_newpoints_main_curprefix_desc'] = 'Prefijo de la moneda que se mostrará antes del formato de puntos.';
$l['setting_newpoints_main_cursuffix'] = 'Sufijo de la Moneda';
$l['setting_newpoints_main_cursuffix_desc'] = 'Sufijo de la moneda que se mostrará después del formato de puntos.';
$l['setting_newpoints_main_decimal'] = 'Decimales';
$l['setting_newpoints_main_decimal_desc'] = 'Número de espacios decimales a usar para la moneda.';
$l['setting_newpoints_main_stats_richestusers'] = 'Estadísticas: Usuarios Más Ricos';
$l['setting_newpoints_main_stats_richestusers_desc'] = 'Número máximo de usuarios más ricos a mostrar en la página de estadísticas.';
$l['setting_newpoints_main_group_rate_primary_only'] = 'Tasa de Grupo Solo para el Grupo Primario';
$l['setting_newpoints_main_group_rate_primary_only_desc'] = 'Si lo configuras en sí, las reglas de tasa de grupo se calcularán usando solo el grupo de usuarios primario. Si desactivas esto, todas las reglas de tasa de grupo se ponderarán y se usará siempre el valor más cercano a <code>1</code>.';
$l['setting_newpoints_main_file'] = 'Nombre del Archivo Principal';
$l['setting_newpoints_main_file_desc'] = 'Si cambias el nombre del archivo principal de NewPoints, actualiza esta configuración. Por defecto: <code>newpoints.php</code>';
$l['setting_newpoints_main_my_alerts_enabled'] = 'Habilitar Integración de MyAlerts';
$l['setting_newpoints_main_my_alerts_enabled_desc'] = 'Si habilitas esto, los usuarios podrán recibir notificaciones de MyAlerts al recibir o perder puntos. Esta configuración también se aplica a los plugins de NewPoints que soportan alertas.';
$l['setting_newpoints_main_pm_alerts_enabled'] = 'Habilitar Notificaciones de Mensajes Privados';
$l['setting_newpoints_main_pm_alerts_enabled_desc'] = 'Habilita esto para enviar notificaciones de MP cuando los usuarios reciban o pierdan puntos.';
$l['setting_newpoints_main_plugins_repositories'] = 'Repositorios de Plugins';
$l['setting_newpoints_main_plugins_repositories_desc'] = 'Inserta tus repositorios de plugins personalizados para actualizaciones. Deja como predeterminado si no estás seguro. Predeterminado <code>community.mybb.com</code>';

$l['setting_group_newpoints_logs'] = 'Registros';
$l['setting_group_newpoints_logs_desc'] = 'Estas configuraciones están relacionadas con los registros.';
$l['setting_newpoints_logs_manage_groups'] = 'Gestionar Grupos';
$l['setting_newpoints_logs_manage_groups_desc'] = 'Selecciona los grupos que pueden gestionar los registros.';
$l['setting_newpoints_logs_per_page'] = 'Registros';
$l['setting_newpoints_logs_per_page_desc'] = 'Número de registros a mostrar por página en la página de registros.';

$l['newpoints_confirmation_plugin_activation'] = '¿Estás seguro de que deseas activar este plugin?';
$l['newpoints_confirmation_plugin_deactivation'] = '¿Estás seguro de que deseas desactivar este plugin?';
$l['newpoints_confirmation_plugin_installation'] = '¿Estás seguro de que deseas instalar este plugin?';
$l['newpoints_confirmation_plugin_uninstallation'] = '¿Estás seguro de que deseas desinstalar este plugin?';

$l['newpoints_groups_tab'] = 'NewPoints';

$l['newpoints_groups_users'] = 'Configuración de Usuarios';
$l['newpoints_groups_users_rate'] = 'Configuración de Tasa';
$l['newpoints_groups_users_income'] = 'Configuración de Ingresos';

$l['newpoints_user_groups_can_get_points'] = '¿Puede recibir puntos de ingresos?';
$l['newpoints_user_groups_can_see_page'] = '¿Puede ver la página principal?';
$l['newpoints_user_groups_can_see_stats'] = '¿Puede ver la página de estadísticas?';
$l['newpoints_user_groups_can_donate'] = '¿Puede donar puntos?';

$l['newpoints_user_groups_rate_addition'] = 'Tasa de Grupo para Adiciones<br /><small class="input">La tasa de ingresos para este grupo, utilizada al agregar puntos a los usuarios (es decir, ganancias de ingresos). El valor predeterminado es <code>1</code>.</small><br />';
$l['newpoints_user_groups_rate_subtraction'] = 'Tasa de Grupo para Sustracción <code style="color: darkorange;">Esto funciona como un porcentaje. Así que "0" = el usuario no paga nada, "100" = los usuarios pagan el precio completo, "200" = el usuario paga el doble del precio, etc.</code><br /><small class="input">La tasa de ingresos para este grupo, utilizada al restar puntos de los usuarios (es decir, venta, compra, etc). El valor predeterminado es <code>100</code>.</small><br />';

$l['newpoints_user_groups_income_thread'] = 'Nuevo Tema<br /><small class="input">Cantidad de puntos recibidos por cada nuevo tema.</small><br />';
$l['newpoints_user_groups_income_thread_reply'] = 'Respuesta a Nuevo Tema<br /><small class="input">Cantidad de puntos recibidos por cada respuesta a un tema.</small><br />';
$l['newpoints_user_groups_income_thread_rate'] = 'Tasa de Nuevo Tema<br /><small class="input">Cantidad de puntos recibidos por cada nueva tasa de tema recibida.</small><br />';
$l['newpoints_user_groups_income_post'] = 'Nueva Publicación<br /><small class="input">Cantidad de puntos recibidos por cada nueva publicación.</small><br />';
$l['newpoints_user_groups_income_post_minimum_characters'] = 'Mínimo de Caracteres<br /><small class="input">Número mínimo de caracteres requeridos para recibir la cantidad de puntos por carácter para nuevos temas o publicaciones.</small><br />';
$l['newpoints_user_groups_income_post_character'] = 'Puntos por Carácter<br /><small class="input">Cantidad de puntos recibidos por cada carácter en un tema o publicación.</small><br />';
$l['newpoints_user_groups_income_page_view'] = 'Vista de Página<br /><small class="input">Cantidad de puntos recibidos por cada vista de página.</small><br />';
$l['newpoints_user_groups_income_visit'] = 'Visita<br /><small class="input">Cantidad de puntos recibidos por cada visita.</small><br />';
$l['newpoints_user_groups_income_visit_minutes'] = 'Intervalo de Visita<br /><small class="input">Tiempo en minutos que el usuario debe esperar para recibir los puntos nuevamente.</small><br />';
$l['newpoints_user_groups_income_poll'] = 'Nueva Encuesta<br /><small class="input">Cantidad de puntos recibidos por cada nueva encuesta.</small><br />';
$l['newpoints_user_groups_income_poll_vote'] = 'Voto en Nueva Encuesta<br /><small class="input">Cantidad de puntos recibidos por cada voto en la encuesta.</small><br />';
$l['newpoints_user_groups_income_user_allowance'] = 'Asignación de Usuario<br /><small class="input">Cantidad de puntos recibidos.</small><br />';
$l['newpoints_user_groups_income_user_allowance_minutes'] = 'Intervalo de Asignación de Usuario<br /><small class="input">Tiempo en minutos que el usuario debe esperar para recibir los puntos nuevamente.</small><br />';
$l['newpoints_user_groups_income_user_allowance_primary_only'] = '¿Conceder asignación solo si este es el grupo primario del usuario?';
$l['newpoints_user_groups_income_user_registration'] = 'Nueva Registro<br /><small class="input">Cantidad de puntos recibidos cuando los usuarios se registran en el foro.</small><br />';
$l['newpoints_user_groups_income_user_referral'] = 'Nueva Referencia<br /><small class="input">Cantidad de puntos recibidos por cada usuario referido al foro.</small><br />';
$l['newpoints_user_groups_income_private_message'] = 'Nuevo Mensaje Privado<br /><small class="input">Cantidad de puntos recibidos por cada mensaje privado enviado.</small><br />';

$l['newpoints_forums'] = 'Configuración de NewPoints';
$l['newpoints_forums_rate'] = 'Tasa del Foro<br /><small class="input">La tasa de ingresos para este foro. El valor predeterminado es <code>1</code>.</small><br />';
$l['newpoints_forums_view_lock_points'] = 'Puntos Mínimos para Ver<br /><small class="input">Establece una cantidad de puntos que los usuarios deben tener para poder ver este foro.</small><br />';
$l['newpoints_forums_post_lock_points'] = 'Puntos Mínimos para Publicar<br /><small class="input">Establece una cantidad de puntos que los usuarios deben tener para poder publicar en este foro.</small><br />';

$l['newpoints_forums_rates'] = 'Configuración de Tasas de NewPoints';

$l['newpoints_task_ran'] = 'Tarea de respaldo de NewPoints ejecutada';
$l['newpoints_task_main_ran'] = 'Tarea principal de NewPoints ejecutada';

$l['newpoints_users_tab'] = 'NewPoints';
$l['newpoints_users_title'] = 'Información de NewPoints';
$l['newpoints_user_newpoints'] = 'NewPoints<br /><small class="input">Actualizar los NewPoints actuales para este usuario.</small><br />';

$l['group_newpoints'] = 'NewPoints';
$l['newpoints_field_newpoints_can_get_points'] = '¿Puede obtener puntos publicando en este foro?';