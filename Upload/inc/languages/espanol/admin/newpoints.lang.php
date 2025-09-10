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
 *    NewPoints is a complex but efficient points system for MyBB.
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
$l['newpoints_description'] = 'NewPoints es un sistema de puntos complejo pero eficiente para MyBB.';
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
$l['newpoints_settings_instance'] = 'Configuraciones de {1}';
$l['newpoints_settings'] = 'Configuraciones';
$l['newpoints_settings_description'] = 'Aquí puedes gestionar las configuraciones para la instancia {1}.';
$l['newpoints_settings_change'] = 'Cambiar';
$l['newpoints_settings_change_description'] = 'Cambiar configuraciones de {3} para la instancia {1}.';
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
$l['newpoints_recount_from_logs_success'] = 'La cantidad de {2} de usuario se ha reconstruido exitosamente a partir de los registros.';

$l['newpoints_recount'] = 'Recontar NewPoints de Usuario Desde Configuracion';
$l['newpoints_recount_desc'] = 'Cuando esto se ejecute, la cantidad de NewPoints para cada usuario se actualizará para reflejar su valor actual en vivo basado en la configuración de ingresos.';
$l['newpoints_recount_success'] = 'La cantidad de {2} de usuario se ha reconstruido exitosamente a partir de la configuracion.';
$l['newpoints_reset'] = 'Restablecer NewPoints de Usuario';
$l['newpoints_reset_success'] = 'El restablecimiento de {2} de usuario fue exitoso.';
$l['newpoints_reset_desc'] = 'Cuando esto se ejecute, la cantidad de NewPoints para cada usuario se actualizará para reflejar este valor.';
$l['newpoints_reset_amount'] = 'Cantidad por usuario';
$l['newpoints_invalid_user'] = 'Usuario inválido.';

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

$l['newpoints_instances'] = 'Instancias';
$l['newpoints_instances_description'] = 'Gestionar instancias de NewPoints.';
$l['newpoints_instances_title'] = 'Instancias de NewPoints';
$l['newpoints_instances_thead_id'] = 'ID';
$l['newpoints_instances_thead_id'] = 'ID';
$l['newpoints_instances_thead_name'] = 'Nombre';
$l['newpoints_instances_thead_column'] = 'Columna de Usuarios';
$l['newpoints_instances_thead_main_file'] = 'Archivo Principal';
$l['newpoints_instances_thead_enabled'] = 'Habilitada';
$l['newpoints_instances_thead_options_settings'] = 'Configuraciones';
$l['newpoints_instances_thead_options_rebuild_columns'] = 'Creat Columna';

$l['newpoints_instances_rebuild_columns_success'] = 'La columna de usuarios <code>{1}</code> para ka instancia {2} ha sido creada exitosamente.';

$l['newpoints_instances_add'] = 'Agregar';
$l['newpoints_instances_add_description'] = 'Agregar una nueva instancia de NewPoints.';

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
$l['setting_newpoints_stats_richest_users_limit'] = 'Estadísticas: Usuarios Más Ricos';
$l['setting_newpoints_stats_richest_users_limit_desc'] = 'Número máximo de usuarios más ricos a mostrar en la página de estadísticas.';
$l['setting_newpoints_stats_menu_order'] = 'Orden del Menú';
$l['setting_newpoints_stats_menu_order_desc'] = 'Orden en el elemento del menú de NewPoints.';

$l['setting_group_newpoints_main'] = 'Principal';
$l['setting_group_newpoints_main_desc'] = 'Estas configuraciones vienen con NewPoints por defecto.';
$l['setting_newpoints_main_group_rate_primary_only'] = 'Tasa de Grupo Solo para el Grupo Primario';
$l['setting_newpoints_main_group_rate_primary_only_desc'] = 'Si lo configuras en sí, las reglas de tasa de grupo se calcularán usando solo el grupo de usuarios primario. Si desactivas esto, todas las reglas de tasa de grupo se ponderarán y se usará siempre el valor más cercano a <code>1</code>.';
$l['setting_newpoints_plugins_repositories'] = 'Repositorios de Plugins';
$l['setting_newpoints_plugins_repositories_desc'] = 'Inserta tus repositorios de plugins personalizados para actualizaciones. Deja como predeterminado si no estás seguro. Predeterminado <code>community.mybb.com</code>';

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

$l['newpoints_permission_general'] = 'General';
$l['newpoints_permission_rates'] = 'Rates';
$l['newpoints_permission_income'] = 'Income';

$l['newpoints_permission_group_can_get_points'] = 'Can get income points?';
$l['newpoints_permission_group_can_get_points_description'] = '';
$l['newpoints_permission_group_can_see_page'] = 'Can see main page?';
$l['newpoints_permission_group_can_see_page_description'] = '';
$l['newpoints_permission_group_can_see_stats'] = 'Can see the stats page?';
$l['newpoints_permission_group_can_see_stats_description'] = '';
$l['newpoints_permission_group_can_donate'] = 'Can donate points?';
$l['newpoints_permission_group_can_donate_description'] = '';

$l['newpoints_permission_group_rate_addition'] = 'Group Rate for Additions';
$l['newpoints_permission_group_rate_addition_description'] = 'The income rate for this group, used when adding points to users (i.e: income earnings). Default is <code>1</code>.';
$l['newpoints_permission_group_rate_subtraction'] = 'Group Rate for Subtraction <code style="color: darkorange;">Lowest from all groups. Percentage.</code>';
$l['newpoints_permission_group_rate_subtraction_description'] = 'The income rate for this group, used when subtracting points from users (i.e: selling, purchasing, etc). Default is <code>100</code>.';

$l['newpoints_permission_group_income_thread'] = 'New Thread';
$l['newpoints_permission_group_income_thread_description'] = 'Amount of points received for each new thread.';
$l['newpoints_permission_group_income_thread_reply'] = 'New Thread Reply';
$l['newpoints_permission_group_income_thread_reply_description'] = 'Amount of points received for each reply to a thread.';
$l['newpoints_permission_group_income_thread_rate'] = 'New Thread Rate';
$l['newpoints_permission_group_income_thread_rate_description'] = 'Amount of points received for each new thread rate received.';
$l['newpoints_permission_group_income_post'] = 'New Post';
$l['newpoints_permission_group_income_post_description'] = 'Amount of points received for each new post.';
$l['newpoints_permission_group_income_post_minimum_characters'] = 'Minimum Characters';
$l['newpoints_permission_group_income_post_minimum_characters_description'] = 'Minimum characters required in order to receive the amount of points per character for new threads or posts.';
$l['newpoints_permission_group_income_post_character'] = 'Post Character';
$l['newpoints_permission_group_income_post_character_description'] = 'Amount of points received for each character in a thread or post.';
$l['newpoints_permission_group_income_page_view'] = 'Page View';
$l['newpoints_permission_group_income_page_view_description'] = 'Amount of points received for each page view.';
$l['newpoints_permission_group_income_visit'] = 'Visit';
$l['newpoints_permission_group_income_visit_description'] = 'Amount of points received for each visit.';
$l['newpoints_permission_group_income_visit_minutes'] = 'Visit Interval';
$l['newpoints_permission_group_income_visit_minutes_description'] = 'Time in minutes that the user must wait to receive the points again.';
$l['newpoints_permission_group_income_poll'] = 'New Poll';
$l['newpoints_permission_group_income_poll_description'] = 'Amount of points received for each new poll.';
$l['newpoints_permission_group_income_poll_vote'] = 'New Poll Vote';
$l['newpoints_permission_group_income_poll_vote_description'] = 'Amount of points received for each poll vote.';
$l['newpoints_permission_group_income_user_allowance'] = 'User Allowance';
$l['newpoints_permission_group_income_user_allowance_description'] = 'Amount of points received.';
$l['newpoints_permission_group_income_user_allowance_minutes'] = 'User Allowance Interval';
$l['newpoints_permission_group_income_user_allowance_minutes_description'] = 'Time in minutes that the user must wait to receive the points again.';
$l['newpoints_permission_group_income_user_allowance_primary_only'] = 'Grant allowance if this is the user primary group only?';
$l['newpoints_permission_group_income_user_registration'] = 'New Registration';
$l['newpoints_permission_group_income_user_registration_description'] = 'Amount of points received when users register to the forum.';
$l['newpoints_permission_group_income_user_referral'] = 'New Referral';
$l['newpoints_permission_group_income_user_referral_description'] = 'Amount of points received for each user referred to the forum.';
$l['newpoints_permission_group_income_private_message'] = 'New Private Message';
$l['newpoints_permission_group_income_private_message_description'] = 'Amount of points received for each private message sent.';

$l['newpoints_permissions_forum_can_get_points'] = 'Can get points posting in this forum?';
$l['newpoints_permissions_forum_rate_addition'] = 'Forum Rate';
$l['newpoints_permissions_forum_rate_addition_description'] = 'The income rate for this forum. Default is <code>1</code>.';
$l['newpoints_permissions_forum_view_lock_points'] = 'Minimum Points To View';
$l['newpoints_permissions_forum_view_lock_points_description'] = 'Set an amount of points users must have in order to view this forum.';
$l['newpoints_permissions_forum_post_lock_points'] = 'Minimum Points To Post';
$l['newpoints_permissions_forum_post_lock_points_description'] = 'Set an amount of points users must have in order to post in this forum.';

$l['newpoints_forums'] = 'NewPoints';
$l['newpoints_field_newpoints_rate_addition'] = 'Tasa del Foro<br /><small class="input">La tasa de ingresos para este foro. El valor predeterminado es <code>1</code>.</small><br />';
$l['newpoints_field_newpoints_view_lock_points'] = 'Puntos Mínimos para Ver<br /><small class="input">Establece una cantidad de puntos que los usuarios deben tener para poder ver este foro.</small><br />';
$l['newpoints_field_newpoints_post_lock_points'] = 'Puntos Mínimos para Publicar<br /><small class="input">Establece una cantidad de puntos que los usuarios deben tener para poder publicar en este foro.</small><br />';

$l['newpoints_users_amount'] = 'Monto de NewPoints';

$l['newpoints_forums_rates'] = 'Configuración de Tasas de NewPoints';

$l['newpoints_task_ran'] = 'Tarea de respaldo de NewPoints ejecutada';
$l['newpoints_task_main_ran'] = 'Tarea principal de NewPoints ejecutada';

$l['newpoints_users_tab'] = 'NewPoints';
$l['newpoints_users_title'] = 'Información de NewPoints';
$l['newpoints_user_deprecated'] = 'This section is deprecated and the <a href="https://community.mybb.com/mods.php?action=view&pid=1623">Quick Edit</a> plugin is recommended instead.<br />You may still update some NewPoints data for this user here.';
$l['newpoints_user_newpoints'] = 'NewPoints<br /><small class="input">Actualizar los NewPoints actuales para este usuario.</small><br />';

$l['newpoints_forums'] = 'NewPoints';
$l['newpoints_field_newpoints_can_get_points'] = '¿Puede obtener puntos publicando en este foro?';

$l = array_merge($l, [
    'newpoints_admin_instances_success_new_instance' => 'The NewPoints instance was successfully added.',
    'newpoints_admin_instances_success_updated_instance' => 'The NewPoints instance settings were successfully updated.',
    'newpoints_admin_instances_success_instance_edit_permissions_groups' => 'The instance custom group permissions were successfully edited.',
    'newpoints_admin_instances_success_instance_edit_permissions_forums' => 'The instance custom forum permissions were successfully edited.',

    'newpoints_admin_instances_error_duplicated_users_column_name' => 'The selected users column name is already in use.',
    'newpoints_admin_instances_error_duplicated_script_file' => 'The selected script file is already in use.',

    'newpoints_admin_instances_edit_tabs_main' => 'Main',
    'newpoints_admin_instances_edit_tabs_permissions' => 'Group Permissions',
    'newpoints_admin_instances_edit_tabs_forum_permissions' => 'Forum Permissions',

    'newpoints_admin_instances_edit' => 'Edit',
    'newpoints_admin_instances_edit_description' => 'Edit a new instance.',

    'newpoints_admin_instances_edit_currency_name_singular' => 'Display Name (Singular)',
    'newpoints_admin_instances_edit_currency_name_singular_description' => 'Enter the display name for this instance (singular).',
    'newpoints_admin_instances_edit_currency_name_plural' => 'Display Name (Plural)',
    'newpoints_admin_instances_edit_currency_name_plural_description' => 'Enter the display name for this instance (plural).',
    'newpoints_admin_instances_edit_users_column_name' => 'Users Column Name',
    'newpoints_admin_instances_edit_users_column_name_description' => 'Enter the name of the column in the users table that will store the NewPoints for this instance.',
    'newpoints_admin_instances_edit_enable_notifications_private_message' => 'Enable Private Message Notifications?',
    'newpoints_admin_instances_edit_enable_notifications_private_message_description' => 'If you enable this, users will receive a private message when they gain or lose points in this instance.',
    'newpoints_admin_instances_edit_enable_notifications_alert' => 'Enable MyAlerts Notifications?',
    'newpoints_admin_instances_edit_enable_notifications_alert_description' => 'If you enable this, users will receive a MyAlerts notification when they gain or lose points in this instance.',
    'newpoints_admin_instances_edit_is_enabled' => 'Enabled?',
    'newpoints_admin_instances_edit_is_enabled_description' => 'Select whether you want this instance to be enabled or disabled.',
    'newpoints_admin_instances_edit_display_order' => 'Display Order',
    'newpoints_admin_instances_edit_display_order_description' => 'Enter the display order for this instance.',
    'newpoints_admin_instances_edit_script_name' => 'Script Name',
    'newpoints_admin_instances_edit_script_name_description' => 'Script for this instance. Default: <code>newpoints.php</code>.',

    'newpoints_admin_instances_edit_button_submit' => 'Submit',
    'newpoints_admin_instances_edit_button_reset' => 'Reset',

    'newpoints_admin_instances_permissions_form_group' => 'Group',
    'newpoints_admin_instances_permissions_form_group_permissions' => 'Group Permissions',
    'newpoints_admin_instances_permissions_form_allowed_actions' => 'Overview: Allowed Actions',
    'newpoints_admin_instances_permissions_form_disallowed_actions' => 'Overview: Disallowed Actions',
    'newpoints_admin_instances_permissions_form_inherited' => 'inherited',
    'newpoints_admin_instances_permissions_form_custom' => 'custom',
    'newpoints_admin_instances_permissions_form_edit' => 'Edit Custom Permissions',
    'newpoints_admin_instances_permissions_form_clear' => 'Clear Custom Permissions',
    'newpoints_admin_instances_permissions_form_set' => 'Set Custom Permissions',
    'newpoints_admin_instances_permissions_form_save_groups' => 'Save Group Permissions',

    'newpoints_admin_instances_permissions_form_custom_permissions' => 'Custom Permissions',
    'newpoints_admin_instances_permissions_form_custom_permissions_description' => 'Here you can modify the full custom permissions for an individual group for a single instance.',

    'newpoints_admin_instances_permissions_form_custom_permissions_success' => 'The instance custom group permissions have been saved successfully.',

    'newpoints_admin_instances_permissions_form_confirm_clear' => 'Are you sure you wish to clear this custom permission?',

    'newpoints_admin_instances_permissions_form_button_submit_groups' => 'Save Group Permissions',

    'newpoints_admin_instances_permissions_clear_confirm' => 'Are you sure you wish to clear this custom permission?',
    'newpoints_admin_instances_permissions_clear_success' => 'The custom group permissions for this instance have been cleared successfully.',

    'newpoints_admin_instances_permissions_form_forum' => 'Forum',
    'newpoints_admin_instances_permissions_form_forum_permissions' => 'Forum Permissions',
    'newpoints_admin_instances_permissions_form_save_forums' => 'Save Forum Permissions',

    'newpoints_admin_instances_permissions_form_button_submit_forums' => 'Save Forum Permissions',

    'newpoints_forums_general' => 'General',
    'newpoints_forums_rates' => 'Rates',
    'newpoints_forums_income' => 'Income',
]);