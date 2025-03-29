<?php
// This file is part of Exabis Library
//
// (c) 2023 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Library is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require __DIR__.'/inc.php';

local_exalib_require_cap(LOCAL_EXALIB_CAP_USE);
local_exalib_require_cap(LOCAL_EXALIB_CAP_MANAGE_REVIEWERS);

define("MAX_USERS_PER_PAGE", 5000);

$showall		= optional_param('showall', 0, PARAM_BOOL);
$searchtext	 = optional_param('searchtext', '', PARAM_TEXT); // search string
$add			= optional_param('add', 0, PARAM_BOOL);
$remove		 = optional_param('remove', 0, PARAM_BOOL);

$output = local_exalib_get_renderer();

echo $output->header('tab_manage_reviewers');

if ($frm = data_submitted()) {
	require_sesskey();

	if ($add and !empty($frm->addselect)) {
		foreach ($frm->addselect as $adduser) {
			if (!$adduser = clean_param($adduser, PARAM_INT)) {
				continue;
			}

			set_user_preference('local_exalib_is_reviewer', 1, $adduser);
		}
	} else if ($remove and !empty($frm->removeselect)) {
		foreach ($frm->removeselect as $record_id) {
			if (!$record_id = clean_param($record_id, PARAM_INT)) {
				continue;
			}

			unset_user_preference('local_exalib_is_reviewer', $record_id);
		}
	} else if ($showall) {
		$searchtext = '';
	}
}

$select  = "username <> 'guest' AND deleted = 0 AND confirmed = 1";
	
if ($searchtext !== '') {   // Search for a subset of remaining users
	//$LIKE	  = $DB->sql_ilike();
		$LIKE	  = "LIKE";
	$FULLNAME  = $DB->sql_fullname();

	$selectsql = " AND ($FULLNAME $LIKE '%$searchtext%' OR email $LIKE '%$searchtext%') ";
	$select  .= $selectsql;
} else { 
	$selectsql = ""; 
}

$assignedusers = local_exalib_get_reviewers();

$availableusers = $DB->get_records_sql('SELECT id, firstname, lastname, email
									 FROM {user}
									 WHERE '.$select.'
									 AND id NOT IN ('.join(',', [0]+array_keys($assignedusers)).')
									 ORDER BY lastname ASC, firstname ASC');

echo $OUTPUT->box_start();
$userlistType = 'reviewers';
require __DIR__.'/lib/configuration_userlist.inc.php';
echo $OUTPUT->box_end();
	
echo $output->footer();
