<?php
/**
 * Username Change Approval and History
 * Copyright 2012 Starpaul20
 */

$l['usernameapprovalhistory_info_name'] = "Username Change Approval, Limit and History";
$l['usernameapprovalhistory_info_desc'] = "Allows you to moderate username changes, limit username changes in a specific time period, and log all username changes.";

// Admin Notice
$l['unread_approval_count'] = "There is 1 username change awaiting approval.";
$l['unread_approval_counts'] = "There are {1} username changes awaiting approval.";

// Usergroup permissions
$l['max_username_changes'] = "Maximum Username Changes Allowed Per X Day";
$l['max_username_changes_desc'] = "Maximum number of times users in this group can change their username in the time period specified below. If empty, users can change their username an unlimited number of times. Admin CP changes does not count to this limit.";
$l['username_changes_day_limit'] = "Username Change Time Period";
$l['username_changes_day_limit_desc'] = "The number of days in which the number of username changes can be made. For example \"30\" would mean that the user could only change their username X amount of times in a 30 day period. If empty, no time period will be used and username change limit will become an all time limit.";
$l['approve_username_changes'] = "Require username changes to be approved?";
$l['name_approval'] = "Username Changes";
$l['can_manage_name_approval'] = "Can manage username change approval?";

$l['can_approve_username_changes'] = "Can approve username change?";

// Admin Log
$l['admin_log_user_name_approval'] = "Moderated username changes";
$l['admin_log_user_name_approval_prune'] = "Pruned {3} username change logs older than {1} days";
$l['admin_log_user_name_approval_prune_user'] = "Pruned {3} username change logs older than {1} days for user #{2}";
$l['admin_log_user_name_approval_delete'] = "Deleted username history #{1}";

// Username Approval page
$l['username_approval'] = "Username Change Approval";
$l['username_approval_desc'] = "Here you can view and approve username changes awaiting moderation.";
$l['username_logs'] = "Username Change Logs";
$l['username_logs_desc'] = "Here you can view all username change history.";
$l['prune_username_logs'] = "Prune Username Logs";
$l['prune_username_logs_desc'] = "Here you can prune username change logs matching a specified criteria.";

$l['user'] = "User";
$l['new_name'] = "New Username";
$l['date'] = "Date Requested";
$l['ipaddress'] = "IP Address";
$l['options'] = "Options";
$l['perform_actions'] = "Perform Actions";

$l['all_users'] = "All Users";
$l['username'] = "Username:";
$l['date_range'] = "Date range:";
$l['older_than'] = "Older than ";
$l['days'] = "days";

$l['success_name_approval'] = "The selected username changes have been moderated successfully.";
$l['no_nameapproval'] = "There are no username change requests awaiting approval.";
$l['success_pruned_username_logs'] = "The username change logs have been pruned successfully.";

$l['ignore'] = "Ignore";
$l['delete'] = "Delete";
$l['approve'] = "Approve";
$l['mark_as_ignored'] = "Mark all as ignored";
$l['mark_as_deleted'] = "Mark all for deletion";
$l['mark_as_approved'] = "Mark all as approved";

$l['current_username'] = "Current Username";
$l['old_username'] = "Old Username";
$l['changed_to'] = "Changed To";
$l['change_date'] = "Change Date";
$l['admin_change'] = "Administrator Change";
$l['no_username_history'] = "This is no username history to display.";
$l['changed_by'] = "changed by";
$l['yes'] = "Yes";
$l['no'] = "No";

$l['filter_username_history'] = "Filter Username History";
$l['sort_by'] = "Sort By:";
$l['in'] = "in";
$l['order'] = "order";
$l['asc'] = "Ascending";
$l['desc'] = "Descending";
$l['all_users'] = "All Users";
$l['results_per_page'] = "Results Per Page:";

$l['error_invalid_username_history'] = "You have selected an invalid username history entry.";
$l['success_username_history_deleted'] = "The username history entry has been deleted successfully.";
$l['confirm_username_history_deletion'] = "Are you sure you wish to delete this username history entry?";

?>