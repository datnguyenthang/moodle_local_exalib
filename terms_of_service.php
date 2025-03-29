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

local_exalib_init_page();
local_exalib_require_cap(LOCAL_EXALIB_CAP_USE);

$forward = required_param('forward', PARAM_LOCALURL);
$accept = optional_param('accept', false, PARAM_BOOL);

if ($accept) {
	set_user_preference('local_exalib_terms_of_service', true);
	redirect(new moodle_url($forward));
	exit;
}

$PAGE->set_url('/local/exalib/terms_of_service.php', ['forward' => $forward]);
$PAGE->set_course($SITE);
$PAGE->set_pagelayout('login');

$output = local_exalib_get_renderer();
$output->init_js_css(); // needed for javascript buttons

echo $OUTPUT->header();

echo local_exalib_get_string('terms_of_use');

echo '<div style="padding: 40px; text-align: center;">';

echo $output->link_button(new moodle_url($PAGE->url, ['accept' => true]), local_exalib_trans('de:Einverstanden'));
echo $output->back_button(new moodle_url('/'));
echo '</div>';

echo $OUTPUT->footer();
