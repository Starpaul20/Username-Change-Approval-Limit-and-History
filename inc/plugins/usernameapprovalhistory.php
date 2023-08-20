<?php
/**
 * Username Change Approval and History
 * Copyright 2012 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(defined('THIS_SCRIPT'))
{
	if(THIS_SCRIPT == 'misc.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'misc_usernamehistory_history,misc_usernamehistory_history_ipaddress,misc_usernamehistory_history_delete,misc_usernamehistory_history_star,misc_usernamehistory,misc_usernamehistory_ipaddress,misc_usernamehistory_delete,misc_usernamehistory_no_history';
	}

	if(THIS_SCRIPT == 'member.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'member_profile_usernamechanges';
	}

	if(THIS_SCRIPT == 'modcp.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'modcp_nav_usernameapproval,modcp_usernameapproval,modcp_usernameapproval_actions,modcp_usernameapproval_none,modcp_usernameapproval_row';
	}

	if(THIS_SCRIPT == 'usercp.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'usercp_changename_approvalnotice,usercp_changename_maxchanges,usercp_changename_changesleft';
	}
}

// Tell MyBB when to run the hooks
$plugins->add_hook("misc_start", "usernameapprovalhistory_run");
$plugins->add_hook("member_profile_end", "usernameapprovalhistory_profile");
$plugins->add_hook("global_start", "usernameapprovalhistory_notify_cache");
$plugins->add_hook("global_intermediate", "usernameapprovalhistory_notify");
$plugins->add_hook("usercp_changename_start", "usernameapprovalhistory_change_page");
$plugins->add_hook("usercp_do_changename_start", "usernameapprovalhistory_check");
$plugins->add_hook("usercp_do_changename_end", "usernameapprovalhistory_log");
$plugins->add_hook("modcp_nav", "usernameapprovalhistory_modcp_nav");
$plugins->add_hook("modcp_start", "usernameapprovalhistory_modcp_page");
$plugins->add_hook("fetch_wol_activity_end", "usernameapprovalhistory_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "usernameapprovalhistory_online_location");
$plugins->add_hook("datahandler_user_delete_content", "usernameapprovalhistory_delete");

$plugins->add_hook("admin_home_index_output_message", "usernameapprovalhistory_admin_notice");
$plugins->add_hook("admin_user_users_edit_commit", "usernameapprovalhistory_admin_log");
$plugins->add_hook("admin_user_users_merge_commit", "usernameapprovalhistory_merge");
$plugins->add_hook("admin_formcontainer_output_row", "usernameapprovalhistory_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "usernameapprovalhistory_usergroup_permission_commit");
$plugins->add_hook("admin_user_menu", "usernameapprovalhistory_admin_menu");
$plugins->add_hook("admin_user_action_handler", "usernameapprovalhistory_admin_action_handler");
$plugins->add_hook("admin_user_permissions", "usernameapprovalhistory_admin_permissions");
$plugins->add_hook("admin_tools_get_admin_log_action", "usernameapprovalhistory_admin_adminlog");

// The information that shows up on the plugin manager
function usernameapprovalhistory_info()
{
	global $lang;
	$lang->load("user_name_approval");

	return array(
		"name"				=> $lang->usernameapprovalhistory_info_name,
		"description"		=> $lang->usernameapprovalhistory_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.5",
		"codename"			=> "usernameapprovalhistory",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function usernameapprovalhistory_install()
{
	global $db, $cache;
	usernameapprovalhistory_uninstall();
	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."usernamehistory (
				hid serial,
				uid int NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				dateline numeric(30,0) NOT NULL default '0',
				ipaddress bytea NOT NULL default '',
				approval smallint NOT NULL default '0',
				newusername varchar(120) NOT NULL default '',
				adminchange smallint NOT NULL default '0',
				admindata TEXT NOT NULL,
				PRIMARY KEY (hid)
			);");
			break;
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."usernamehistory (
				hid INTEGER PRIMARY KEY,
				uid int NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				dateline int NOT NULL default '0',
				ipaddress blob(16) NOT NULL default '',
				approval tinyint(1) NOT NULL default '0',
				newusername varchar(120) NOT NULL default '',
				adminchange tinyint(1) NOT NULL default '0',
				admindata TEXT NOT NULL
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."usernamehistory (
				hid int unsigned NOT NULL auto_increment,
				uid int unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				dateline int unsigned NOT NULL default '0',
				ipaddress varbinary(16) NOT NULL default '',
				approval tinyint(1) NOT NULL default '0',
				newusername varchar(120) NOT NULL default '',
				adminchange tinyint(1) NOT NULL default '0',
				admindata text NOT NULL,
				KEY uid (uid),
				PRIMARY KEY (hid)
			) ENGINE=MyISAM{$collation};");
			break;
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "usernameapproval", "smallint NOT NULL default '0'");
			$db->add_column("usergroups", "maxusernamesperiod", "int NOT NULL default '5'");
			$db->add_column("usergroups", "maxusernamesdaylimit", "int NOT NULL default '30'");
			$db->add_column("usergroups", "canapproveusernames", "smallint NOT NULL default '1'");
			break;
		case "sqlite":
			$db->add_column("usergroups", "usernameapproval", "tinyint(1) NOT NULL default '0'");
			$db->add_column("usergroups", "maxusernamesperiod", "int(3) NOT NULL default '5'");
			$db->add_column("usergroups", "maxusernamesdaylimit", "int(3) NOT NULL default '30'");
			$db->add_column("usergroups", "canapproveusernames", "tinyint(1) NOT NULL default '1'");
			break;
		default:
			$db->add_column("usergroups", "usernameapproval", "tinyint(1) NOT NULL default '0'");
			$db->add_column("usergroups", "maxusernamesperiod", "int(3) unsigned NOT NULL default '5'");
			$db->add_column("usergroups", "maxusernamesdaylimit", "int(3) unsigned NOT NULL default '30'");
			$db->add_column("usergroups", "canapproveusernames", "tinyint(1) NOT NULL default '1'");
			break;
	}

	$cache->update_usergroups();
	update_usernameapproval();
}

// Checks to make sure plugin is installed
function usernameapprovalhistory_is_installed()
{
	global $db;
	if($db->table_exists("usernamehistory"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function usernameapprovalhistory_uninstall()
{
	global $db, $cache;
	if($db->table_exists("usernamehistory"))
	{
		$db->drop_table("usernamehistory");
	}

	if($db->field_exists("usernameapproval", "usergroups"))
	{
		$db->drop_column("usergroups", "usernameapproval");
	}

	if($db->field_exists("maxusernamesperiod", "usergroups"))
	{
		$db->drop_column("usergroups", "maxusernamesperiod");
	}

	if($db->field_exists("maxusernamesdaylimit", "usergroups"))
	{
		$db->drop_column("usergroups", "maxusernamesdaylimit");
	}

	if($db->field_exists("canapproveusernames", "usergroups"))
	{
		$db->drop_column("usergroups", "canapproveusernames");
	}

	$cache->update_usergroups();

	$cache->delete('usernameapproval');
}

// This function runs when the plugin is activated.
function usernameapprovalhistory_activate()
{
	global $db;

	// Upgrade support (from 1.2 to 1.3)
	if(!$db->field_exists("canapproveusernames", "usergroups"))
	{
		$db->add_column("usergroups", "canapproveusernames", "tinyint(1) NOT NULL default '1'");
	}

	$query = $db->simple_select("settinggroups", "gid", "name='member'");
	$gid = $db->fetch_field($query, "gid");

	// Insert settings
	$insertarray = array(
		'name' => 'minusernametimewait',
		'title' => 'Minimum Time Between Username Changes',
		'description' => 'The minimum amount of time (in hours) after a username change a user must wait before being able to change it again. Admin changes do not count towards this limit. Enter 0 (zero) for no limit.',
		'optionscode' => 'numeric
min=0',
		'value' => 6,
		'disporder' => 26,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	rebuild_settings();

	// Insert templates
	$insert_array = array(
		'title'		=> 'misc_usernamehistory',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->username_history_for}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="thead" colspan="{$colspan}"><strong>{$lang->username_history_for}</strong></td>
		</tr>
		<tr>
			<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->old_username}</strong></span></td>
			<td class="tcat" width="40%" align="center"><span class="smalltext"><strong>{$lang->date_changed}</strong></span></td>
			{$ipaddresscol}
			{$optionscol}
		</tr>
		{$usernamehistory_bit}
	</table>
{$multipage}
{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_ipaddress',
		'template'	=> $db->escape_string('<td class="tcat" width="10%" align="center"><span class="smalltext"><strong>{$lang->ip_address}</strong></span></td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_delete',
		'template'	=> $db->escape_string('<td class="tcat" width="10%" align="center"><span class="smalltext"><strong>{$lang->options}</strong></span></td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_no_history',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" colspan="4" align="center">{$lang->no_history}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_history',
		'template'	=> $db->escape_string('<tr>
	<td class="{$alt_bg}" align="center">{$history[\'username\']}{$star}</td>
	<td class="{$alt_bg}" align="center">{$dateline}</td>
	{$ipaddressbit}
	{$deletebit}
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_history_ipaddress',
		'template'	=> $db->escape_string('<td class="{$alt_bg}" align="center">{$history[\'ipaddress\']}</td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_history_delete',
		'template'	=> $db->escape_string('<td class="{$alt_bg}" align="center"><a href="misc.php?action=usernamehistorydelete&amp;hid={$history[\'hid\']}&amp;my_post_key={$mybb->post_code}"  onclick="if(confirm(&quot;{$lang->delete_history_confirm}&quot;))window.location=this.href.replace(\'action=usernamehistorydelete\',\'action=usernamehistorydelete\');return false;">{$lang->delete}</a></td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_usernamehistory_history_star',
		'template'	=> $db->escape_string('<span title="{$admin_change}"><strong>*</strong></span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_usernamechanges',
		'template'	=> $db->escape_string('<br /><strong>{$lang->username_changes}: <a href="misc.php?action=usernamehistory&amp;uid={$memprofile[\'uid\']}">{$num_changes}</a></strong>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'global_usernameapproval',
		'template'	=> $db->escape_string('<div class="red_alert"><a href="{$mybb->settings[\'bburl\']}/modcp.php?action=usernameapproval">{$lang->unread_approval_counts}</a></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_changename_approvalnotice',
		'template'	=> $db->escape_string('<br /><span class="smalltext">{$lang->approval_notice}</span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_changename_maxchanges',
		'template'	=> $db->escape_string('<br /><span class="smalltext">{$max_changes_message}</span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_changename_changesleft',
		'template'	=> $db->escape_string('<br /><span class="smalltext">{$num_changes_left}</span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_nav_usernameapproval',
		'template'	=> $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=usernameapproval" class="modcp_nav_item" style="background:url(\'images/usernameapproval.png\') no-repeat left center;">{$lang->mcp_nav_usernameapproval}</a></td></tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_usernameapproval',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->username_approval}</title>
{$headerinclude}
</head>
<body>
	{$header}
	<form action="modcp.php" method="post">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<table width="100%" border="0" align="center">
			<tr>
				{$modcp_nav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead" colspan="5"><strong>{$lang->username_approval}</strong></td>
						</tr>
						<tr>
							<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->current_name}</strong></span></td>
							<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->new_username}</strong></span></td>
							<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->date}</strong></span></td>
							<td class="tcat" align="center" width="15%"><span class="smalltext"><strong>{$lang->ipaddress}</strong></span></td>
							<td class="tcat" align="center" width="1"><input name="allbox" title="Select All" type="checkbox" class="checkbox checkall" value="1" /></td>
						</tr>
						{$usernameapproval}
						{$multipage}
					</table>
					<br />
					<div align="center">
						<input type="hidden" name="action" value="do_usernameapproval" />
						{$usernameapproval_delete_actions}
					</div>
				</td>
			</tr>
		</table>
	</form>
	{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_usernameapproval_actions',
		'template'	=> $db->escape_string('<input type="submit" class="button" name="approve" value="{$lang->approve_changes}" />
<input type="submit" class="button" name="delete" value="{$lang->delete_changes}" />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_usernameapproval_none',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" colspan="5" align="center">{$lang->no_usernames_awaiting_approval}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_usernameapproval_row',
		'template'	=> $db->escape_string('<tr>
	<td class="{$alt_bg}" align="center">{$usernamehistory[\'username\']}</td>
	<td class="{$alt_bg}" align="center">{$usernamehistory[\'newusername\']}</td>
	<td class="{$alt_bg}" align="center">{$dateline}</td>
	<td class="{$alt_bg}" align="center">{$usernamehistory[\'ipaddress\']}</td>
	<td class="{$alt_bg}" align="center"><input type="checkbox" class="checkbox" name="check[{$usernamehistory[\'hid\']}]" value="{$usernamehistory[\'hid\']}" /></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$online_status}')."#i", '{$online_status}{$username_changes}');
	find_replace_templatesets("usercp_changename", "#".preg_quote('{$lang->new_username}</strong>')."#i", '{$lang->new_username}</strong>{$maxchanges}{$approvalnotice}{$changesleft}');
	find_replace_templatesets("header", "#".preg_quote('{$pm_notice}')."#i", '{$pm_notice}{$username_approval}');
	find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_ipsearch}')."#i", '{$nav_ipsearch}{$nav_usernameapproval}');

	change_admin_permission('user', 'name_approval');

	update_usernameapproval();
}

// This function runs when the plugin is deactivated.
function usernameapprovalhistory_deactivate()
{
	global $db;
	$db->delete_query("settings", "name IN('minusernametimewait')");
	$db->delete_query("templates", "title IN('misc_usernamehistory','misc_usernamehistory_ipaddress','misc_usernamehistory_delete','misc_usernamehistory_no_history','misc_usernamehistory_history','misc_usernamehistory_history_ipaddress','misc_usernamehistory_history_delete','misc_usernamehistory_history_star','member_profile_usernamechanges','global_usernameapproval','usercp_changename_approvalnotice','usercp_changename_maxchanges','usercp_changename_changesleft')");
	$db->delete_query("templates", "title IN('modcp_nav_usernameapproval','modcp_usernameapproval','modcp_usernameapproval_actions','modcp_usernameapproval_none','modcp_usernameapproval_row')");
	rebuild_settings();

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$username_changes}')."#i", '', 0);
	find_replace_templatesets("usercp_changename", "#".preg_quote('{$maxchanges}{$approvalnotice}{$changesleft}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$username_approval}')."#i", '', 0);
	find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_usernameapproval}')."#i", '', 0);

	change_admin_permission('user', 'name_approval', -1);
}

// Username History for user
function usernameapprovalhistory_run()
{
	global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $multipage, $alt_bg;
	$lang->load("usernameapprovalhistory");

	if($mybb->input['action'] == "usernamehistory")
	{
		if($mybb->usergroup['canviewprofiles'] == 0)
		{
			error_no_permission();
		}

		$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
		$user = get_user($uid);
		if(!$user['uid'])
		{
			error($lang->invalid_user);
		}

		$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
		$lang->username_history_for = $lang->sprintf($lang->username_history_for, $user['username']);

		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->nav_usernamehistory);

		// Figure out if we need to display multiple pages.
		$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
		if(!$perpage)
		{
			if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
			{
				$mybb->settings['threadsperpage'] = 20;
			}

			$perpage = $mybb->settings['threadsperpage'];
		}

		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS count", "uid='{$user['uid']}' AND approval='0'");
		$result = $db->fetch_field($query, "count");

		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($result, $perpage, $page, "misc.php?action=usernamehistory&uid={$user['uid']}");

		// Fetch the usernames which will be displayed on this page
		$usernamehistory_bit = '';
		$query = $db->simple_select("usernamehistory", "*", "uid='{$user['uid']}' AND approval='0'", array("order_by" => "dateline", "order_dir" => "desc", "limit_start" => $start, "limit" => $perpage));
		while($history = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$dateline = my_date('relative', $history['dateline']);
			$history['username'] = htmlspecialchars_uni($history['username']);

			// Display IP address and admin notation of username changes if user is a mod/admin
			$ipaddressbit = $deletebit = $star = '';
			if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1)
			{
				$history['ipaddress'] = my_inet_ntop($db->unescape_binary($history['ipaddress']));
				eval("\$ipaddressbit = \"".$templates->get("misc_usernamehistory_history_ipaddress")."\";");
				eval("\$deletebit = \"".$templates->get("misc_usernamehistory_history_delete")."\";");

				if($history['adminchange'] == 1)
				{
					$admindata = my_unserialize($history['admindata']);
					$admindata['username'] = htmlspecialchars_uni($admindata['username']);
					$admin_change = $lang->sprintf($lang->admin_change, $admindata['username']);
					eval("\$star = \"".$templates->get("misc_usernamehistory_history_star")."\";");
				}
			}

			eval("\$usernamehistory_bit .= \"".$templates->get("misc_usernamehistory_history")."\";");
		}

		if(!$usernamehistory_bit)
		{
			eval("\$usernamehistory_bit = \"".$templates->get("misc_usernamehistory_no_history")."\";");
		}

		// Display IP address and delete option if user is a mod/admin
		$ipaddresscol = $optionscol = '';
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1)
		{
			eval("\$ipaddresscol = \"".$templates->get("misc_usernamehistory_ipaddress")."\";");
			eval("\$optionscol = \"".$templates->get("misc_usernamehistory_delete")."\";");
			$colspan = 4;
		}
		else
		{
			$colspan = 2;
		}

		eval("\$usernamehistory = \"".$templates->get("misc_usernamehistory")."\";");
		output_page($usernamehistory);
	}

	if($mybb->input['action'] == "usernamehistorydelete")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($mybb->usergroup['issupermod'] == 0)
		{
			error_no_permission();
		}

		$query = $db->simple_select("usernamehistory", "*", "hid='".$mybb->get_input('hid', MyBB::INPUT_INT)."'");
		$history = $db->fetch_array($query);

		if(!$history)
		{
			error($lang->error_invalidhistory);
		}

		$db->delete_query("usernamehistory", "hid='{$history['hid']}'");
		update_usernameapproval();

		redirect("misc.php?action=usernamehistory&uid={$history['uid']}", $lang->redirect_historydeleted);
	}
}

// Username history on User Profile
function usernameapprovalhistory_profile()
{
	global $db, $lang, $templates, $memprofile, $username_changes;
	$lang->load("usernameapprovalhistory");

	$query = $db->simple_select("usernamehistory", "COUNT(hid) AS num_changes", "uid='".(int)$memprofile['uid']."' AND approval='0'");
	$num_changes = $db->fetch_field($query, "num_changes");

	if($num_changes > 0)
	{
		eval("\$username_changes = \"".$templates->get("member_profile_usernamechanges")."\";");
	}
}

// Cache the notify template
function usernameapprovalhistory_notify_cache()
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'global_usernameapproval';
}

// Alerts Admins on username awaiting approval
function usernameapprovalhistory_notify()
{
	global $mybb, $lang, $cache, $templates, $username_approval;
	$lang->load("usernameapprovalhistory");

	$username_approval = '';
	// This user can approve username changes
	if($mybb->usergroup['canapproveusernames'] == 1 && $mybb->usergroup['canmodcp'] == 1)
	{
		// Read the username awaiting approval cache
		$awaitingapproval = $cache->read("usernameapproval");

		// 0 or more username change approvals currently exist
		if($awaitingapproval['awaiting'] > 0)
		{
			if($awaitingapproval['awaiting'] == 1)
			{
				$lang->unread_approval_counts = $lang->unread_approval_count;
			}
			else
			{
				$lang->unread_approval_counts = $lang->sprintf($lang->unread_approval_counts, $awaitingapproval['awaiting']);
			}
			eval("\$username_approval = \"".$templates->get("global_usernameapproval")."\";");
		}
	}
}

// Username change group limit and approval notice
function usernameapprovalhistory_change_page()
{
	global $db, $mybb, $lang, $templates, $approvalnotice, $maxchanges, $changesleft;
	$lang->load("usernameapprovalhistory");

	// Check group limits
	$changesleft = '';
	if($mybb->usergroup['maxusernamesperiod'] > 0)
	{
		if($mybb->usergroup['maxusernamesdaylimit'] > 0)
		{
			$days = (int)$mybb->usergroup['maxusernamesdaylimit'];
			$time = TIME_NOW - (60 * 60 * 24 * $days);
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1' AND dateline >= '".($time)."'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes_day = $lang->sprintf($lang->error_max_changes_day, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes_day);
			}
		}
		else
		{
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes = $lang->sprintf($lang->error_max_changes, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes);
			}
		}

		$num_left = $mybb->usergroup['maxusernamesperiod'] - $change_count;
		if($num_left == 1)
		{
			$num_changes_left = $lang->num_change_left;
		}
		else
		{
			$num_changes_left = $lang->sprintf($lang->num_changes_left, $num_left);
		}

		eval("\$changesleft = \"".$templates->get("usercp_changename_changesleft")."\";");
	}

	// Check minimum wait time
	if($mybb->settings['minusernametimewait'] > 0)
	{
		$hours = (int)$mybb->settings['minusernametimewait'];
		$time = TIME_NOW - (60 * 60 * $hours);
		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS history", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1' AND dateline >= '".($time)."'");
		$history = $db->fetch_field($query, "history");

		if($history > 0)
		{
			$lang->error_minimum_wait_time = $lang->sprintf($lang->error_minimum_wait_time, $mybb->settings['minusernametimewait']);
			error($lang->error_minimum_wait_time);
		}
	}

	$approvalnotice = '';
	if($mybb->usergroup['usernameapproval'] == 1)
	{
		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS approval", "uid='".(int)$mybb->user['uid']."' AND approval='1'");
		$approval = $db->fetch_field($query, "approval");

		if($approval > 0)
		{
			error($lang->error_alreadyawaiting);
		}

		eval("\$approvalnotice = \"".$templates->get("usercp_changename_approvalnotice")."\";");
	}

	$maxchanges = '';
	if($mybb->usergroup['maxusernamesperiod'] > 0)
	{
		if(empty($mybb->usergroup['maxusernamesdaylimit']))
		{
			$max_changes_message = $lang->sprintf($lang->max_changes_message, $mybb->usergroup['maxusernamesperiod']);
		}
		elseif($mybb->usergroup['maxusernamesdaylimit'] == 1)
		{
			$max_changes_message = $lang->sprintf($lang->max_changes_message_day, $mybb->usergroup['maxusernamesperiod']);
		}
		else
		{
			$max_changes_message = $lang->sprintf($lang->max_changes_message_days, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
		}

		eval("\$maxchanges = \"".$templates->get("usercp_changename_maxchanges")."\";");
	}
}

// Username change group limit and approval
function usernameapprovalhistory_check()
{
	global $db, $mybb, $lang, $session;
	$lang->load("usernameapprovalhistory");
	$mybb->binary_fields["usernamehistory"] = array('ipaddress' => true);

	// Check group limits
	if($mybb->usergroup['maxusernamesperiod'] > 0)
	{
		if($mybb->usergroup['maxusernamesdaylimit'] > 0)
		{
			$days = (int)$mybb->usergroup['maxusernamesdaylimit'];
			$time = TIME_NOW - (60 * 60 * 24 * $days);
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1' AND dateline >= '".($time)."'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes_day = $lang->sprintf($lang->error_max_changes_day, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes_day);
			}
		}
		else
		{
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes = $lang->sprintf($lang->error_max_changes, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes);
			}
		}
	}

	// Check minimum wait time
	if($mybb->settings['minusernametimewait'] > 0)
	{
		$hours = (int)$mybb->settings['minusernametimewait'];
		$time = TIME_NOW - (60 * 60 * $hours);
		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS history", "uid='".(int)$mybb->user['uid']."' AND adminchange !='1' AND dateline >= '".($time)."'");
		$history = $db->fetch_field($query, "history");

		if($history > 0)
		{
			$lang->error_minimum_wait_time = $lang->sprintf($lang->error_minimum_wait_time, $mybb->settings['minusernametimewait']);
			error($lang->error_minimum_wait_time);
		}
	}

	$query = $db->simple_select("usernamehistory", "COUNT(hid) AS approval", "uid='".(int)$mybb->user['uid']."' AND approval='1'");
	$approval = $db->fetch_field($query, "approval");

	if($approval > 0)
	{
		error($lang->error_alreadyawaiting);
	}

	if($mybb->usergroup['usernameapproval'] == 1)
	{
		$errors = array();

		require_once MYBB_ROOT."inc/functions_user.php";

		if($mybb->usergroup['canchangename'] != 1)
		{
			error_no_permission();
		}

		if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password')) == false)
		{
			$errors[] = $lang->error_invalidpassword;
		}
		else
		{
			// Set up user handler. Running it though the handler here checks for errors so only valid usernames are submitted.
			require_once "inc/datahandlers/user.php";
			$userhandler = new UserDataHandler("update");

			$user = array(
				"uid" => $mybb->user['uid'],
				"username" => $mybb->get_input('username')
			);

			$userhandler->set_data($user);

			if(!$userhandler->validate_user())
			{
				$errors = $userhandler->get_friendly_errors();
			}
			else
			{
				$username_update = array(
					"uid" => (int)$mybb->user['uid'],
					"username" => $db->escape_string($mybb->user['username']),
					"dateline" => TIME_NOW,
					"ipaddress" => $db->escape_binary($session->packedip),
					"approval" => 1,
					"newusername" => $db->escape_string($mybb->get_input('username')),
					"adminchange" => 0,
					"admindata" => ''
				);
				$db->insert_query("usernamehistory", $username_update);
				update_usernameapproval();

				redirect("usercp.php", $lang->redirect_namechangedapproval, "", true);
			}
		}
		if(count($errors) > 0)
		{
			$errors = inline_error($errors);
			$mybb->input['action'] = "changename";
		}
	}
}

// Log old user's username (from User CP, for those who don't require approval)
function usernameapprovalhistory_log()
{
	global $db, $mybb, $session;
	$mybb->binary_fields["usernamehistory"] = array('ipaddress' => true);

	$username_update = array(
		"uid" => (int)$mybb->user['uid'],
		"username" => $db->escape_string($mybb->user['username']),
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_binary($session->packedip),
		"newusername" => $db->escape_string($mybb->get_input('username')),
		"adminchange" => 0,
		"admindata" => ''
	);
	$db->insert_query("usernamehistory", $username_update);
}

// Mod CP nav menu
function usernameapprovalhistory_modcp_nav()
{
	global $mybb, $lang, $templates, $nav_usernameapproval;
	$lang->load("usernameapprovalhistory");

	if($mybb->usergroup['canapproveusernames'] == 1)
	{
		eval("\$nav_usernameapproval = \"".$templates->get("modcp_nav_usernameapproval")."\";");
	}
}

// Mod CP approval page
function usernameapprovalhistory_modcp_page()
{
	global $db, $mybb, $lang, $templates, $theme, $cache, $headerinclude, $header, $footer, $modcp_nav, $multipage, $usernameapproval_delete_actions;
	$lang->load("usernameapprovalhistory");

	$mybb->input['action'] = $mybb->get_input('action');
	if($mybb->input['action'] == "do_usernameapproval")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($mybb->usergroup['canapproveusernames'] == 0)
		{
			error_no_permission();
		}

		$mybb->input['check'] = $mybb->get_input('check', MyBB::INPUT_ARRAY);
		if(empty($mybb->input['check']))
		{
			error($lang->no_users_selected);
		}

		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		if($mybb->get_input('approve')) // approve changes
		{
			// Fetch users
			$query = $db->simple_select("usernamehistory", "hid, uid, newusername", "hid IN (".implode(",", array_map("intval", array_keys($mybb->input['check']))).")");
			while($history = $db->fetch_array($query))
			{
				$user = array(
					"uid" => $history['uid'],
					"username" => $history['newusername']
				);

				$userhandler->set_data($user);
				$errors = '';

				if(!$userhandler->validate_user())
				{
					$errors = $userhandler->get_friendly_errors();
				}
				else
				{
					$userhandler->update_user();

					$approval = array(
						"approval" => 0
					);
					$db->update_query("usernamehistory", $approval, "hid='{$history['hid']}'");
				}
			}

			$message = $lang->redirect_changes_approved;
		}

		if($mybb->get_input('delete')) // delete changes
		{
			$db->delete_query("usernamehistory", "hid IN (".implode(",", array_map("intval", array_keys($mybb->input['check']))).")");

			$message = $lang->redirect_changes_deleted;
		}

		update_usernameapproval();
		redirect("modcp.php?action=usernameapproval", $message);
	}

	if($mybb->input['action'] == "usernameapproval")
	{
		add_breadcrumb($lang->nav_modcp, "modcp.php");
		add_breadcrumb($lang->mcp_nav_usernameapproval, "modcp.php?action=usernameapproval");

		if($mybb->usergroup['canapproveusernames'] == 0)
		{
			error_no_permission();
		}

		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		// Figure out if we need to display multiple pages.
		$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = $mybb->settings['threadsperpage'];
		}

		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS count", "approval ='1'");
		$result = $db->fetch_field($query, "count");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($result, $perpage, $page, "modcp.php?action=usernameapproval");

		$usernameapproval = '';
		$query2 = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."usernamehistory h
			WHERE h.approval='1'
			ORDER BY h.dateline DESC
			LIMIT {$start}, {$perpage}
		");
		while($usernamehistory = $db->fetch_array($query2))
		{
			$alt_bg = alt_trow();
			$usernamehistory['username'] = htmlspecialchars_uni($usernamehistory['username']);
			$usernamehistory['username'] = build_profile_link($usernamehistory['username'], $usernamehistory['uid']);
			$dateline = my_date('relative', $usernamehistory['dateline']);
			$usernamehistory['ipaddress'] = my_inet_ntop($db->unescape_binary($usernamehistory['ipaddress']));
			$usernamehistory['newusername'] = htmlspecialchars_uni($usernamehistory['newusername']);

			eval("\$usernameapproval .= \"".$templates->get("modcp_usernameapproval_row")."\";");
		}

		$usernameapproval_delete_actions = '';
		if(!empty($usernameapproval))
		{
			eval("\$usernameapproval_delete_actions = \"".$templates->get("modcp_usernameapproval_actions")."\";");
		}

		if(!$usernameapproval)
		{
			eval("\$usernameapproval = \"".$templates->get("modcp_usernameapproval_none")."\";");
		}

		eval("\$modusernameapproval = \"".$templates->get("modcp_usernameapproval")."\";");
		output_page($modusernameapproval);
	}
}

// Online activity
function usernameapprovalhistory_online_activity($user_activity)
{
	global $user, $uid_list, $parameters;
	if(my_strpos($user_activity['location'], "misc.php?action=usernamehistory") !== false)
	{
		if(is_numeric($parameters['uid']))
		{
			$uid_list[] = $parameters['uid'];
		}

		$user_activity['activity'] = "misc_usernamehistory";
		$user_activity['uid'] = $parameters['uid'];
	}

	return $user_activity;
}

function usernameapprovalhistory_online_location($plugin_array)
{
	global $lang, $parameters, $usernames;
	$lang->load("usernameapprovalhistory");

	if($plugin_array['user_activity']['activity'] == "misc_usernamehistory")
	{
		if($usernames[$parameters['uid']])
		{
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_username_history2, $plugin_array['user_activity']['uid'], $usernames[$parameters['uid']]);
		}
		else
		{
			$plugin_array['location_name'] = $lang->viewing_username_history;
		}
	}

	return $plugin_array;
}

// Delete username history if user is deleted
function usernameapprovalhistory_delete($delete)
{
	global $db;

	$db->delete_query('usernamehistory', 'uid IN('.$delete->delete_uids.')');
	update_usernameapproval();

	return $delete;
}

// Alerts Admins in the Admin CP of username awaiting approval
function usernameapprovalhistory_admin_notice()
{
	global $db, $lang, $page;
	$lang->load("user_name_approval");

	$query = $db->simple_select("usernamehistory", "COUNT(hid) AS awaitingapproval", "approval='1'");
	$awaitingapproval = my_number_format($db->fetch_field($query, "awaitingapproval"));

	if($awaitingapproval > 0)
	{
		if($awaitingapproval > 1)
		{
			$approval_count = $lang->sprintf($lang->unread_approval_counts, $awaitingapproval);
		}
		else
		{
			$approval_count = $lang->unread_approval_count;
		}

		$page->output_error("<p><a href=\"index.php?module=user-name_approval\">{$approval_count}</a></p>");
	}
}

// Log old user's username (from Admin CP)
function usernameapprovalhistory_admin_log()
{
	global $db, $mybb, $user;
	$mybb->binary_fields["usernamehistory"] = array('ipaddress' => true);

	if($user['username'] != $mybb->input['username'])
	{
		$admin_info = array(
			'uid' => (int)$mybb->user['uid'],
			'username' => $mybb->user['username']
		);
		$admindata = my_serialize($admin_info);

		$username_update = array(
			"uid" => (int)$user['uid'],
			"username" => $db->escape_string($user['username']),
			"dateline" => TIME_NOW,
			"ipaddress" => $db->escape_binary(my_inet_pton(get_ip())),
			"newusername" => $db->escape_string($mybb->get_input('username')),
			"adminchange" => 1,
			"admindata" => $db->escape_string($admindata)
		);
		$db->insert_query("usernamehistory", $username_update);
	}
}

// Merge username history if users are merged
function usernameapprovalhistory_merge()
{
	global $db, $source_user, $destination_user;

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("usernamehistory", $uid, "uid='{$source_user['uid']}'");
}

// Admin CP permission control
function usernameapprovalhistory_usergroup_permission($above)
{
	global $mybb, $lang, $form;
	$lang->load("user_name_approval");

	if($mybb->input['module'] == "user-groups" AND $mybb->input['action'] == 'edit')
	{
		if($above['title'] == $lang->account_management && $lang->account_management)
		{
			$above['content'] .= "<div class=\"group_settings_bit\">".$form->generate_check_box('usernameapproval', 1, $lang->approve_username_changes, array("checked" => $mybb->input['usernameapproval']))."</div>";
			$above['content'] .= "<div class=\"group_settings_bit\">{$lang->max_username_changes}:<br /><small>{$lang->max_username_changes_desc}</small><br />".$form->generate_numeric_field('maxusernamesperiod', $mybb->input['maxusernamesperiod'], array('id' => 'maxusernamesperiod', 'class' => 'field50', 'min' => 0))."</div>";
			$above['content'] .= "<div class=\"group_settings_bit\">{$lang->username_changes_day_limit}:<br /><small>{$lang->username_changes_day_limit_desc}</small><br />".$form->generate_numeric_field('maxusernamesdaylimit', $mybb->input['maxusernamesdaylimit'], array('id' => 'maxusernamesdaylimit', 'class' => 'field50', 'min' => 0))."</div>";
		}

		if($above['title'] == $lang->user_options && $lang->user_options)
		{
			$above['content'] .= "<div class=\"group_settings_bit\">".$form->generate_check_box("canapproveusernames", 1, $lang->can_approve_username_changes, array("checked" => $mybb->input['canapproveusernames']))."</div>";
		}
	}

	return $above;
}

function usernameapprovalhistory_usergroup_permission_commit()
{
	global $mybb, $updated_group;
	$updated_group['usernameapproval'] = $mybb->get_input('usernameapproval', MyBB::INPUT_INT);
	$updated_group['maxusernamesperiod'] = $mybb->get_input('maxusernamesperiod', MyBB::INPUT_INT);
	$updated_group['maxusernamesdaylimit'] = $mybb->get_input('maxusernamesdaylimit', MyBB::INPUT_INT);
	$updated_group['canapproveusernames'] = $mybb->get_input('canapproveusernames', MyBB::INPUT_INT);
}

// Admin CP log page
function usernameapprovalhistory_admin_menu($sub_menu)
{
	global $lang;
	$lang->load("user_name_approval");

	$sub_menu['90'] = array('id' => 'name_approval', 'title' => $lang->name_approval, 'link' => 'index.php?module=user-name_approval');

	return $sub_menu;
}

function usernameapprovalhistory_admin_action_handler($actions)
{
	$actions['name_approval'] = array('active' => 'name_approval', 'file' => 'name_approval.php');

	return $actions;
}

function usernameapprovalhistory_admin_permissions($admin_permissions)
{
	global $lang;
	$lang->load("user_name_approval");

	$admin_permissions['name_approval'] = $lang->can_manage_name_approval;

	return $admin_permissions;
}

// Admin Log display
function usernameapprovalhistory_admin_adminlog($plugin_array)
{
	global $lang;
	$lang->load("user_name_approval");

	if($plugin_array['logitem']['data'][0] == 'name_approval')
	{
		$plugin_array['lang_string'] = 'admin_log_user_name_approval';
	}

	if($plugin_array['lang_string'] == 'admin_log_user_name_approval_prune')
	{
		if($plugin_array['logitem']['data'][1])
		{
			$plugin_array['lang_string'] = 'admin_log_user_name_approval_prune_user';
		}
	}

	return $plugin_array;
}

/**
 * Update username awaiting approval cache.
 *
 */
function update_usernameapproval()
{
	global $db, $cache;
	$usernamehistory = array();
	$query = $db->simple_select("usernamehistory", "COUNT(hid) AS approvalcount", "approval='1'");
	$approvalcount = $db->fetch_field($query, 'approvalcount');

	$query = $db->simple_select("usernamehistory", "dateline", "approval='1'", array('order_by' => 'dateline', 'order_dir' => 'DESC'));
	$dateline = $db->fetch_field($query, 'dateline');

	$usernamehistory = array(
		"awaiting" => $approvalcount,
		"lastdateline" => $dateline
	);

	$cache->update("usernameapproval", $usernamehistory);
}
