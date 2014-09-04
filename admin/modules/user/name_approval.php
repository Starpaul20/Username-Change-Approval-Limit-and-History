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

$page->add_breadcrumb_item($lang->username_approval, "index.php?module=user-name_approval");

$sub_tabs['name_approval'] = array(
	'title' => $lang->username_approval,
	'link' => "index.php?module=user-name_approval",
	'description' => $lang->username_approval_desc
);

$sub_tabs['username_logs'] = array(
	'title' => $lang->username_logs,
	'link' => "index.php?module=user-name_approval&amp;action=logs",
	'description' => $lang->username_logs_desc
);

if($mybb->input['action'] == "logs")
{
	$page->add_breadcrumb_item($lang->username_logs);
	$page->output_header($lang->username_logs);

	$page->output_nav_tabs($sub_tabs, 'username_logs');

	$perpage = $mybb->get_input('perpage', 1);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = 'WHERE 1=1';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND h.uid='".$mybb->get_input('uid', 1)."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		default:
			$sortby = "h.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(h.dateline) AS count
		FROM ".TABLE_PREFIX."usernamehistory h
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=h.uid)
		{$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = $mybb->get_input('page', 1);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}

	$table = new Table;
	$table->construct_header($lang->current_username, array('width' => '20%'));
	$table->construct_header($lang->old_username, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->changed_to, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->change_date, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '10%'));
	$table->construct_header($lang->admin_change, array("class" => "align_center", 'width' => '15%'));

	$query = $db->query("
		SELECT h.*, u.username AS current_username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."usernamehistory h
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=h.uid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$adminchange = '';
		$logitem['dateline'] = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['current_username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);

		$logitem['username'] = htmlspecialchars_uni($logitem['username']);
		$logitem['newusername'] = htmlspecialchars_uni($logitem['newusername']);

		if($logitem['adminchange'] == 1)
		{
			$data = unserialize($logitem['admindata']);
			$logitem['adminlink'] = build_profile_link($data['username'], $data['uid']);
			$adminchange = "<strong>{$lang->yes}</strong>, {$lang->changed_by} {$logitem['adminlink']}";
		}
		else
		{
			$adminchange = $lang->no;
		}

		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['username'], array("class" => "align_center"));
		$table->construct_cell($logitem['newusername'], array("class" => "align_center"));
		$table->construct_cell($logitem['dateline'], array("class" => "align_center"));
		$table->construct_cell(my_inet_ntop($db->unescape_binary($logitem['ipaddress'])), array("class" => "align_center"));
		$table->construct_cell($adminchange, array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_username_history, array("colspan" => "6"));
		$table->construct_row();
	}

	$table->output($lang->username_logs);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=user-name_approval&amp;action=logs&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT h.uid, u.username
		FROM ".TABLE_PREFIX."usernamehistory h
		LEFT JOIN ".TABLE_PREFIX."users u ON (h.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = $user['username'];
	}

	$sort_by = array(
		'dateline' => $lang->change_date,
		'username' => $lang->current_username
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=user-name_approval&amp;action=logs", "post");
	$form_container = new FormContainer($lang->filter_username_history);
	$form_container->output_row($lang->current_username.":", "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_text_box('perpage', $perpage, array('id' => 'perpage')), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_username_history);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		if(is_array($mybb->input['users']))
		{
			require_once MYBB_ROOT."inc/datahandlers/user.php";
			$userhandler = new UserDataHandler("update");

			// Fetch users
			$query = $db->simple_select("usernamehistory", "hid, uid, newusername", "hid IN (".implode(",", array_map("intval", array_keys($mybb->input['users']))).")");
			while($history = $db->fetch_array($query))
			{
				$action = $mybb->input['users'][$history['hid']];
				if($action == "approve")
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
						update_usernameapproval();
					}
				}
				else if($action == "delete")
				{
					$db->delete_query("usernamehistory", "hid='{$history['hid']}'");
					update_usernameapproval();
				}
			}

			// Log admin action
			log_admin_action('name_approval');

			flash_message($lang->success_name_approval, 'success');
			admin_redirect("index.php?module=user-name_approval");
		}
	}

	$all_options = "<ul class=\"modqueue_mass\">\n";
	$all_options .= "<li><a href=\"#\" class=\"mass_ignore\" onclick=\"$$('input.radio_ignore').each(function(e) { e.checked = true; }); return false;\">{$lang->mark_as_ignored}</a></li>\n";
	$all_options .= "<li><a href=\"#\" class=\"mass_delete\" onclick=\"$$('input.radio_delete').each(function(e) { e.checked = true; }); return false;\">{$lang->mark_as_deleted}</a></li>\n";
	$all_options .= "<li><a href=\"#\" class=\"mass_approve\" onclick=\"$$('input.radio_approve').each(function(e) { e.checked = true; }); return false;\">{$lang->mark_as_approved}</a></li>\n";
	$all_options .= "</ul>\n";

	$page->output_header($lang->username_approval);

	$stylesheet = "<link rel=\"stylesheet\" href=\"styles/default/forum.css\" type=\"text/css\" />";
	echo $stylesheet;

	$page->output_nav_tabs($sub_tabs, 'name_approval');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=user-name_approval", "post");

	$table = new Table;
	$table->construct_header($lang->user, array('width' => '20%'));
	$table->construct_header($lang->new_name, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->date, array("class" => "align_center", 'width' => '30%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '10%'));
	$table->construct_header($lang->options, array("class" => "align_center", 'width' => '20%'));

	$query = $db->simple_select("usernamehistory", "*", "approval='1'");
	while($username_history = $db->fetch_array($query))
	{
		$username_history['dateline'] = my_date('relative', $username_history['dateline']);
		$trow = alt_trow();
		$username_history['profilelink'] = build_profile_link($username_history['username'], $username_history['uid'], "_blank");

		$controls = "<div class=\"modqueue_controls\">\n";
		$controls .= $form->generate_radio_button("users[{$username_history['hid']}]", "ignore", $lang->ignore, array('class' => 'radio_ignore', 'checked' => true))." ";
		$controls .= $form->generate_radio_button("users[{$username_history['hid']}]", "delete", $lang->delete, array('class' => 'radio_delete', 'checked' => false))." ";
		$controls .= $form->generate_radio_button("users[{$username_history['hid']}]", "approve", $lang->approve, array('class' => 'radio_approve', 'checked' => false));
		$controls .= "</div>";

		$table->construct_cell($username_history['profilelink']);
		$table->construct_cell($username_history['newusername'], array("class" => "align_center"));
		$table->construct_cell($username_history['dateline'], array("class" => "align_center"));
		$table->construct_cell(my_inet_ntop($db->unescape_binary($username_history['ipaddress'])), array("class" => "align_center"));
		$table->construct_cell($controls);
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_nameapproval, array("colspan" => "5"));
		$table->construct_row();
	}

	$table->output($lang->username_approval);
	echo $all_options;

	$buttons[] = $form->generate_submit_button($lang->perform_actions);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

?>