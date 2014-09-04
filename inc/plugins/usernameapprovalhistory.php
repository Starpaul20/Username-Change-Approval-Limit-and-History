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
if(my_strpos($_SERVER['PHP_SELF'], 'misc.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'misc_usernamehistory_history,misc_usernamehistory,misc_usernamehistory_no_history';
}

if(my_strpos($_SERVER['PHP_SELF'], 'member.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'member_profile_usernamechanges';
}

if(my_strpos($_SERVER['PHP_SELF'], 'usercp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'usercp_changename_approvalnotice,usercp_changename_maxchanges,usercp_changename_changesleft';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("misc_start", "usernameapprovalhistory_run");
$plugins->add_hook("member_profile_end", "usernameapprovalhistory_profile");
$plugins->add_hook("global_start", "usernameapprovalhistory_notify");
$plugins->add_hook("usercp_changename_start", "usernameapprovalhistory_change_page");
$plugins->add_hook("usercp_do_changename_start", "usernameapprovalhistory_check");
$plugins->add_hook("usercp_do_changename_end", "usernameapprovalhistory_log");
$plugins->add_hook("fetch_wol_activity_end", "usernameapprovalhistory_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "usernameapprovalhistory_online_location");

$plugins->add_hook("admin_user_users_edit_commit", "usernameapprovalhistory_admin_log");
$plugins->add_hook("admin_user_users_merge_commit", "usernameapprovalhistory_merge");
$plugins->add_hook("admin_user_users_delete_commit", "usernameapprovalhistory_delete");
$plugins->add_hook("admin_formcontainer_output_row", "usernameapprovalhistory_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "usernameapprovalhistory_usergroup_permission_commit");
$plugins->add_hook("admin_user_menu", "usernameapprovalhistory_admin_menu");
$plugins->add_hook("admin_user_action_handler", "usernameapprovalhistory_admin_action_handler");
$plugins->add_hook("admin_user_permissions", "usernameapprovalhistory_admin_permissions");
$plugins->add_hook("admin_tools_cache_begin", "usernameapprovalhistory_datacache_class");
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
		"version"			=> "1.0",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function usernameapprovalhistory_install()
{
	global $db, $cache;
	usernameapprovalhistory_uninstall();
	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."usernamehistory (
				hid int(10) unsigned NOT NULL auto_increment,
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				dateline bigint(30) NOT NULL default '0',
				ipaddress varbinary(16) NOT NULL default '',
				approval int(1) NOT NULL default '0',
				newusername varchar(120) NOT NULL default '',
				adminchange int(1) NOT NULL default '0',
				admindata text NOT NULL,
				KEY uid (uid),
				PRIMARY KEY(hid)
			) ENGINE=MyISAM{$collation}");

	$db->add_column("usergroups", "usernameapproval", "int(1) NOT NULL default '0'");
	$db->add_column("usergroups", "maxusernamesperiod", "int(3) NOT NULL default '5'");
	$db->add_column("usergroups", "maxusernamesdaylimit", "int(3) NOT NULL default '30'");

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

	$db->delete_query("datacache", "title='usernameapproval'");
	$cache->update_usergroups();
}

// This function runs when the plugin is activated.
function usernameapprovalhistory_activate()
{
	global $db;
	$query = $db->simple_select("settinggroups", "gid", "name='member'");
	$gid = intval($db->fetch_field($query, "gid"));

	// Insert settings
	$insertarray = array(
		'name' => 'minusernametimewait',
		'title' => 'Minimum Time Between Username Changes',
		'description' => 'The minimum amount of time (in hours) after a username change a user must wait before being able to change it again. Admin changes do not count towards this limit. Enter 0 (zero) for no limit.',
		'optionscode' => 'text',
		'value' => 6,
		'disporder' => 37,
		'gid' => intval($gid)
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
</tr>
{$usernamehistory_bit}
</tr>
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
		'title'		=> 'misc_usernamehistory_no_history',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" colspan="3" align="center">{$lang->no_history}</td>
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
</tr>'),
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
		'template'	=> $db->escape_string('<div class="red_alert"><a href="{$mybb->settings[\'bburl\']}/{$config[\'admin_dir\']}/index.php?module=user-name_approval">{$lang->unread_approval_counts}</a></div>
<br />'),
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

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$online_status}')."#i", '{$online_status}{$username_changes}');
	find_replace_templatesets("usercp_changename", "#".preg_quote('{$lang->new_username}</strong>')."#i", '{$lang->new_username}</strong>{$maxchanges}{$approvalnotice}{$changesleft}');
	find_replace_templatesets("header", "#".preg_quote('{$pm_notice}')."#i", '{$pm_notice}{$username_approval}');

	change_admin_permission('user', 'name_approval');

	update_usernameapproval();
}

// This function runs when the plugin is deactivated.
function usernameapprovalhistory_deactivate()
{
	global $db;
	$db->delete_query("settings", "name IN('minusernametimewait')");
	$db->delete_query("templates", "title IN('misc_usernamehistory','misc_usernamehistory_no_history','misc_usernamehistory_history','member_profile_usernamechanges','global_usernameapproval','usercp_changename_approvalnotice','usercp_changename_maxchanges','usercp_changename_changesleft')");
	rebuild_settings();

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$username_changes}')."#i", '', 0);
	find_replace_templatesets("usercp_changename", "#".preg_quote('{$maxchanges}{$approvalnotice}{$changesleft}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$username_approval}')."#i", '', 0);

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

		$uid = intval($mybb->input['uid']);
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
		if(!$mybb->settings['membersperpage'])
		{
			$mybb->settings['membersperpage'] = 20;
		}

		$perpage = intval($mybb->input['perpage']);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = intval($mybb->settings['membersperpage']);
		}

		$query = $db->simple_select("usernamehistory", "COUNT(hid) AS count", "uid='{$user['uid']}' AND approval='0'");
		$result = $db->fetch_field($query, "count");

		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
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
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."usernamehistory
			WHERE uid='{$user['uid']}' AND approval='0'
			ORDER BY dateline desc
			LIMIT {$start}, {$perpage}
		");
		while($history = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$dateline = my_date('relative', $history['dateline']);

			// Display IP address and admin notation of username changes if user is a mod/admin
			if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1)
			{
				$history['ipaddress'] = my_inet_ntop($db->unescape_binary($history['ipaddress']));
				$ipaddressbit = "<td class=\"{$alt_bg}\" align=\"center\">{$history['ipaddress']}</td>";

				if($history['adminchange'] == 1)
				{
					$admindata = unserialize($history['admindata']);
					$admin_change = $lang->sprintf($lang->admin_change, $admindata['username']);
					$star = "<span title=\"{$admin_change}\"><strong>*</strong></span>";
				}
				else
				{
					$star = "";
				}
			}

			eval("\$usernamehistory_bit .= \"".$templates->get("misc_usernamehistory_history")."\";");
		}

		if(!$usernamehistory_bit)
		{
			eval("\$usernamehistory_bit = \"".$templates->get("misc_usernamehistory_no_history")."\";");
		}

		// Display IP address of scores if user is a mod/admin
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1)
		{
			$ipaddresscol = "<td class=\"tcat\" width=\"10%\" align=\"center\"><span class=\"smalltext\"><strong>{$lang->ip_address}</strong></span></td>";
			$colspan = "3";
		}
		else
		{
			$colspan = "2";
		}

		eval("\$usernamehistory = \"".$templates->get("misc_usernamehistory")."\";");
		output_page($usernamehistory);
	}
}

// Username history on User Profile
function usernameapprovalhistory_profile()
{
	global $db, $mybb, $lang, $templates, $memprofile, $username_changes;
	$lang->load("usernameapprovalhistory");

	$query = $db->simple_select("usernamehistory", "COUNT(hid) AS num_changes", "uid='".intval($memprofile['uid'])."' AND approval='0'");
	$num_changes = $db->fetch_field($query, "num_changes");

	if($num_changes > 0)
	{
		eval("\$username_changes = \"".$templates->get("member_profile_usernamechanges")."\";");
	}
}

// Alerts Admins on username awaiting approval
function usernameapprovalhistory_notify()
{
	global $mybb, $lang, $cache, $templates, $config, $username_approval;
	$lang->load("usernameapprovalhistory");

	$username_approval = '';
	// This user is an administrator and admin links aren't hidden
	if($mybb->usergroup['cancp'] == 1 && $mybb->config['hide_admin_links'] != 1)
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
	$changesleft = "";
	if($mybb->usergroup['maxusernamesperiod'] > 0)
	{
		if($mybb->usergroup['maxusernamesdaylimit'] > 0)
		{
			$days = intval($mybb->usergroup['maxusernamesdaylimit']);
			$time = TIME_NOW - (60 * 60 * 24 * $days);
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1' AND dateline >= '".($time)."'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes_day = $lang->sprintf($lang->error_max_changes_day, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes_day);
			}
		}
		else
		{
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1'");
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
		$hours = intval($mybb->settings['minusernametimewait']);
		$time = TIME_NOW - (60 * 60 * $hours);
		$query = $db->simple_select("usernamehistory", "hid", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1' AND dateline >= '".($time)."'");
		$history = $db->fetch_array($query);

		if($history['hid'])
		{
			$lang->error_minimum_wait_time = $lang->sprintf($lang->error_minimum_wait_time, $mybb->settings['minusernametimewait']);
			error($lang->error_minimum_wait_time);
		}
	}

	$approvalnotice = "";
	if($mybb->usergroup['usernameapproval'] == 1)
	{
		$query = $db->simple_select("usernamehistory", "hid", "uid='".intval($mybb->user['uid'])."' AND approval='1'");
		$history = $db->fetch_array($query);

		if($history['hid'])
		{
			error($lang->error_alreadyawaiting);
		}

		eval("\$approvalnotice = \"".$templates->get("usercp_changename_approvalnotice")."\";");
	}

	$maxchanges = "";
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
			$days = intval($mybb->usergroup['maxusernamesdaylimit']);
			$time = TIME_NOW - (60 * 60 * 24 * $days);
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1' AND dateline >= '".($time)."'");
			$change_count = $db->fetch_field($query, "change_count");
			if($change_count >= $mybb->usergroup['maxusernamesperiod'])
			{
				$lang->error_max_changes_day = $lang->sprintf($lang->error_max_changes_day, $mybb->usergroup['maxusernamesperiod'], $mybb->usergroup['maxusernamesdaylimit']);
				error($lang->error_max_changes_day);
			}
		}
		else
		{
			$query = $db->simple_select("usernamehistory", "COUNT(*) AS change_count", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1'");
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
		$hours = intval($mybb->settings['minusernametimewait']);
		$time = TIME_NOW - (60 * 60 * $hours);
		$query = $db->simple_select("usernamehistory", "hid", "uid='".intval($mybb->user['uid'])."' AND adminchange !='1' AND dateline >= '".($time)."'");
		$history = $db->fetch_array($query);

		if($history['hid'])
		{
			$lang->error_minimum_wait_time = $lang->sprintf($lang->error_minimum_wait_time, $mybb->settings['minusernametimewait']);
			error($lang->error_minimum_wait_time);
		}
	}

	$query = $db->simple_select("usernamehistory", "hid", "uid='".intval($mybb->user['uid'])."' AND approval='1'");
	$history = $db->fetch_array($query);

	if($history['hid'])
	{
		error($lang->error_alreadyawaiting);
	}

	if($mybb->usergroup['usernameapproval'] == 1)
	{
		$errors = '';
		require_once MYBB_ROOT."inc/functions_user.php";

		if($mybb->usergroup['canchangename'] != 1)
		{
			error_no_permission();
		}

		if(validate_password_from_uid($mybb->user['uid'], $mybb->input['password']) == false)
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
				"username" => $mybb->input['username']
			);

			$userhandler->set_data($user);

			if(!$userhandler->validate_user())
			{
				$errors = $userhandler->get_friendly_errors();
			}
			else
			{
				$username_update = array(
					"uid" => intval($mybb->user['uid']),
					"username" => $db->escape_string($mybb->user['username']),
					"dateline" => TIME_NOW,
					"ipaddress" => $db->escape_binary($session->packedip),
					"approval" => 1,
					"newusername" => $db->escape_string($mybb->input['username'])
				);
				$db->insert_query("usernamehistory", $username_update);
				update_usernameapproval();

				redirect("usercp.php", $lang->redirect_namechangedapproval);
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
		"uid" => intval($mybb->user['uid']),
		"username" => $db->escape_string($mybb->user['username']),
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_binary($session->packedip),
		"newusername" => $db->escape_string($mybb->input['username'])
	);
	$db->insert_query("usernamehistory", $username_update);
}

// Online activity
function usernameapprovalhistory_online_activity($user_activity)
{
	global $user, $uid_list, $parameters;
	if(my_strpos($user['location'], "misc.php?action=usernamehistory") !== false)
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

// Log old user's username (from Admin CP)
function usernameapprovalhistory_admin_log()
{
	global $db, $mybb, $user;
	$mybb->binary_fields["usernamehistory"] = array('ipaddress' => true);

	if($user['username'] != $mybb->input['username'])
	{
		$admin_info = array(
			'uid' => intval($mybb->user['uid']),
			'username' => $db->escape_string($mybb->user['username'])
		);
		$admindata = serialize($admin_info);

		$username_update = array(
			"uid" => intval($user['uid']),
			"username" => $db->escape_string($user['username']),
			"dateline" => TIME_NOW,
			"ipaddress" => $db->escape_binary(my_inet_pton(get_ip())),
			"newusername" => $db->escape_string($mybb->input['username']),
			"adminchange" => 1,
			"admindata" => $db->escape_string($admindata)
		);
		$db->insert_query("usernamehistory", $username_update);
	}
}

// Merge username history if users are merged
function usernameapprovalhistory_merge()
{
    global $db, $mybb, $source_user, $destination_user;

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("usernamehistory", $uid, "uid='{$source_user['uid']}'");
}

// Delete username history if user is deleted
function usernameapprovalhistory_delete()
{
	global $db, $mybb, $user;
	$db->delete_query("usernamehistory", "uid='{$user['uid']}'");
	update_usernameapproval();
}

// Admin CP permission control
function usernameapprovalhistory_usergroup_permission($above)
{
	global $mybb, $lang, $form;
	$lang->load("user_name_approval");

	if($above['title'] == $lang->account_management && $lang->account_management)
	{
		$above['content'] .="<div class=\"group_settings_bit\">".$form->generate_check_box('usernameapproval', 1, $lang->approve_username_changes, array("checked" => $mybb->input['usernameapproval']))."</div>";
		$above['content'] .= "<div class=\"group_settings_bit\">{$lang->max_username_changes}:<br /><small>{$lang->max_username_changes_desc}</small><br />".$form->generate_text_box('maxusernamesperiod', $mybb->input['maxusernamesperiod'], array('id' => 'maxusernamesperiod', 'class' => 'field50'))."</div>";
		$above['content'] .= "<div class=\"group_settings_bit\">{$lang->username_changes_day_limit}:<br /><small>{$lang->username_changes_day_limit_desc}</small><br />".$form->generate_text_box('maxusernamesdaylimit', $mybb->input['maxusernamesdaylimit'], array('id' => 'maxusernamesdaylimit', 'class' => 'field50'))."</div>";
	}

	return $above;
}

function usernameapprovalhistory_usergroup_permission_commit()
{
	global $mybb, $updated_group;
	$updated_group['usernameapproval'] = intval($mybb->input['usernameapproval']);
	$updated_group['maxusernamesperiod'] = intval($mybb->input['maxusernamesperiod']);
	$updated_group['maxusernamesdaylimit'] = intval($mybb->input['maxusernamesdaylimit']);
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
  	global $db, $mybb, $lang;
	$lang->load("user_name_approval");

	$admin_permissions['name_approval'] = $lang->can_manage_name_approval;

	return $admin_permissions;
}

// Rebuild username approval cache in Admin CP
function usernameapprovalhistory_datacache_class()
{
	global $cache;

	if(class_exists('MyDatacache'))
	{
		class UsernameDatacache extends MyDatacache
		{
			function update_usernameapproval()
			{
				update_usernameapproval();
			}
		}

		$cache = null;
		$cache = new UsernameDatacache;

	}
	else
	{
		class MyDatacache extends datacache
		{
			function update_usernameapproval()
			{
				update_usernameapproval();
			}
		}

		$cache = null;
		$cache = new MyDatacache;
	}
}

// Admin Log display
function usernameapprovalhistory_admin_adminlog($plugin_array)
{
  	global $lang;
	$lang->load("user_name_approval");

	if($plugin_array['logitem']['data'][0] == 'name_approval')
	{
		$plugin_array['lang_string'] = admin_log_user_name_approval;
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
	$num = $db->fetch_array($query);

	$query = $db->simple_select("usernamehistory", "dateline", "approval='1'", array('order_by' => 'dateline', 'order_dir' => 'DESC'));
	$latest = $db->fetch_array($query);

	$usernamehistory = array(
		"awaiting" => $num['approvalcount'],
		"lastdateline" => $latest['dateline']
	);

	$cache->update("usernameapproval", $usernamehistory);
}

?>