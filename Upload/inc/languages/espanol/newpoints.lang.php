<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/languages/english/newpoints.lang.php)
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

use NewPoints\Core\IncomePermissions;

$l['newpoints'] = 'NewPoints';

$l['newpoints_header_menu'] = 'NewPoints';

$l['newpoints_home'] = 'Inicio';
$l['newpoints_menu'] = 'Menú';
$l['newpoints_donate'] = 'Donar';
$l['newpoints_donated'] = 'Has donado exitosamente {1} al usuario seleccionado.';
$l['newpoints_user'] = 'Usuario';
$l['newpoints_user_desc'] = 'Ingresa el nombre de usuario de la persona a la que deseas enviar una donación.';
$l['newpoints_amount'] = 'Cantidad';
$l['newpoints_amount_desc'] = 'Ingresa la cantidad de puntos que deseas enviar al usuario.';
$l['newpoints_reason'] = 'Razón';
$l['newpoints_reason_desc'] = '(Opcional) Ingresa una razón para la donación.';
$l['newpoints_submit'] = 'Enviar';
$l['newpoints_donate_subject'] = 'Nueva donación';
$l['newpoints_donate_message'] = 'Hola, acabo de enviarte una donación de {1}.';
$l['newpoints_donate_message_reason'] = 'Hola, acabo de enviarte una donación de {1}. Razón: [quote]{2}[/quote]';
$l['newpoints_donations_disabled'] = 'Las donaciones han sido deshabilitadas por el administrador.';
$l['newpoints_cant_donate_self'] = 'No puedes enviarte una donación a ti mismo.';
$l['newpoints_invalid_amount'] = 'Has ingresado una cantidad de puntos inválida.';
$l['newpoints_invalid_user'] = 'Has ingresado un nombre de usuario inválido.';
$l['newpoints_donate_log'] = '{1}-{2}-{3}';
$l['newpoints_stats_disabled'] = 'Las estadísticas han sido deshabilitadas por el administrador.';
$l['newpoints_statistics'] = 'Estadísticas';
$l['newpoints_richest_users'] = 'Usuarios más ricos';
$l['newpoints_last_donations'] = 'Últimas donaciones';
$l['newpoints_from'] = 'De';
$l['newpoints_to'] = 'A';
$l['newpoints_noresults'] = 'No se encontraron resultados.';
$l['newpoints_date'] = 'Fecha';
$l['newpoints_not_enough_points'] = 'No tienes suficientes puntos. Requerido: {1}';
$l['newpoints_amount_paid'] = 'Cantidad Pagada';
$l['newpoints_source'] = 'Fuente';

$l['newpoints_home_desc'] = 'NewPoints es un sistema de puntos complejo pero eficiente para MyBB.';
$l['newpoints_home_description_primary'] = 'Hay algunas opciones en el menú a la izquierda que puedes usar.';
$l['newpoints_home_description_header'] = '¿Cómo ganas {2}?';
$l['newpoints_home_description_secondary'] = '';
$l['newpoints_home_description_footer'] = 'Contacta a tu administrador si tienes alguna pregunta.';
$l['newpoints_home_user_rate_description'] = 'Tu tasa para ganar {2} es <code>{3}</code> y tu tasa para gastar {2} es <code>{4}</code>.';

$l['newpoints_action'] = 'Acción';
$l['newpoints_chars'] = 'Caracteres';
$l['newpoints_max_donations_control'] = 'Has alcanzado el máximo de {1} en los últimos 15 minutos. Por favor, espera antes de hacer una nueva.';

// Settings translation
$l['newpoints_income_source'] = 'Fuente';
$l['newpoints_income_amount'] = '{1} Recibidos';
$l[IncomePermissions::UserIncomeThread] = 'Nuevo Tema';
$l['newpoints_income_thread_desc'] = 'Cantidad de puntos recibidos por cada nuevo tema.';
$l[IncomePermissions::UserIncomeThreadReply] = 'Nueva Respuesta de Tema';
$l['newpoints_income_thread_reply_desc'] = 'Cantidad de puntos recibidos por cada respuesta a un tema.';
$l[IncomePermissions::UserIncomeThreadRate] = 'Tasa de Nuevo Tema';
$l['newpoints_income_thread_rate_desc'] = 'Cantidad de puntos recibidos por cada nueva tasa de tema recibida.';
$l[IncomePermissions::UserIncomePost] = 'Nueva Publicación';
$l['newpoints_income_post_desc'] = 'Cantidad de puntos recibidos por cada nueva publicación con al menos {1} caracteres.';
$l[IncomePermissions::UserIncomePostCharacter] = 'Caracter de Publicación';
$l['newpoints_income_post_character_desc'] = 'Cantidad de puntos recibidos por cada carácter en un tema o publicación.';
$l[IncomePermissions::UserIncomePageView] = 'Vista de Página';
$l['newpoints_income_page_view_desc'] = 'Cantidad de puntos recibidos por cada vista de página.';
$l[IncomePermissions::UserIncomeVisit] = 'Visita';
$l['newpoints_income_visit_desc'] = 'Cantidad de puntos recibidos por cada visita cada {1} minutos.';
$l[IncomePermissions::UserIncomePoll] = 'Nueva Encuesta';
$l['newpoints_income_poll_desc'] = 'Cantidad de puntos recibidos por cada nueva encuesta.';
$l[IncomePermissions::UserIncomePollVote] = 'Nuevo Voto de Encuesta';
$l['newpoints_income_poll_vote_desc'] = 'Cantidad de puntos recibidos por cada voto en una encuesta.';
$l[IncomePermissions::UserIncomeUserAllowance] = 'Asignación de Usuario';
$l['newpoints_income_user_allowance_desc'] = 'Cantidad de puntos recibidos cada {1} minutos.';
$l[IncomePermissions::UserIncomeUserRegistration] = 'Nuevo Registro';
$l['newpoints_income_user_registration_desc'] = 'Cantidad de puntos recibidos cuando los usuarios se registran en el foro.';
$l[IncomePermissions::UserIncomeUserReferral] = 'Nueva Referencia';
$l['newpoints_income_user_referral_desc'] = 'Cantidad de puntos recibidos por cada usuario referido al foro.';
$l[IncomePermissions::UserIncomePrivateMessage] = 'Nuevo Mensaje Privado';
$l['newpoints_income_private_message_desc'] = 'Cantidad de puntos recibidos por cada mensaje privado enviado.';

$l['newpoints_search_user'] = 'Buscar un usuario..';

$l['newpoints_task_ran'] = 'Tarea de respaldo de NewPoints ejecutada';
$l['newpoints_task_main_ran'] = 'Tarea principal de NewPoints ejecutada';

$l['newpoints_page_confirm_table_cancel_title'] = 'Confirmar Cancelación';
$l['newpoints_page_confirm_table_cancel_button'] = 'Cancelar Pedido';

$l['newpoints_page_confirm_table_purchase_title'] = 'Confirmar Compra';
$l['newpoints_page_confirm_table_purchase_button'] = 'Comprar';

$l['newpoints_buttons_delete'] = 'Eliminar';
$l['newpoints_buttons_manage'] = 'Gestionar';
$l['newpoints_buttons_orders'] = 'Ver Pedidos';

$l['newpoints_manage_page_breadcrumb'] = 'Gestionar';

// Logs
$l['newpoints_logs_menu_title'] = 'Registros';
$l['newpoints_logs_page_title'] = 'Registros';
$l['newpoints_logs_page_breadcrumb'] = 'Registros';
$l['newpoints_logs_page_table_title'] = 'Registros';
$l['newpoints_logs_page_table_id'] = 'ID';
$l['newpoints_logs_page_table_action'] = 'Acción';

$l['newpoints_logs_page_table_action_donation_received'] = 'Donación Recibida';
$l['newpoints_logs_page_table_action_donation_sent'] = 'Donación Enviada';
$l['newpoints_logs_page_table_action_income_thread'] = 'Nuevo Tema';
$l['newpoints_logs_page_table_action_income_thread_reply'] = 'Nueva Respuesta';
$l['newpoints_logs_page_table_action_income_post'] = 'Nueva Publicación';
$l['newpoints_logs_page_table_action_income_post_character'] = 'Actualización de Caracteres de Publicación';
$l['newpoints_logs_page_table_action_income_user_registration'] = 'Registro';
$l['newpoints_logs_page_table_action_income_user_referral'] = 'Referencia de Registro';
$l['newpoints_logs_page_table_action_income_private_message'] = 'Nuevo Mensaje Privado';

$l['newpoints_logs_page_table_log_thread'] = 'Tema: <a href="{1}/{2}">{3}</a>';
$l['newpoints_logs_page_table_log_forum'] = 'Foro: <a href="{1}/{2}">{3}</a>';
$l['newpoints_logs_page_table_log_post'] = 'Publicación: <a href="{1}/{2}">{3}</a>';
$l['newpoints_logs_page_table_log_user'] = 'Usuario: {1}';

$l['newpoints_logs_page_table_points'] = 'Puntos';#deprecated
$l['newpoints_logs_page_table_amount'] = 'Cantidad';
$l['newpoints_logs_page_table_action_user'] = 'Usuario';
$l['newpoints_logs_page_table_action_primary'] = 'Primario';
$l['newpoints_logs_page_table_action_secondary'] = 'Secundario';
$l['newpoints_logs_page_table_action_tertiary'] = 'Terciario';
$l['newpoints_logs_page_table_action_type'] = 'Tipo';
$l['newpoints_logs_page_table_action_type_income'] = 'Ingreso';
$l['newpoints_logs_page_table_action_type_charge'] = 'Cargo';
$l['newpoints_logs_page_table_action_date'] = 'Fecha';
$l['newpoints_logs_page_table_action_options'] = 'Opciones';
$l['newpoints_logs_page_table_action_options_delete'] = 'Eliminar';
$l['newpoints_logs_page_table_empty'] = 'No hay registros para mostrar.';

$l['newpoints_logs_page_filter_table_title'] = 'Filtro';
$l['newpoints_logs_page_filter_table_actions'] = 'Acciones';
$l['newpoints_logs_page_filter_table_user'] = 'Usuario';

$l['newpoints_logs_page_errors_invalid_user_name'] = 'Has ingresado un nombre de usuario inválido.';
$l['newpoints_logs_page_errors_no_logs_selected'] = 'Has seleccionado un registro inválido.';
$l['newpoints_logs_page_success_log_deleted'] = 'El registro seleccionado fue eliminado exitosamente.<br /><br />Ahora serás redirigido de vuelta a la página anterior.';

$l['newpoints_menu_category_main'] = 'Principal';
$l['newpoints_menu_category_market'] = 'Mercado';
$l['newpoints_menu_category_user'] = 'Usuario';

$l['newpoints_wol_location_home'] = 'Viendo la página de <a href="{1}/{2}">Inicio</a>';
$l['newpoints_wol_location_stats'] = 'Viendo la página de <a href="{1}/{2}">Estadísticas</a>';
$l['newpoints_wol_location_donation'] = 'Viendo la página de <a href="{1}/{2}">Donación</a>';
$l['newpoints_wol_location_logs'] = 'Viendo la página de <a href="{1}/{2}">Registros</a>';

$l['newpoints_log_pm_add_subject'] = '{3} {2} fueron añadidos a tu cuenta.';
$l['newpoints_log_pm_add_message'] = 'Hola {3}, {4} {2} fueron añadidos a tu cuenta.';
$l['newpoints_log_pm_subtract_subject'] = '{3} {2} fueron restados de tu cuenta.';
$l['newpoints_log_pm_subtract_message'] = 'Hola {3}, {4} {2} fueron restados de tu cuenta.';

$l['newpoints_alert_text_core_add_points'] = '{4} {1} fueron añadidos a tu cuenta.';
$l['newpoints_alert_text_core_subtract_points'] = '{4} {1} fueron restados de tu cuenta.';

$l['myalerts_setting_newpoints_core_add_points'] = '¿Recibir alerta al recibir puntos?';
$l['myalerts_setting_newpoints_core_subtract_points'] = '¿Recibir alerta al perder puntos?';