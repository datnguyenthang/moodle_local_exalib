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
local_exalib_require_cap(LOCAL_EXALIB_CAP_COURSE_SETTINGS);

$action = optional_param('action', "", PARAM_ALPHAEXT);

$output = local_exalib_get_renderer();

$out = '';

if ($action == 'save_coursesettings') {
	$settings = local_exalib_course_settings::get_course();
	$settings->allow_comments = optional_param('allow_comments', 0, PARAM_BOOL);
	$settings->use_review = optional_param('use_review', 0, PARAM_BOOL);
	$settings->use_terms_of_service = optional_param('use_terms_of_service', 0, PARAM_BOOL);
	$settings->alternative_wording = optional_param('alternative_wording', 0, PARAM_BOOL);
	$settings->root_category_id = optional_param('root_category_id', 0, PARAM_INT);
	$settings->save();

	$out .= '<h3>'.local_exalib_get_string('aenderungen_gespeichert').'</h3>';
}

$mgr = new local_exalib_category_manager(true);

$categories = $mgr->getChildren(0);
$categories = array_map(function($c) {
	return $c->name;
}, $categories);

echo $output->header('tab_course_settings');
echo $out;
echo html_writer::tag('form',
	html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'save_coursesettings')).

	html_writer::checkbox('allow_comments', 1, local_exalib_course_settings::allow_comments(), local_exalib_get_string('allow_comments'),array("class"=>"course_setting_checkbox")).'<br />'.
	html_writer::checkbox('use_review', 1, local_exalib_course_settings::use_review(), local_exalib_get_string('use_review'),array("class"=>"course_setting_checkbox")).'<br />'.
	html_writer::checkbox('use_terms_of_service', 1, local_exalib_course_settings::use_terms_of_service(), local_exalib_get_string('use_terms_of_service'),array("class"=>"course_setting_checkbox")).'<br />'.
	html_writer::checkbox('alternative_wording', 1, local_exalib_course_settings::alternative_wording(), local_exalib_get_string('alternative_wording'),array("class"=>"course_setting_checkbox")).'<br />'.

	html_writer::select($categories, 'root_category_id', local_exalib_course_settings::root_category_id(), local_exalib_get_string('option_keine')).' '.local_exalib_get_string('einstiegsebene_festlegen').'<br />'.

	html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('save', 'admin')))
	, ['action' => $_SERVER['REQUEST_URI'], 'method' => 'post']);


/* END CONTENT REGION */
echo $output->footer();
