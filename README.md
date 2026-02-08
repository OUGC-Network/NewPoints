<p align="center">
    <a href="" rel="noopener">
        <img width="700" height="400" src="https://github.com/user-attachments/assets/397e2214-4ea5-4450-a462-8c3b7d812db5" alt="Project logo">
    </a>
</p>

<h3 align="center">NewPoints</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/NewPoints.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/NewPoints.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> NewPoints is the best points system for MyBB. Efficient, feature rich and easy to use.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
    - [Template Modifications](#template_modifications)
- [Settings](#settings)
    - [File Level Settings](#file_level_settings)
- [Templates](#templates)
- [Usage](#usage)
    - [Plugins](#usage_plugins)
    - [Settings](#usage_settings)
    - [Forum Rules](#usage_forum_rules)
    - [Group Rules](#usage_group_rules)
- [Plugins](#plugins)
    - [Global Scope](#plugin_global)
    - [Hooks](#plugin_hooks)
    - [Methods](#plugin_methods)
    - [Constants](#plugin_constants)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

NewPoints is a flexible and feature-packed points system that rewards your users for activities like posting and
interacting. It includes essential features like stats tracking, a built-in donation system, and customizable earning
options. With extensive permissions and settings, you have full control over how points are earned and spent. Engage
your community, drive participation, and create a more rewarding forum experience with NewPoints today!

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [MyBB-PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) >= 13

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── admin
   │ ├── modules
   │ │ ├── newpoints
   │ │ │ ├── forumrules.php
   │ │ │ ├── grouprules.php
   │ │ │ ├── log.php
   │ │ │ ├── maintenance.php
   │ │ │ ├── module_meta.php
   │ │ │ ├── plugins.php
   │ │ │ ├── settings.php
   │ │ │ ├── stats.php
   │ │ │ ├── upgrades.php
   ├── images
   │ ├── newpoints
   │ │ ├── index.html
   │ ├── languages
   │ │ ├── english
   │ │ │ ├── newpoints.lang.php
   │ │ │ ├── admin
   │ │ │ │ ├── newpoints.lang.php
   │ │ │ │ ├── newpoints_module_meta.lang.php
   ├── inc
   │ ├── plugins
   │ │ ├── newpoints
   │ │ │ ├── core
   │ │ │ │ ├── index.html
   │ │ │ │ ├── hooks.php
   │ │ │ │ ├── plugin.php
   │ │ │ ├── hooks
   │ │ │ │ ├── admin.php
   │ │ │ │ ├── forum.php
   │ │ │ │ ├── shared.php
   │ │ │ ├── languages
   │ │ │ │ ├── english
   │ │ │ │ │ ├── admin
   │ │ │ │ │ │ ├── index.html
   │ │ │ │ │ ├── index.html
   │ │ │ │ │ │ ├── newpoints_hello.lang.php
   │ │ │ │ ├── index.html
   │ │ │ ├── plugins
   │ │ │ │ ├── newpoints_hello.php
   │ │ │ ├── settings
   │ │ │ │ ├── donations.json
   │ │ │ │ ├── income.json
   │ │ │ │ ├── main.json
   │ │ │ ├── templates
   │ │ │ │ ├── donate.html
   │ │ │ │ ├── donate_form.html
   │ │ │ │ ├── home.html
   │ │ │ │ ├── home_income.html
   │ │ │ │ ├── home_income_row.html
   │ │ │ │ ├── home_income_table.html
   │ │ │ │ ├── menu.html
   │ │ │ │ ├── modal.html
   │ │ │ │ ├── no_results.html
   │ │ │ │ ├── option.html
   │ │ │ │ ├── option_selected.html
   │ │ │ │ ├── postbit.html
   │ │ │ │ ├── postbit_donate.html
   │ │ │ │ ├── profile.html
   │ │ │ │ ├── profile_donate.html
   │ │ │ │ ├── statistics.html
   │ │ │ │ ├── statistics_donation.html
   │ │ │ │ ├── statistics_donation_row.html
   │ │ │ │ ├── statistics_richest.html
   │ │ │ │ ├── statistics_richest_user.html
   │ │ │ ├── upgrades
   │ │ │ │ ├── index.html
   │ │ │ │ ├── upgrade11.php
   │ │ │ │ ├── upgrade12.php
   │ │ │ │ ├── upgrade19.php
   │ │ │ │ ├── upgrade195.php
   │ │ │ ├── index.html
   │ │ │ ├── admin.php
   │ │ │ ├── classes.php
   │ │ │ ├── core.php
   ├── tasks
   │ ├── backupnewpoints.php
   │ ├── newpoints.php
   └── newpoints.php
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package from the [MyBB Extend](https://community.mybb.com/mods.php) site or
   from the [repository releases](https://github.com/OUGC-Network/NewPoints/releases/latest).
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Configuration » Plugins_ and install this plugin by clicking _Install & Activate_.
4. Browse to _NewPoints_ to manage NewPoints modules.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.
4. Browse to _NewPoints_ to manage NewPoints modules.

### Template Modifications <a name = "template_modifications"></a>

To display NewPoints data it is required that you edit the following template for each of your themes.

#### MyBB 1.9

8. Place `{{ get('newpoints_header_menu')|raw }}` before `{% if mybb.settings.portal %}`in the `partials/header.twig`
   template to display a link to the NewPoints main page.

#### MyBB 1.8

1. Place `{$newpoints_globals['newpoints_user_balance_formatted']}` or
   `{$GLOBALS['newpoints_globals']['newpoints_user_balance_formatted']}` in any template to display the current user
   points. Where `newpoints` in `newpoints_user_balance_formatted` is the instance users column name. Note
   that `{$newpoints_user_balance_formatted}` and `{$mypoints}` has been deprecated and will be removed in
   the future.
2. Place `{$memprofile['newpoints_user_balance_formatted']}` or
   `{$GLOBALS['memprofile']['newpoints_user_balance_formatted']}` in any `member_profile*` template to display the
   profile user points. Where `newpoints` in
   `newpoints_user_balance_formatted` is the instance users column name. Note that
   `{$newpoints_profile_user_balance_formatted}` and `{$points}` has been deprecated and will be removed in the future.
3. Place `{$post['newpoints_postbit']}` in the `postbit` or `postbit_classic` templates to display the post user
   NewPoints details.
4. Place `{$post['newpoints_user_balance_formatted']}` in the `postbit` or `postbit_classic` templates to display the
   post user points. Where `newpoints` in `newpoints_user_balance_formatted` is the instance users column name. Note
   that `{$post['newpoints_balance_formatted']}` and `{$points}` has been deprecated and will be removed in the future.
5. Place `<!--NEWPOINTS_POST_USER_DETAILS-->` in the `postbit_author_user` template to display the post user NewPoints
   details inside the author template.
6. Place `<!--NEWPOINTS_POST_USER_POINTS-->` in the `postbit_author_user` template to display the post user points
   inside the author template.
7. Place `{$newpoints_profile}` after `{$warning_level}`in the `member_profile` template to display the profile user
   NewPoints details.
8. Place `{$newpoints_header_menu}` after `{$menu_calendar}`in the `header` template to display a link to the NewPoints
   main page.
9. Place `<td class="{$alt_bg}" align="center">{$user['newpoints_user_balance_formatted']}</td>` before
   `{$referral_bit}` in the `memberlist_user` template to display the user NewPoints amount formatted. Where `newpoints`
   in `newpoints_user_balance_formatted` is the instance users column name. Note that `{$user['newpoints_formatted']}`
   and `{$user['newpoints_formatted']}` has been deprecated and will be removed in the future.
10. Place
    `<td class="tcat" width="10%" align="center"><span class="smalltext"><a href="{$sorturl}&amp;sort=newpoints&amp;order=descending"><strong>NewPoints</strong></a> {$orderarrow['newpoints']}</span></td>`.
    Where `newpoints` is the instance users column name.
    after `{$referral_header}` in the `memberlist` template to display the NewPoints column header.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Main Settings

- **Group Rate For Primary Group Only** `yesNo`
    - _If you set this to yes, group rate rules will be calculated using only the primary user group. If you turn this
      off, all group rate rules wil be pondered and the closest value to 1 will always be used._

### File Level Settings <a name = "file_level_settings"></a>

Additionally, you can force your settings by updating the `SETTINGS` array constant in the `NewPoints\Core`
namespace in the `./inc/plugins/newpoints.php` file. Any setting set this way will always bypass any front-end
configuration. Use the setting key as shown below:

```PHP
define('NewPoints\Core\SETTINGS', [
    'disable_plugins' => true
]);
```

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin.

- `newpoints_donate`
    - _front end_;
- `newpoints_donate_form`
    - _front end_;
- `newpoints_inline`
    - _front end_;
- `newpoints_home`
    - _front end_;
- `newpoints_home_income_row`
    - _front end_;
- `newpoints_home_income_table`
    - _front end_;
- `newpoints_menu`
    - _front end_;
- `newpoints_modal`
    - _front end_;
- `newpoints_no_results`
    - _front end_;
- `newpoints_option`
    - _front end_;
- `newpoints_option_selected`
    - _front end_;
- `newpoints_postbit`
    - _front end_;
- `newpoints_profile`
    - _front end_;
- `newpoints_statistics`
    - _front end_;
- `newpoints_statistics_donation`
    - _front end_;
- `newpoints_statistics_donation_row`
    - _front end_;
- `newpoints_statistics_richest`
    - _front end_;
- `newpoints_statistics_richest_user`
    - _front end_;

[Go up to Table of Contents](#table_of_contents)

## 📖 Usage <a name="usage"></a>

The following is a description of the _Administrator Control Panel_ module form fields.

### Plugins <a name="usage_plugins"></a>

### Settings <a name="usage_settings"></a>

### Logs <a name="usage_log"></a>

### Forum Rules <a name="usage_forum_rules"></a>

### Group Rules <a name="usage_group_rules"></a>

[Go up to Table of Contents](#table_of_contents)

## 🧩 Plugins <a name="plugins"></a>

Provides a list of available variables, functions, and methods for plugins to use.

### Variables available at the global scope: <a name="plugin_global"></a>

- `(float) $newpoints_user_balance_formatted` `0` if current user is a guest.

### List of available hooks: <a name="plugin_hooks"></a>

#### Front end

- `newpoints_begin`
- `newpoints_start`
- `newpoints_home_start`
- `newpoints_home_end` `array &$income_settings` object is passed by reference
- `newpoints_stats_start`
- `newpoints_stats_richest_users`
- `newpoints_stats_middle`
- `newpoints_stats_last_donations`
- `newpoints_stats_end`
- `newpoints_donate_start`
- `newpoints_donate_end`
- `newpoints_do_donate_start`
- `newpoints_do_donate_end`
- `newpoints_terminate`

- `newpoints_templates_rebuild_start` `array &$hook_arguments` argument is passed with the following variables:
    - `(array) &$templates_directories`
    - `(array) &$templates_list`
- `newpoints_templates_rebuild_end` `array &$hook_arguments` argument is passed with the following variables:
    - `(array) &$templates_directories`
    - `(array) &$templates_list`
- `newpoints_settings_rebuild_start` `array &$hook_arguments` argument is passed with the following variables:
    - `(array) &$settings_directories`
    - `(array) &$settings_list`
- `newpoints_settings_rebuild_end` `array &$hook_arguments` argument is passed with the following variables:
    - `(array) &$settings_directories`
    - `(array) &$settings_list`
- `newpoints_default_menu` `array &$menu_items` argument is passed

- `newpoints_admin_load` (To be deprecated, use core `admin_load` instead.)
- `newpoints_admin_newpoints_menu` (To be deprecated, use `newpoints_admin_menu` instead.)
- `newpoints_admin_newpoints_action_handler` (To be deprecated, use `newpoints_admin_action_handler` instead.)
- `newpoints_admin_newpoints_permissions` (To be deprecated, use `newpoints_admin_permissions` instead.)
- `newpoints_admin_user_groups_edit_graph_start` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_user_groups_edit_graph_intermediate` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_user_groups_edit_graph_end` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_user_groups_edit_commit_start` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
- `newpoints_admin_formcontainer_end_start` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_user_groups_edit_graph_intermediate` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_user_groups_edit_graph_end` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`
    - `(array) &$form_fields`
- `newpoints_admin_forum_management_edit_commit_start` `array &$hook_arguments` argument is passed with the following
  variables:
    - `(array) &$fields_data`

- `newpoints_global_start` (To be deprecated, use core `global_start` instead.)
- `newpoints_xmlhttp` (To be deprecated, use core `xmlhttp` instead.)
- `newpoints_archive_start` (To be deprecated, use core `archive_start` instead.)

#### Back end

- `newpoints_admin_forumrules_begin`
- `newpoints_admin_forumrules_noaction_start`
- `newpoints_admin_forumrules_noaction_end`
- `newpoints_admin_forumrules_add_start`
- `newpoints_admin_forumrules_add_insert` `array &$insert_data` argument is passed
- `newpoints_admin_forumrules_add`
- `newpoints_admin_forumrules_edit_start`
- `newpoints_admin_forumrules_edit_update` `array &$update_query` argument is passed
- `newpoints_admin_forumrules_edit`
- `newpoints_admin_forumrules_terminate`

- `newpoints_admin_grouprules_begin`
- `newpoints_admin_grouprules_noaction_start`
- `newpoints_admin_grouprules_noaction_end`
- `newpoints_admin_grouprules_add_start`
- `newpoints_admin_grouprules_add_insert` `array &$insert_data` argument is passed
- `newpoints_admin_grouprules_add` `\FormContainer &$form_container` argument is passed
- `newpoints_admin_grouprules_edit_start`
- `newpoints_admin_grouprules_edit_update` `array &$update_data` argument is passed
- `newpoints_admin_grouprules_edit` `\FormContainer &$form_container` argument is passed
- `newpoints_admin_grouprules_terminate`

- `newpoints_admin_log_begin`
- `newpoints_admin_log_terminate`

- `newpoints_admin_maintenance_begin`
- `newpoints_admin_maintenance_start`
- `newpoints_admin_maintenance_end`
- `newpoints_admin_maintenance_edituser_start`
- `newpoints_admin_maintenance_edituser_commit`
- `newpoints_admin_maintenance_edituser_form`
- `newpoints_admin_maintenance_edituser_end`
- `newpoints_admin_maintenance_recount_start`
- `newpoints_admin_maintenance_recount_end`
- `newpoints_admin_maintenance_reset_start`
- `newpoints_admin_maintenance_reset_start`
- `newpoints_admin_maintenance_terminate`

- `newpoints_admin_menu` `array &$sub_menu_items` argument is passed
- `newpoints_admin_action_handler` `array &$action_handlers` argument is passed
- `newpoints_admin_permissions` `array &$admin_permissions` argument is passed

- `newpoints_admin_plugins_activate`
- `newpoints_admin_plugins_deactivate`
- `newpoints_admin_plugins_activate_commit`
- `newpoints_admin_plugins_deactivate_commit`
- `newpoints_admin_plugins_start`
- `newpoints_admin_plugins_end`

- `newpoints_admin_settings_change`
- `newpoints_admin_settings_change_commit`
- `newpoints_admin_settings_start`

### List of available methods at the `NewPoints\Core` namespace: <a name="plugin_methods"></a>

- `language_load(): bool { ... }`

### List of available constants: <a name="plugin_constants"></a>

The following is a list of constants are defined dynamically, `defined()`should be used to make sure they are defined.

- `\NewPoints\DECIMAL_DATA_TYPE_SIZE (string)` Default: `16,4` To be used for DECIMAL data types.
- `\NewPoints\DECIMAL_DATA_TYPE_STEP (float)` Default: `0.0001` To be used for DECIMAL data types. Example:

```PHP
const FIELDS_DATA = [
    'foo_table' => [
        'foo_column' => [
            'type' => 'DECIMAL',
            'size' => \NewPoints\DECIMAL_DATA_TYPE_SIZE,
            'default' => 0,
            'form_type' => \NewPoints\Core\FORM_TYPE_NUMERIC_FIELD,
            'form_options' => [
                'step' => \NewPoints\DECIMAL_DATA_TYPE_STEP,
            ]
        ],
    ]
];
```

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

See also the list of [contributors](https://github.com/OUGC-Network/NewPoints/contributors) who participated in
this
project.

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)