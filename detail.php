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

use local_exalib\globals as g;

local_exalib_init_page();

$itemid = required_param('itemid', PARAM_INT);
if (!$item = $DB->get_record('local_exalib_item', array('id' => $itemid))) {
	print_error(local_exalib_get_string('itemnotfound'));
}

$type = optional_param('type', '', PARAM_TEXT);
$back = optional_param('back', '', PARAM_LOCALURL);
if ($back) {
	$back = (new moodle_url($back))->out(false);
} else {
	$back = 'javascript:history.back();';
}

local_exalib_require_view_item($item);

$output = local_exalib_get_renderer();

require_once $CFG->libdir.'/formslib.php';

class local_exalib_comment_form extends moodleform {

	function definition() {
		$mform = &$this->_form;

		$this->_form->_attributes['action'] = $_SERVER['REQUEST_URI'];

		$mform->addElement('hidden', 'action');
		$mform->setType('action', PARAM_TEXT);
		$mform->setDefault('action', 'comment_add');

		// $mform->addElement('header', 'comment', get_string("addcomment", "local_exaport"));

		$mform->addElement('editor', 'text', local_exalib_trans(["de:Kommentar", 'en:Comment']), null, array('rows' => 10));
		$mform->setType('text', PARAM_TEXT);
		//$mform->addRule('text', local_exalib_get_string('requiredelement', 'form'), 'required');

		if (local_exalib_course_settings::allow_rating()) {
			$radioarray = array();
			$radioarray[] = $mform->createElement('radio', 'rating', '', local_exalib_trans(['de:keine', 'en:none']), 0);
			$radioarray[] = $mform->createElement('radio', 'rating', '', 1, 1);
			$radioarray[] = $mform->createElement('radio', 'rating', '', 2, 2);
			$radioarray[] = $mform->createElement('radio', 'rating', '', 3, 3);
			$radioarray[] = $mform->createElement('radio', 'rating', '', 4, 4);
			$radioarray[] = $mform->createElement('radio', 'rating', '', 5, 5);
			$mform->addGroup($radioarray, 'ratingarr', local_exalib_trans(["de:Bewertung", 'en:Rating']), array(' '), false);
		}

		if (local_exalib_course_settings::use_review() && in_array(g::$USER->id, [$this->_customdata['item']->created_by, $this->_customdata['item']->reviewer_id])) {
			$mform->addElement('advcheckbox', 'isprivate', local_exalib_trans(['de:Privater Kommentar (nur für Ersteller und Reviewer sichtbar)', 'en:Private Comment (only visible for the creator and reviewer)']));
		}

		$this->add_action_buttons(false, local_exalib_get_string('add'));

	}

}

$commentsform = new local_exalib_comment_form(null, ['item' => $item]);

if (optional_param('action', '', PARAM_TEXT) == 'comment_add') {
	require_sesskey();

	if ($formdata = $commentsform->get_data()) {
		$post = new stdClass;
		$post->itemid = $item->id;
		$post->userid = $USER->id;
		$post->time_created = $post->time_modified = time();
		$post->text = $formdata->text['text'];
		if (isset($formdata->rating)) {
			$post->rating = $formdata->rating;

			// delete older ratings for same items
			$DB->execute("
				UPDATE {local_exalib_item_comments}
				SET rating=0
				WHERE itemid=? AND userid=?
			", [$item->id, $USER->id]);
		}

		if (isset($formdata->isprivate)) {
			$post->isprivate = $formdata->isprivate;
		}

		$id = $DB->insert_record('local_exalib_item_comments', $post);

		redirect($_SERVER['REQUEST_URI']);
	}
}
if (optional_param('action', '', PARAM_TEXT) == 'comment_delete') {
	require_sesskey();
	$commentid = required_param('commentid', PARAM_INT);

	$conditions = array("id" => $commentid, "userid" => $USER->id, "itemid" => $itemid);
	$DB->delete_records("local_exalib_item_comments", $conditions);

	redirect($back);
}


$fs = get_file_storage();
$files = $fs->get_area_files(context_system::instance()->id,
	'local_exalib',
	'item_file',
	$item->id,
	'itemid',
	'',
	false);

if ($type == 'mine') {
	$output->set_tabs('tab_mine');
} elseif ($type == 'admin') {
	$output->set_tabs('tab_manage_content');
}

echo $output->header();

/* <script type="text/javascript">jwplayer.key="YOUR_JWPLAYER_LICENSE_KEY";</script> */

/*
?>
<script type="text/javascript" src="jwplayer/jwplayer.js"></script>
<style>


h1.libary_head {
    -moz-border-bottom-colors: none !important;
    -moz-border-left-colors: none !important;
    -moz-border-right-colors: none !important;
    -moz-border-top-colors: none !important;
    background: none repeat scroll 0 0 rgba(0, 0, 0, 0) !important;

    border-color: #C8C8C8;
    border-image: none !important;
    border-style: none;
    border-width: 0;

    color: #003876;

    border-bottom: 1px solid #C8C8C8 !important;
    padding-bottom: 20px !important;

    font-size: 1.9em;
    font-weight: normal !important;
    margin-bottom: 35px !important;
    margin-top: 20px !important;
}

.exalib {
    margin: 0 10px;
    padding: 10px;
}

a.exalib-blue-cat-lib {
    margin-top: 5px;
    margin-top: 5px;
    background: url([[pix:theme|bgbutton]]) repeat-x scroll 0 0 #003876;
    border: 1px solid #003F85;
    border-radius: 7px;
    box-shadow: 0 5px 5px #005BC1 inset, 0 1px 1px rgba(0, 0, 0, 0.05);
    color: #FFFFFF;
    font-size: 14px;
    padding: 3px 20px;
}
</style>
<?
*/

if (preg_match('!rtmp://!', $item->link)) {
	$video_url = $item->link;
	$item->link = '';
} else {
	$video_url = null;
}

echo '<h2 class="head">'.$item->name.'</h2>';

echo '<table>';
if ($item->source) {
	echo '<tr><td>Source:</td><td>'.$item->source.'</td></tr>';
}

$authors = null;
if ($item->authors) {
	$authors = $item->authors;
} elseif ($tmpuser = $DB->get_record('user', ['id' => $item->created_by])) {
	$authors = fullname($tmpuser);
}
if ($authors) {
	echo '<tr><td>'.local_exalib_get_string('author').':</td><td>'.$authors.'</td></tr>';
}

if ($item->time_created) {
	echo '<tr><td>'.local_exalib_get_string('created').':</td><td>'.
		userdate($item->time_created);
	/*
	if ($item->created_by && $tmpuser = $DB->get_record('user', array('id' => $item->created_by))) {
		echo ' '.local_exalib_get_string('by_person', null, fullname($tmpuser));
	}
	*/
	echo '</td></tr>';
}
if ($item->time_modified > $item->time_created) {
	echo '<tr><td>'.local_exalib_trans(['en:Last Modified', 'de:Zuletzt geändert']).':</td><td>'.
		userdate($item->time_modified);
	if ($item->modified_by && $tmpuser = $DB->get_record('user', array('id' => $item->modified_by))) {
		echo ' '.local_exalib_get_string('by_person', null, fullname($tmpuser));
	}
	echo '</td></tr>';
}

if ($item->real_fiktiv) {
	echo '<tr><td>'.local_exalib_trans('de:Typ').':</td><td>';
	echo $item->real_fiktiv;
}

if ($files) {
	echo '<tr><td>'.local_exalib_get_string('files').':</td><td>';

	foreach ($files as $file) {
		echo '<a href="'.local_exalib_get_url_for_file($file).'" target="_blank">'.
			local_exalib_get_renderer()->pix_icon(file_file_icon($file), get_mimetype_description($file)).
			' '.$file->get_filename().'</a>&nbsp;&nbsp;&nbsp;';
	}
	echo '</td></tr>';
}

if ($item->link) {
	$linkurl = local_exalib_format_url($item->link);
	$linktext = trim($item->link_titel ?? '') ? $item->link_titel : $item->link;

	echo '<tr><td>'.local_exalib_get_string('link').':</td><td>';
	echo '<a class="head" href="'.$linkurl.'" target="_blank">'.$linktext.'</a>';
}

echo '</table>';

if ($text = trim(get_config('local_exalib', 'item_detail_bottom_info'))) {
    echo '<div style="padding-top: 40px;">'.$text.'</div>';
}

if ($item->content) {
	echo '<h2 class="head">'.local_exalib_get_string('content').'</h2>';
	echo format_text($item->content);
}

if ($item->abstract) {
	echo '<h3>'.local_exalib_get_string('abstract').'</h3>'.format_text($item->abstract).'</div>';
}

if ($video_url) {
	local_exalib_print_jwplayer(array(
		'file'    => $video_url,
		'width'    => "100%",
		'height' => "100%",
	));
}

if (@local_exalib_course_settings::allow_comments()) {
	echo '<h2 class="head">'.local_exalib_trans(['de:Kommentare', 'en:Comments']).'</h2>';


	$comments = $DB->get_records("local_exalib_item_comments", ["itemid" => $item->id], 'time_created ASC');
	foreach ($comments as $comment) {
		if ($comment->isprivate) {
			if (!in_array(g::$USER->id, [$item->created_by, $item->reviewer_id])) {
				continue;
			}
		}


		$conditions = array("id" => $comment->userid);
		$user = $DB->get_record('user', $conditions);

		echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

		echo '<tr class="header"><td class="picture left">';
		echo $OUTPUT->user_picture($user);
		echo '</td>';

		echo '<td class="topic starter"><div class="author">';

		if ($comment->isprivate) {
			echo '['.local_exalib_trans(['de:Privat', 'en:Private']).'] ';
		}

		$fullname = fullname($user, $comment->userid);
		$by = new stdClass();
		$by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.
			$user->id.'&amp;course='.$COURSE->id.'">'.$fullname.'</a>';
		$by->date = userdate($comment->time_modified);
		print_string('bynameondate', 'forum', $by);
		if (local_exalib_course_settings::allow_rating() && $comment->rating) {
			echo ' - '.local_exalib_trans(['de:Bewertung', 'en:Rating']).': ';
			echo '<span title="'.local_exalib_trans('de:{$a->rating} von {$a->max} Sternen', ['rating' => $comment->rating, 'max' => 5]).'">';
			for ($i = 1; $i <= 5; $i++) {
				echo ($comment->rating >= $i) ? '&#9733;' : '&#9734;';
			}
			echo '</span>';
		}

		if ($comment->userid == $USER->id) {
			echo ' '.$output->link_button(new moodle_url($PAGE->url, [
					'commentid' => $comment->id,
					'action' => 'comment_delete',
					'sesskey' => sesskey(),
					'back' => $PAGE->url->out_as_local_url(false),
				]), get_string('delete'), ['exa-confirm' => local_exalib_get_string('comment_delete_confirmation')]);
		}

		echo '</div></td></tr>';

		echo '<tr><td class="left side">';

		echo '</td><td class="content">'."\n";

		echo format_text($comment->text);

		echo '</td></tr></table>'."\n\n";
	}

	if (($item->allow_comments == '') // all
		|| (($item->allow_comments == 'teachers_and_reviewers') && local_exalib_is_reviewer())
		|| (($item->allow_comments == 'reviewers') && local_exalib_is_reviewer())
	) {
		$commentsform->display();
	}
}

?>
	<br/><br/>
	<a class="exalib-blue-cat-lib" href="<?php echo $back ?>"><?php echo local_exalib_get_string('back') ?></a>
	</div>
<?php

echo $output->footer();
