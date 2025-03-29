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

defined('MOODLE_INTERNAL') || die();

use \local_exalib\globals as g;

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/filelib.php');

$show = optional_param('show', '', PARAM_TEXT);

$output = local_exalib_get_renderer();

if ($show == 'categories') {
	local_exalib_require_cap(LOCAL_EXALIB_CAP_MANAGE_CATS);

	echo $output->header('tab_manage_cats');

	echo '<div id="local-exalib-category-mgmt">';
	echo $output->link_button('admin.php?courseid='.g::$COURSE->id.'&show=category_add&parent_id=0', local_exalib_get_string('addmaincat'));
	echo '<ul>';

	$mgr = new local_exalib_category_manager(true);
	$mgr->walktree(function($cat) use ($output) {

		echo '<li><div>';

		echo $cat->name;
		if (!$cat->online) {
			echo ' ('.local_exalib_get_string('offline').')';
		}
		echo ' ('.$cat->cnt_inc_subs.') ';

		echo '<span class="buttons">';
		echo $output->link_button('admin.php?courseid='.g::$COURSE->id.'&show=category_add&parent_id='.$cat->id, local_exalib_get_string('addcat'));
		echo $output->link_button('admin.php?courseid='.g::$COURSE->id.'&show=category_edit&category_id='.$cat->id, local_exalib_get_string('edit'));
		echo $output->link_button('admin.php?courseid='.g::$COURSE->id.'&show=category_delete&category_id='.$cat->id.'&sesskey='.sesskey(), local_exalib_get_string('delete'), [
			'exa-confirm' => local_exalib_get_string('deletecat', null, $cat->name),
		]);
		echo '</span>';
		echo '</div>';

		echo '<ul>';
	}, function() {
		echo '</ul>';
		echo '</li>';
	});

	echo '</ul>';
	echo '</div>';

	echo $output->footer();
	exit;
}

if ($show == 'category_delete') {
	local_exalib_require_cap(LOCAL_EXALIB_CAP_MANAGE_CATS);

	$categoryid = required_param('category_id', PARAM_INT);

	require_sesskey();
	$DB->delete_records('local_exalib_category', array(
		'id' => required_param('category_id', PARAM_INT),
	));
	redirect('admin.php?courseid='.g::$COURSE->id.'&show=categories');
	exit;
}

if (($show == 'category_add') || ($show == 'category_edit')) {
	local_exalib_require_cap(LOCAL_EXALIB_CAP_MANAGE_CATS);

	if ($show == 'category_add') {
		$category = (object)array(
			'id' => 0,
			'parent_id' => required_param('parent_id', PARAM_INT),
			'online' => 1,
		);
	} else {
		$category = $DB->get_record('local_exalib_category', array('id' => required_param('category_id', PARAM_INT)));
	}

	/**
	 * Items edit form
	 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
	 * @copyright  gtn gmbh <office@gtn-solutions.com>
	 */
	class itemeditform extends moodleform {

		/**
		 * @var $_category_select - selected category
		 */
		public $_category_select = array(0 => 'root');

		/**
		 * Definition
		 * @return nothing
		 */
		public function definition() {
			global $CFG;

			$mgr = new local_exalib_category_manager(true);
			$mgr->walktree(function($cat) {
				$this->_category_select[$cat->id] = str_repeat('&nbsp;&nbsp;&nbsp;', $cat->level).'&bullet; '.$cat->name;
			});

			$mform =& $this->_form; // Don't forget the underscore!

			$mform->addElement('text', 'name', local_exalib_get_string('name'), 'size="100"');
			$mform->setType('name', PARAM_TEXT);
			$mform->addRule('name', 'Name required', 'required', null, 'server');

			$mform->addElement('select', 'parent_id', local_exalib_get_string('parent'), $this->_category_select);

			$mform->addElement('advcheckbox', 'online', local_exalib_get_string('online'));

			$this->add_action_buttons();
		}

		/**
		 * Get categories
		 * @return empty now
		 */
		public function get_categories() {
			return;
		}
	}


	$categoryeditform = new itemeditform($_SERVER['REQUEST_URI']);

	if ($categoryeditform->is_cancelled()) {
		$fcc = 1; // For Code checker.
	} else {
		if ($fromform = $categoryeditform->get_data()) {
			// Edit/add.

			if (!empty($category->id)) {
				$fromform->id = $category->id;
				$DB->update_record('local_exalib_category', $fromform);
			} else {
				$fromform->id = $DB->insert_record('local_exalib_category', $fromform);
			}

			redirect('admin.php?courseid='.g::$COURSE->id.'&show=categories');
			exit;

		} else {
			// Display form.

			echo $output->header();

			$categoryeditform->set_data($category);
			$categoryeditform->display();

			echo $output->footer();
		}
	}

	exit;
}

if (in_array($show, ['edit', 'add', 'delete'])) {
	local_exalib_handle_item_edit('', $show);
	exit;
}
