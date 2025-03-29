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

defined('MOODLE_INTERNAL') || die;

use local_exalib\globals as g;

class local_exalib_renderer extends plugin_renderer_base {

	var $tabs = [];

	public function header($items = null) {

		if ($items === null) {
			$items = $this->tabs;
		}

		// check for tos
		if (!get_user_preferences('local_exalib_terms_of_service') && local_exalib_course_settings::use_terms_of_service()) {
			redirect(new moodle_url('terms_of_service.php', ['forward' => g::$PAGE->url->out_as_local_url(false)]));
			exit;
		}

		$items = (array)$items;
		$strheader = local_exalib_get_string('pluginname');

		$last_item_name = '';
		$tabs = array();

		$tabs[] = new tabobject('tab_library', new moodle_url('/local/exalib/index.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_library"), '', true);

		if (local_exalib_course_settings::use_review()) {
			$tabs[] = new tabobject('tab_mine', new moodle_url('/local/exalib/mine.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_mine"), '', true);

			if (local_exalib_is_reviewer()) {
				$tabs[] = new tabobject('tab_review', new moodle_url('/local/exalib/mine.php?type=review', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_review"), '', true);
			}
		}

		if (local_exalib_get_fachsprachliches_lexikon_id()) {
			$tabs[] = new tabobject('tab_fachsprachliches_lexikon', new moodle_url('/local/exalib/fachsprachliches_lexikon.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_fachsprachliches_lexikon"), '', true);
		}

		if (local_exalib_has_cap(LOCAL_EXALIB_CAP_MANAGE_CONTENT)) {
			$tabs[] = new tabobject('tab_manage_content', new moodle_url('/local/exalib/admin.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_manage_content"), '', true);
		}
		if (local_exalib_has_cap(LOCAL_EXALIB_CAP_MANAGE_CATS)) {
			$tabs[] = new tabobject('tab_manage_cats', new moodle_url('/local/exalib/admin.php', ['courseid' => g::$COURSE->id, 'show' => 'categories']), local_exalib_get_string("tab_manage_cats"), '', true);
		}

		if (local_exalib_has_cap(LOCAL_EXALIB_CAP_MANAGE_REVIEWERS) && local_exalib_course_settings::use_review()) {
			$tabs[] = new tabobject('tab_manage_reviewers', new moodle_url('/local/exalib/reviewers.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string("tab_manage_reviewers"), '', true);
		}

		if (local_exalib_has_cap(LOCAL_EXALIB_CAP_COURSE_SETTINGS)) {
			$tabs[] = new tabobject('tab_course_settings', new moodle_url('/local/exalib/course_settings.php', ['courseid' => g::$COURSE->id]), local_exalib_get_string('tab_course_settings'), '', true);
		}

		$tabtree = new tabtree($tabs);

		g::$PAGE->navbar->add(local_exalib_get_string('heading'), new moodle_url('/local/exalib/index.php', ['courseid' => g::$COURSE->id]));

		foreach ($items as $level => $item) {
			if (!is_array($item)) {
				if (!is_string($item)) {
					trigger_error('not supported');
				}

				if ($item[0] == '=') {
					$item_name = substr($item, 1);
				} else {
					$item_name = local_exalib_get_string($item);
				}

				$item = array('name' => $item_name, 'id' => $item);
			}

			if (!empty($item['id']) && $tabobj = $tabtree->find($item['id'])) {
				// overwrite selected
				$tabobj->selected = true;
				if (empty($item['link']) && $tabobj->link) {
					$item['link'] = $tabobj->link;
				}
			}

			$last_item_name = $item['name'];
			g::$PAGE->navbar->add($item['name'], !empty($item['link']) ? $item['link'] : null);
		}

		if (!array_filter($tabtree->subtree, function($t) {
			return $t->selected;
		})
		) {
			// none selected => always select first
			reset($tabtree->subtree)->selected = true;
		}

		g::$PAGE->set_title($strheader.': '.$last_item_name);
		g::$PAGE->set_heading(local_exalib_get_string('heading'));

		$this->init_js_css();

		$content = '';
		$content .= parent::header();
		$content .= '<div id="local_exalib">';
		if (count($tabtree->subtree) > 1) {
			$content .= $this->render($tabtree);
		}

		return $content;
	}

	public function footer() {
		$content = '';
		$content .= '</div>';
		$content .= parent::footer();

		return $content;
	}

	public function set_tabs($tabs) {
		$this->tabs = $tabs;
	}

	public function init_js_css() {
		static $init = true;
		if (!$init) {
			return;
		}
		$init = false;

		// init default js / css
		g::$PAGE->requires->css('/local/exalib/css/exalib.css');
		g::$PAGE->requires->css('/local/exalib/css/skin-lion/ui.easytree.css');

		g::$PAGE->requires->jquery();
		g::$PAGE->requires->js('/local/exalib/javascript/common.js');
		g::$PAGE->requires->js('/local/exalib/javascript/exalib.js');
		g::$PAGE->requires->js('/local/exalib/javascript/jquery.easytree.js');
	}

	public function requires() {
		$this->init_js_css();

		return g::$PAGE->requires;
	}

	function back_button($url) {
		return $this->link_button(
			new moodle_url($url),
			local_exalib_get_string('back')
		);
	}

	function link_button($url, $label, $attributes = []) {
		return html_writer::empty_tag('input', $attributes + [
				'type' => 'button',
				'exa-type' => 'link',
				'href' => $url,
				'value' => $label,
			]);
	}

	function item_list($type, $items) {
		global $CFG, $DB;

		foreach ($items as $item) {

			$fs = get_file_storage();
			$files = $fs->get_area_files(context_system::instance()->id,
				'local_exalib',
				'item_file',
				$item->id,
				'itemid',
				false);

			$areafiles = $fs->get_area_files(context_system::instance()->id,
				'local_exalib',
				'preview_image',
				$item->id,
				'itemid',
				false);
			$previewimage = reset($areafiles);

			if (!local_exalib_course_settings::allow_rating()) {
				$rating = 0;
			} else {
				$rating = $DB->get_field_sql('
					SELECT SUM(rating)/COUNT(*)
					FROM {local_exalib_item_comments}
					WHERE itemid=? AND rating>0
				', [$item->id]);

				// Ensure $rating is a number before rounding
				if (!is_null($rating)) {
					$rating = round($rating);
				} else {
					$rating = 0; // or handle it appropriately
				}

			}
			/*
			$linkurl = '';
			$linktext = '';
			$linktextprefix = '';
			$targetnewwindow = false;

			if ($item->resource_id) {
				$linkurl = '/mod/resource/view.php?id='.$item->resource_id;
			} else {
				if ($item->link) {
					if (strpos($item->link, 'rtmp://') === 0) {
						$linkurl = 'detail.php?itemid='.$item->id.'&back='.g::$PAGE->url->out_as_local_url();
					} else {
						$linkurl = local_exalib_format_url($item->link);
						$linktext = trim($item->link_titel) ? $item->link_titel : $item->link;
						$targetnewwindow = true;
					}
				} else {
					if ($item->content) {
						$linkurl = 'detail.php?itemid='.$item->id.'&back='.g::$PAGE->url->out_as_local_url();
					}
				}
			}
			if (!$linkurl) {
				$linkurl = 'detail.php?itemid='.$item->id.($type !== 'public' ? '&type='.$type : '').'&back='.g::$PAGE->url->out_as_local_url();
			}
			*/

			echo '<div class="library-item">';

			$linkurl = new moodle_url('detail.php', ['courseid' => g::$COURSE->id, 'itemid' => $item->id, 'back' => g::$PAGE->url->out_as_local_url()] + ($type != 'public' ? ['type' => $type] : []));
			echo '<a class="head" href="'.$linkurl.'">'.$item->name.'</a>';

			if ($rating > 0) {
				echo '&nbsp;&nbsp;';
				for ($i = 1; $i <= 5; $i++) {
					echo ($rating >= $i) ? '&#9733;' : '&#9734;';
				}
			}

			if ($type != 'public') {
				echo '<div><span class="libary_author">'.local_exalib_trans('de:Status').':</span> ';
				if ($item->online == LOCAL_EXALIB_ITEM_STATE_NEW) {
					echo local_exalib_trans('de:Neuer Eintrag');
				} elseif ($item->online == LOCAL_EXALIB_ITEM_STATE_IN_REVIEW) {
					echo local_exalib_trans('de:in Review');
				} elseif ($item->online > 0) {
					echo local_exalib_get_string('online');
				} else {
					echo local_exalib_get_string('offline');
				}
				echo '</div>';
			}

			if ($previewimage) {
				$url = local_exalib_get_url_for_file($previewimage)."?preview=thumb";
				echo '<div><img src="'.$url.'" /></div>';
			}

			if ($item->year) {
				echo '<div><span class="libary_author">'.local_exalib_trans(['de:Jahr', 'en:Year']).':</span> '.$item->year.'</div>';
			}
			if ($item->source) {
				echo '<div><span class="libary_author">'.local_exalib_get_string('source').':</span> '.$item->source.'</div>';
			}
			if ($item->authors) {
				echo '<div class="exalib-authors"><span class="libary_author">'.local_exalib_get_string('authors').':</span> '.$item->authors.'</div>';
			}

			if ($item->time_created) {
				echo '<div><span class="libary_author">'.local_exalib_get_string('created').':</span> '.
					userdate($item->time_created);
				if ($item->created_by && $tmpuser = $DB->get_record('user', array('id' => $item->created_by))) {
					echo ' '.local_exalib_get_string('by_person', null, fullname($tmpuser));
				}
				echo '</div>';
			}
			if ($item->time_modified > $item->time_created) {
				echo '<div><span class="libary_author">'.local_exalib_trans(['en:Last Modified', 'de:Zuletzt geändert']).':</span> '.
					userdate($item->time_modified);
				if ($item->modified_by && $tmpuser = $DB->get_record('user', array('id' => $item->modified_by))) {
					echo ' '.local_exalib_get_string('by_person', null, fullname($tmpuser));
				}
				echo '</div>';
			}

			if ($item->abstract) {
				echo '<div class="libary_content">'.format_text($item->abstract).'</div>';
			}

			if ($files) {
				echo '<div>';
				echo '<span class="libary_author">'.local_exalib_get_string('files').':</span> ';
				echo count($files);
				echo '</div>';
			}

			if ($type != 'public' && local_exalib_can_edit_item($item)) {
				echo '<span class="library-item-buttons">';

				if (local_exalib_course_settings::use_review()) {
					if ($item->online == LOCAL_EXALIB_ITEM_STATE_NEW) {
						echo $this->link_button(new moodle_url(basename($_SERVER['PHP_SELF']), ['courseid' => g::$COURSE->id, 'show' => 'change_state', 'state' => LOCAL_EXALIB_ITEM_STATE_IN_REVIEW, 'id' => $item->id, 'sesskey' => sesskey()]), local_exalib_trans('de:Beim Reviewer einreichen'), [
							'exa-confirm' => local_exalib_trans('de:Soll dieser Fall beim Reviewer eingereicht werden? Eine weitere Bearbeitung ist nicht mehr möglich.'),
						]);
						echo '<br />';
					}
					if ($item->online == 0 || $item->online == LOCAL_EXALIB_ITEM_STATE_IN_REVIEW) {
						echo $this->link_button(new moodle_url(basename($_SERVER['PHP_SELF']), ['courseid' => g::$COURSE->id, 'show' => 'change_state', 'state' => LOCAL_EXALIB_ITEM_STATE_NEW, 'id' => $item->id, 'sesskey' => sesskey()]), local_exalib_trans('de:Dem Autor zur Überarbeitung freigeben'), [
							'exa-confirm' => local_exalib_trans('de:Soll dieser Fall dem Autor zur Überarbeitung freigegeben werden?'),
						]);
						echo '<br />';
					}
				}

				echo $this->link_button(new moodle_url(basename($_SERVER['PHP_SELF']), ['courseid' => g::$COURSE->id, 'show' => 'edit', 'type' => $type, 'id' => $item->id, 'back' => g::$PAGE->url->out_as_local_url(false)]), local_exalib_get_string('edit'));
				echo $this->link_button(new moodle_url(basename($_SERVER['PHP_SELF']), ['courseid' => g::$COURSE->id, 'show' => 'delete', 'type' => $type, 'id' => $item->id, 'sesskey' => sesskey()]), local_exalib_get_string('delete'), [
					'exa-confirm' => local_exalib_get_string('delete_confirmation', null, $item->name),
				]);
				echo '</span>';
			}

			echo '</div>';
		}
	}
}
