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
    - [Subscriptions](#usage_subscriptions)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

NewPoints is the best points system for MyBB. Efficient, feature rich and easy to use.

NewPoints is in its most stable stage since it has reached v2. Enjoy it!

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Template Modifications <a name = "template_modifications"></a>

To display the page link it is required that you edit the following template for each of your themes.

1. Place `{$newpoints_user_balance_formatted}` or `{$GLOBALS['$newpoints_user_balance_formatted']}` in any template to
   display the current user points.
2. Place `{$newpoints_profile_user_balance_formatted}` or `{$GLOBALS['newpoints_profile_user_balance_formatted']}` in
   any `member_profile*` template to display the profile user points.
3. Place `{$post['newpointsPostUserBalanceFormatted']}` in any `postbit*` template to display the post user points.

[Go up to Table of Contents](#table_of_contents)

### Features

* Main settings
    * NewPoints enabled.
    * Currency Name
    * Currency Prefix.
    * Currency Suffix.
    * Decimal places.
    * Statistics visible to users.
    * Donations enabled.
    * Send PM on donate.
    * Number of last donations to show.
    * Number of richest users to show.
* Income settings
    * Points per post.
    * Points per thread.
    * Points per poll.
    * Points per character (on new post and new thread).
    * Minimum characters required to multiply points per character.
    * Points on registration.
    * Points per poll vote.
    * Points per reply (points given to the author of the thread).
    * Points per PM sent.
    * Points per page view.
    * Points per visit (MyBB Session).
    * Points per referral.
* Seperated plugins system. (based on MyBB's plugins system)
* Seperated settings system. (based on MyBB's settings system)
* Upgrade system.
* Maintenance page to recount points, reset points, edit user's points and reconstruct templates.
* Easy-to-edit templates.
* Forum Rules. (income rate, minimum points to view, minimum points to create a new post/thread)
* User Group rules (income rate, amount of points to pay to this group, number of seconds between payments).
* Task which creates a backup of NewPoints default tables as well as newpoints field in the users table. (backups are
  saved to admin/backups/backupnewpoints/)
* Shows number of points in profile and post bit.

### Installation

1. Upload the contents of the "Upload" folder to the root of your MyBB installation.
2. Install and Activate NewPoints from the Admin CP and chmod the backups directory (admin/backups/backupnewpoints) to
    777.
3. If you want to activate weekly backups, just enable the "Backup NewPoints" task from your Admin CP.
4. If you want to configure the maximum donations per 15 minutes, you must edit inc/plugins/newpoints.php and search for
   MAX_DONATIONS_CONTROL.

### Upgrade

**Upgrading from 2.1.1 to 2.1.2**

Upload the contents of the "Upload" folder to the root of your MyBB installation, overwriting the old files.

**Upgrading from 1.9.5/1.9.6/1.9.7/1.9.8/2.0/2.1 to 2.1.1**

Upload the contents of the "Upload" folder to the root of your MyBB installation, overwriting the old files.
Deactivate and activate the plugin back.

### Instructions

**Plugins**

NewPoints's plugins must be uploaded to inc/plugins/newpoints and must be activated from NewPoints Plugins page.

**Settings**

NewPoints's settings (including its plugins's settings) can be found in NewPoints (top tab) -> Settings (side bar tab).
Default NewPoints settings can be found under "Main" and "Income" groups.

**Log**

From here, you can view all log entries. By default (without plugins), only donations are logged as there is nothing
else to log.
Log entries can be deleted one by one or pruned.
Note: last donations are based off logs, so by deleting logs statistics will be affected.

**Maintenance**

From the Maintenance page (NewPoints (top tab) -> Maintenance (side bar tab)) you can recount or reset everyone's
points, edit someone's points or reconstruct templates to fix duplicates. (like double points showing up in the postbit
or profile)

**Forum Rules**

You can set a custom income rate (by default, the income rate for every forum is 1) for each forum, as well as a minimum
points to view the forum (view forum includes viewing threads within the forum, editing posts, view archive to view
threads and the forum and sending thread), and set a minimum points to create a new post or thread. If the user doesn't
have the required amount of points, an error page shows up informing the user how many points the user needs in order to
execute that action.

**User Group Rules**

User group rules work the same way Froum Rules do, except that they have no minimum points to view or post rules, only
income rate.

**Statistics**

The statistics page provides you information about the last donations and richest users.
There is a statistics page which can be accessed by regular users: /newpoints.php?action=stats (this page can be
disabled from the Main settings group)

### Support

For support please visit [MyBB-Plugins.com](http://forums.mybb-plugins.com/ "MyBB-Plugins.com")

function newpoints_plugin_do_template_edits()
{
// do edits
require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
find_replace_templatesets(
'postbit_classic',
'#' . preg_quote('{$post[\'user_details\']}') . '#',
'{$post[\'user_details\']}' . '{$post[\'newpoints_postbit\']}'
);
find_replace_templatesets(
'postbit',
'#' . preg_quote('{$post[\'user_details\']}') . '#',
'{$post[\'user_details\']}' . '{$post[\'newpoints_postbit\']}'
);
find_replace_templatesets(
'member_profile',
'#' . preg_quote('{$warning_level}') . '#',
'{$warning_level}' . '{$newpoints_profile}'
);
}

function newpoints_plugin_undo_template_edits()
{
// undo edits
require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
find_replace_templatesets('postbit_classic', '#' . preg_quote('{$post[\'newpoints_postbit\']}') . '#', '', 0);
find_replace_templatesets('postbit', '#' . preg_quote('{$post[\'newpoints_postbit\']}') . '#', '', 0);
find_replace_templatesets('member_profile', '#' . preg_quote('{$newpoints_profile}') . '#', '', 0);
}