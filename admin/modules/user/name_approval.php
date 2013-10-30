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

	$sub_tabs['name_approval'] = array(
		'title' => $lang->username_approval,
		'link' => "index.php?module=user-name_approval",
		'description' => $lang->username_approval_desc
	);

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
		$username_history['dateline'] = date("jS M Y, G:i", $username_history['dateline']);
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
		$table->construct_cell($username_history['ipaddress'], array("class" => "align_center"));
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