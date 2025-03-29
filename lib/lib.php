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

require __DIR__.'/config.php';
require __DIR__.'/common.php';

use \local_exalib\globals as g;

/**
 * local exalib new moodle url
 * @return moodle_url
 */
function local_exalib_new_moodle_url() {
	global $CFG;

	$moodlepath = preg_replace('!^[^/]+//[^/]+!', '', $CFG->wwwroot);

	return new moodle_url(str_replace($moodlepath, '', $_SERVER['REQUEST_URI']));
}

function local_exalib_is_reviewer() {
	return (bool)get_user_preferences('local_exalib_is_reviewer');
}

/**
 * is creator?
 * @return boolean
 */
function local_exalib_is_creator() {
	return local_exalib_is_admin() || has_capability('local/exalib:creator', context_system::instance());
}

/**
 * is admin?
 * @return boolean
 */
function local_exalib_is_admin() {
	return has_capability('local/exalib:admin', context_system::instance());
}

function local_exalib_require_cap($cap, $user = null) {
	// all capabilities require use
	if (!has_capability('local/exalib:use', context_system::instance(), $user)) {
		if (!g::$USER->id) {
			// not logged in and no guest
			// -> forward to login form
			require_login();
		} else {
			throw new require_login_exception(local_exalib_get_string('notallowed'));
		}
	}

	switch ($cap) {
		case LOCAL_EXALIB_CAP_USE:
			// already checked
			return;
		case LOCAL_EXALIB_CAP_MANAGE_CONTENT:
		case LOCAL_EXALIB_CAP_MANAGE_CATS:
			if (!local_exalib_is_creator()) {
				throw new local_exalib_permission_exception('no creator');
			}

			return;
		case LOCAL_EXALIB_CAP_MANAGE_REVIEWERS:
		case LOCAL_EXALIB_CAP_COURSE_SETTINGS:
			if (!local_exalib_is_admin()) {
				throw new local_exalib_permission_exception('no admin');
			}

			return;
	}

	require_capability('local/exalib:'.$cap, context_system::instance(), $user);
}

function local_exalib_has_cap($cap, $user = null) {
	try {
		local_exalib_require_cap($cap, $user);

		return true;
	} catch (local_exalib_permission_exception $e) {
		return false;
	} catch (\require_login_exception $e) {
		return false;
	} catch (\required_capability_exception $e) {
		return false;
	}
}

/**
 * local exalib require open
 * @return nothing
 */
function local_exalib_require_view_item($item_or_id) {
	local_exalib_require_cap(LOCAL_EXALIB_CAP_USE);

	if (is_object($item_or_id)) {
		$item = $item_or_id;
	} else {
		$item = g::$DB->get_record('local_exalib_item', array('id' => $item_or_id));
	}

	if (!$item) {
		throw new moodle_exception('item not found');
	}

	if ($item->created_by == g::$USER->id || $item->reviewer_id == g::$USER->id) {
		// creator and reviewer can view it
		return true;
	}

	if ($item->online > 0) {
		// all online items can be viewed
		return true;
	}

	if (local_exalib_has_cap(LOCAL_EXALIB_CAP_MANAGE_CONTENT)) {
		// admin can view
		return true;
	}

	throw new local_exalib_permission_exception('not allowed');
}

class local_exalib_permission_exception extends local_exalib\moodle_exception {
}

/**
 * local exalib require can edit item
 * @param stdClass $item
 */
function local_exalib_require_can_edit_item(stdClass $item) {
	if (local_exalib_has_cap(LOCAL_EXALIB_CAP_MANAGE_CONTENT)) {
		return true;
	}

	if (local_exalib_is_reviewer() && $item->reviewer_id == g::$USER->id && $item->online != LOCAL_EXALIB_ITEM_STATE_NEW) {
		return true;
	}

	// Item creator can edit when not freigegeben
	if ($item->created_by == g::$USER->id && $item->online == LOCAL_EXALIB_ITEM_STATE_NEW) {
		return true;
	}

	throw new local_exalib_permission_exception(local_exalib_get_string('noedit'));
}

/**
 * can edit item ?
 * @param stdClass $item
 * @return boolean
 */
function local_exalib_can_edit_item(stdClass $item) {
	try {
		local_exalib_require_can_edit_item($item);

		return true;
	} catch (local_exalib_permission_exception $e) {
		return false;
	}
}


/**
 * wrote own function, so eclipse knows which type the output renderer is
 * @return \local_exalib_renderer
 */
function local_exalib_get_renderer($init = true) {
	if ($init) {
		local_exalib_init_page();
	}

	static $renderer = null;
	if ($renderer) {
		return $renderer;
	}

	return $renderer = g::$PAGE->get_renderer('local_exalib');
}

function local_exalib_init_page() {
	static $init = true;
	if (!$init) {
		return;
	}
	$init = false;

	require_login(optional_param('courseid', g::$SITE->id, PARAM_INT));
	// g::$PAGE->set_course(g::$SITE);

	if (!g::$PAGE->has_set_url()) {
		g::$PAGE->set_url(local_exalib_new_moodle_url());
	}
}

function local_exalib_get_url_for_file(stored_file $file) {
	return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
		$file->get_itemid(), $file->get_filepath(), $file->get_filename());
}

/**
 * print jwplayer
 * @param array $options
 * @return nothing
 */
function local_exalib_print_jwplayer($options) {
    $options = array_merge(array(
		// 'primary' => "flash",
		'autostart' => false,
		// 'image' => 'https://www.e-cco-ibd.eu/pluginfile.php/145/local_html/content/MASTER_ECCO_logo_rechts_26_08_2010%20jpg.jpg'
	), $options);

    if (isset($options['file']) && preg_match('!^rtmp://.*cco-ibd.*:(.*)$!i', $options['file'], $matches)) {
        // add hls stream

        $rtmp = $options['file'];
        unset($options['file']);
        $options['playlist'] = array(
            array(
                'sources' => array(
                    array('file' => 'http://video.ecco-ibd.eu/'.$matches[1]),
                    array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.$matches[1].'/playlist.m3u8'),
                    array('file' => $rtmp),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.str_replace('.mp4', '.m4v', $matches[1]).'/playlist.m3u8'),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.strtolower('ECCO2014_SP_S7_ELouis').'.m4v/playlist.m3u8'),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:ecco2012_7.m4v/playlist.m3u8'),
                )
            )
        );
    }

    if (strpos($_SERVER['HTTP_HOST'], 'ecco-ibd')) {
    	$player = '//content.jwplatform.com/libraries/xKafWURJ.js';
	} else {
		$player = 'jwplayer/jwplayer.js';
		$options['flashplayer'] = "jwplayer/player.swf";
	}
    //

	?>
    <script type="application/javascript" src="<?=$player?>"></script>
	<div class="video-container" id='player_2834'></div>
	<script type='text/javascript'>

		// allow fullscreen in iframes, you have to add allowFullScreen to the iframe
        if (window.frameElement) {
            window.frameElement.setAttribute('allowFullScreen', 'allowFullScreen');
        }

		var options = <?php echo json_encode($options); ?>;
		if (options.width == 'auto') options.width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		if (options.height == 'auto') options.height = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;

        var p;
        var onPlay = function(){};
        var pauseVideo = false;
		if (!options.autostart) {
            // start and just load first frame
			options.autostart = true;
			options.mute = true;
            pauseVideo = true; // we want to pause it when loading

            onPlay = function(){
                if (pauseVideo) {
                    this.setMute(false);
                    this.pause();
                }
                window.setTimeout(function(){
                    // onplay fires twice?!?
                    // use setTimeout to overcome that
                    pauseVideo = false;
                }, 500);
			};
		}

        p = jwplayer('player_2834').setup(options);
        p.on('displayClick', function(){
            // user clicked the video -> don't pause video again
            pauseVideo = false;
        });
        p.on('play', onPlay);
		p.on('error', function(message){
			// $('#player_2834').replace('x');
			// confirm('Sorry, this file could not be played')console.log('ecco', message);
		});
	</script>
	<?php
}

/**
 * Exalib category manager
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 */
class local_exalib_category_manager {
	/**
	 * @var $categories - categories
	 */
	private $categories = null;
	/**
	 * @var $categoriesbyparent - categories by parent
	 */
	private $categoriesbyparent = null;

	function __construct($showOfflineToo, $limitToCategoryId = null) {
		if ($this->categories !== null) {
			// Already loaded.
			return;
		}

		$this->createdefaultcategories();

		/*
		$fields = [];
		$join = [];
		$where = [];
		$params = [];
		*/

		$this->categories = g::$DB->get_records_sql("
        	SELECT category.*
        	FROM {local_exalib_category} category
        	WHERE 1=1
        	".($showOfflineToo ? '' : "
	            AND category.online > 0
			")."
			ORDER BY name
		");

		// sort naturally (for numbers)
		uasort($this->categories, function($a, $b) {
			return strnatcmp($a->name, $b->name);
		});

		$this->categoriesbyparent = array();

		$item_category_ids = iterator_to_array(g::$DB->get_recordset_sql("
        	SELECT item.id AS item_id, ic.category_id
        	FROM {local_exalib_item} item
        	JOIN {local_exalib_item_category} ic ON item.id=ic.item_id
        	WHERE 1=1
        	".($showOfflineToo ? '' : "
    	        AND item.online > 0
				AND (item.online_from=0 OR item.online_from IS NULL OR item.online_from <= ".time().")
				AND (item.online_to=0 OR item.online_to IS NULL OR item.online_to >= ".time().")
			")."
			".local_exalib_limit_item_to_category_where($limitToCategoryId)."
		"), false);

		// init
		foreach ($this->categories as $cat) {
			$cat->self_inc_all_sub_ids = [$cat->id => $cat->id];
			$cat->cnt_inc_subs = [];
			$cat->item_ids = [];
			$cat->item_ids_inc_subs = [];
			$cat->cnt = 0;
			$cat->level = 0;
		}

		// add items for counting
		foreach ($item_category_ids as $item_category) {
			if (!isset($this->categories[$item_category->category_id])) {
				continue;
			}

			$this->categories[$item_category->category_id]->item_ids[$item_category->item_id] = $item_category->item_id;
			$this->categories[$item_category->category_id]->item_ids_inc_subs[$item_category->item_id] = $item_category->item_id;
		}

		foreach ($this->categories as $cat) {

			$this->categoriesbyparent[$cat->parent_id][$cat->id] = $cat;
			$catLeaf = $cat;

			// find parents
			while ($cat->parent_id && isset($this->categories[$cat->parent_id])) {
				// has parent
				$parentCat = $this->categories[$cat->parent_id];
				$catLeaf->level++;
				$parentCat->self_inc_all_sub_ids += $cat->self_inc_all_sub_ids;
				$parentCat->item_ids_inc_subs += $cat->item_ids_inc_subs;

				$cat = $parentCat;
			}
		}

		if ($limitToCategoryId) {
			$this->categoriesbyparent[0] = $this->categoriesbyparent[$limitToCategoryId];
		}

		// count unique ids
		foreach ($this->categories as $cat) {
			$cat->cnt_inc_subs = count($cat->item_ids_inc_subs);
		}
	}

	/**
	 * get category
	 * @param integer $categoryid
	 * @return category
	 */
	public function getcategory($categoryid) {
		return isset($this->categories[$categoryid]) ? $this->categories[$categoryid] : null;
	}

	public function getChildren($categoryid) {
		return @$this->categoriesbyparent[$categoryid];
	}

	/**
	 * get category parent id
	 * @param integer $categoryid
	 * @return array of category
	 */
	public function getcategoryparentids($categoryid) {
		$parents = array();
		for ($i = 0; $i < 100; $i++) {
			$c = $this->getcategory($categoryid);
			if ($c) {
				$parents[] = $c->id;
				$categoryid = $c->parent_id;
			} else {
				break;
			}
		}

		return $parents;
	}

	/**
	 * walk tree
	 * @param \Closure $functionbefore
	 * @param \Closure $functionafter
	 * @return string item
	 */
	public function walktree($functionbefore, $functionafter = null) {
		return $this->walktreeitem($functionbefore, $functionafter);
	}

	/**
	 * walk tree item
	 * @param \Closure $functionbefore
	 * @param \Closure $functionafter
	 * @param integer $level
	 * @param integer $parent
	 * @return output
	 */
	private function walktreeitem($functionbefore, $functionafter, $level = 0, $parent = 0) {
		if (empty($this->categoriesbyparent[$parent])) {
			return;
		}

		$output = '';
		foreach ($this->categoriesbyparent[$parent] as $cat) {
			if ($functionbefore) {
				$output .= $functionbefore($cat);
			}

			$suboutput = $this->walktreeitem($functionbefore, $functionafter, $level + 1, $cat->id);

			if ($functionafter) {
				$output .= $functionafter($cat, $suboutput);
			}
		}

		return $output;
	}

	/**
	 * create default categories
	 * @return nothing
	 */
	public function createdefaultcategories() {
		global $DB;

		if ($DB->get_records('local_exalib_category', null, '', 'id', 0, 1)) {
			return;
		}

		$DB->execute("INSERT INTO {local_exalib_category} (id, parent_id, name, online) VALUES
 			(".LOCAL_EXALIB_CATEGORY_TAGS.", 0, 'Tags', 1)");
		/*
		$DB->execute("INSERT INTO {local_exalib_category} (id, parent_id, name, online) VALUES
			(".LOCAL_EXALIB_CATEGORY_SCHULSTUFE.", 0, 'Schulstufe', 1)");
		$DB->execute("INSERT INTO {local_exalib_category} (id, parent_id, name, online) VALUES
			(".LOCAL_EXALIB_CATEGORY_SCHULFORM.", 0, 'Schulform', 1)");
		*/

		$DB->execute("ALTER TABLE {local_exalib_category} AUTO_INCREMENT=1001");
	}
}

function local_exalib_get_reviewers() {
	return g::$DB->get_records_sql("
		SELECT u.*
		FROM {user} u
		JOIN {user_preferences} p ON u.id=p.userid AND p.name='local_exalib_is_reviewer'
		WHERE p.value
		ORDER BY lastname, firstname
	");
}

function local_exalib_handle_item_delete($type) {
	$id = required_param('id', PARAM_INT);
	require_sesskey();

	$item = g::$DB->get_record('local_exalib_item', array('id' => $id));
	local_exalib_require_can_edit_item($item);

	g::$DB->delete_records('local_exalib_item', array('id' => $id));
	g::$DB->delete_records('local_exalib_item_category', array("item_id" => $id));

	if ($back = optional_param('back', '', PARAM_LOCALURL)) {
		redirect(new moodle_url($back));
	} elseif ($type == 'mine') {
		redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
	} else {
		redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
	}

	exit;
}

function local_exalib_handle_item_edit($type, $show) {
	global $CFG, $USER;

	if ($show == 'delete') {
		local_exalib_handle_item_delete($type);
	}

	if ($show == 'change_state') {
		$id = required_param('id', PARAM_INT);
		$state = required_param('state', PARAM_INT);
		require_sesskey();

		$item = g::$DB->get_record('local_exalib_item', array('id' => $id));
		local_exalib_require_can_edit_item($item);

		/*
		if ($item->created_by == g::$USER->id && $item->online == LOCAL_EXALIB_ITEM_STATE_NEW && $state == LOCAL_EXALIB_ITEM_STATE_IN_REVIEW) {
			// ok
		} elseif ($item->online == 0 || $item->online == LOCAL_EXALIB_ITEM_STATE_IN_REVIEW && $state == LOCAL_EXALIB_ITEM_STATE_NEW) {
			// ok
		} else {
			throw new moodle_exception('not allowed');
		}
		*/

		// send email to reviewer
		if ($state == LOCAL_EXALIB_ITEM_STATE_IN_REVIEW) {
			$reviewer = g::$DB->get_record('user', ['id' => $item->reviewer_id]);
			$creator = g::$USER;

			if ($reviewer) {
				$message = local_exalib_trans('de:'.join('<br />', [
						'Liebe/r '.fullname($reviewer).',',
						'',
						'Im Fallarchiv der PH-OÖ wurde von '.fullname($creator).' ('.$creator->email.') ein Fall eingetragen.',
						''.fullname($creator).' bittet Sie den Fall zu Reviewen. Bitte sehen sie den Fall durch und',
						'- geben Sie den Fall gegebenfalls frei',
						'- oder verbessern Sie den Fall',
						'- oder geben Sie den Fall zurück an den Autor zur erneuten Bearbeitung',
						'',
						'<a href="'.g::$CFG->wwwroot.'/local/exalib/detail.php?itemid='.$item->id.'&type=mine">Klicken Sie hier um den Fall zu reviewen.</a>',
						'',
						'Vielen Dank',
						'',
						'Das ist eine automatisch generierte E-Mail, bitte nicht Antworten.',
					]));

				$eventdata = new \core\message\message();
				$eventdata->component = 'local_exalib'; // Your plugin's name
				$eventdata->name = 'item_status_changed';
				$eventdata->component = 'local_exalib';
				$eventdata->userfrom = $creator;
				$eventdata->userto = $reviewer;
				$eventdata->subject = local_exalib_trans('de:PH - Kasuistik Reviewanfrage');
				$eventdata->fullmessage = $message;
				$eventdata->fullmessageformat = FORMAT_HTML;
				$eventdata->fullmessagehtml = $message;
				$eventdata->smallmessage = '';
				$eventdata->notification = 1;
				@message_send($eventdata);
			}
		}

		// send email to creator
		if ($state == LOCAL_EXALIB_ITEM_STATE_NEW) {
			$reviewer = g::$USER;
			$creator = g::$DB->get_record('user', ['id' => $item->created_by]);

			if ($creator) {
				$message = local_exalib_trans('de:'.join('<br />', [
						'Liebe/r '.fullname($creator).',',
						'',
						'Im Fallarchiv der PH-OÖ wurde Ihnen ein Fall zur Überarbeitung übergeben. Bitte überarbeiten Sie den Fall und geben in erneut zum Review frei.',
						'',
						'<a href="'.g::$CFG->wwwroot.'/local/exalib/detail.php?itemid='.$item->id.'&type=mine">Klicken Sie hier um den Fall zu überarbeiten.</a>',
						'',
						'Vielen Dank',
						'',
						'Das ist eine automatisch generierte E-Mail, bitte nicht Antworten.',
					]));

				$eventdata = new \core\message\message();
				$eventdata->name = 'item_status_changed';
				$eventdata->component = 'local_exalib';
				$eventdata->userfrom = $reviewer;
				$eventdata->userto = $creator;
				$eventdata->subject = local_exalib_trans('de:PH - Kasuistik Reviewfeedback');
				$eventdata->fullmessageformat = FORMAT_HTML;
				$eventdata->fullmessagehtml = $message;
				$eventdata->smallmessage = '';
				$eventdata->notification = 1;
				message_send($eventdata);
			}
		}

		g::$DB->update_record('local_exalib_item', [
			'id' => $item->id,
			'online' => $state,
		]);

		if ($type == 'mine') {
			redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
		} else {
			redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
		}

		exit;
	}

	require_once($CFG->libdir.'/formslib.php');

	$categoryid = optional_param('category_id', '', PARAM_INT);
	$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_system::instance());
	$fileoptions = array('subdirs' => false, 'maxfiles' => 5);

	if ($show == 'add') {
		$id = 0;
		$item = new StdClass;
		$item->online = 1;

		// local_exalib_require_creator();
	} else {
		$id = required_param('id', PARAM_INT);
		$item = g::$DB->get_record('local_exalib_item', array('id' => $id));

		local_exalib_require_can_edit_item($item);

		if ($item->online_to > 10000000000) {
			// bei den lateinern ist ein fiktiv hohes online_to drinnen
			$item->online_to = 0;
		}

		$item->contentformat = FORMAT_HTML;
		$item = file_prepare_standard_editor($item, 'content', $textfieldoptions, context_system::instance(),
			'local_exalib', 'item_content', $item->id);
		$item->abstractformat = FORMAT_HTML;
		$item = file_prepare_standard_editor($item, 'abstract', $textfieldoptions, context_system::instance(),
			'local_exalib', 'item_abstract', $item->id);
		$item = file_prepare_standard_filemanager($item, 'file', $fileoptions, context_system::instance(),
			'local_exalib', 'item_file', $item->id);
		$item = file_prepare_standard_filemanager($item, 'preview_image', $fileoptions, context_system::instance(),
			'local_exalib', 'preview_image', $item->id);
	}

	/**
	 * Items edit form
	 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
	 * @copyright  gtn gmbh <office@gtn-solutions.com>
	 */
	class item_edit_form extends moodleform {

		/**
		 * Definition
		 * @return nothing
		 */
		public function definition() {
			$mform =& $this->_form;

			$mform->addElement('text', 'name', local_exalib_get_string('name'), 'size="100"');
			$mform->setType('name', PARAM_TEXT);
			$mform->addRule('name', 'Name required', 'required', null, 'server');

			if (local_exalib_course_settings::use_review()) {
				$values = array_map('fullname', local_exalib_get_reviewers());
				$values = ['' => ''] + $values;
				$mform->addElement('select', 'reviewer_id', local_exalib_trans(['de:Reviewer', 'en:Reviewer']), $values);
				$mform->addRule('reviewer_id', get_string('requiredelement', 'form'), 'required');

				$values = [
					'' => '',
					'real' => 'real',
					'fiktiv' => 'fiktiv',
				];
				$mform->addElement('select', 'real_fiktiv', local_exalib_trans('de:Typ'), $values);
			}

			if (!local_exalib_course_settings::alternative_wording()) {
				$mform->addElement('text', 'source', local_exalib_get_string('source'), 'size="100"');
				$mform->setType('source', PARAM_TEXT);
			}

			/*
			$values = g::$DB->get_records_sql_menu("
				SELECT c.id, c.name
				FROM {local_exalib_category} c
				WHERE parent_id=".LOCAL_EXALIB_CATEGORY_SCHULSTUFE."
			   ");
			$mform->addElement('select', 'schulstufeid', local_exalib_trans('de:Schulstufe'), $values);
			$mform->addRule('schulstufeid', get_string('requiredelement', 'form'), 'required');

			$values = g::$DB->get_records_sql_menu("
				SELECT c.id, c.name
				FROM {local_exalib_category} c
				WHERE parent_id=".LOCAL_EXALIB_CATEGORY_SCHULFORM."
			   ");
			$mform->addElement('select', 'schulformid', local_exalib_trans('de:Schulform'), $values);
			$mform->addRule('schulformid', get_string('requiredelement', 'form'), 'required');
			*/

			$mform->addElement('text', 'authors', local_exalib_get_string('authors'), 'size="100"');
			$mform->setType('authors', PARAM_TEXT);
$to_year = date('Y') + 1;
			$values = range(2010,$to_year);
			$values = ['' => ''] + array_combine($values, $values);
			$mform->addElement('select', 'year', local_exalib_get_string('year', 'form'), $values);
			$mform->setType('year', PARAM_INT);

			$mform->addElement('editor', 'abstract_editor', local_exalib_get_string('abstract'), 'rows="10" cols="50" style="width: 95%"');
			$mform->setType('abstract', PARAM_RAW);

			$mform->addElement('header', 'contentheader', local_exalib_get_string('content'));
			$mform->setExpanded('contentheader');

			$mform->addElement('text', 'link', local_exalib_get_string('link'), 'size="100"');
			$mform->setType('link', PARAM_TEXT);

			$mform->addElement('editor', 'content_editor', local_exalib_get_string('content'), 'rows="20" cols="50" style="width: 95%"');
			$mform->setType('content', PARAM_RAW);

			$mform->addElement('filemanager', 'file_filemanager', local_exalib_get_string('files'), null, $this->_customdata['fileoptions']);

			$mform->addElement('filemanager', 'preview_image_filemanager', local_exalib_get_string('previmg'), null,
				$this->_customdata['fileoptions']);

			$mform->addElement('header', 'categoriesheader', local_exalib_get_string('categories'));
			$mform->setExpanded('categoriesheader');

			$mform->addElement('static', 'categories', local_exalib_get_string('groups'), $this->get_categories());

			if ($this->_customdata['type'] != 'mine') {
				$mform->addElement('header', 'onlineheader', local_exalib_get_string('onlineset'));

				$mform->addElement('advcheckbox', 'online', local_exalib_get_string('online'));

				$mform->addElement('date_selector', 'online_from', local_exalib_get_string('onlinefrom'), array(
					'startyear' => 2014,
					'stopyear' => date('Y') + 10,
					'optional' => true,
				));
				$mform->addElement('date_selector', 'online_to', local_exalib_get_string('onlineto'), array(
					'startyear' => 2014,
					'stopyear' => date('Y') + 10,
					'optional' => true,
				));
			} elseif (local_exalib_is_reviewer()) {
				// $mform->addElement('advcheckbox', 'online', local_exalib_get_string('online'));

				$radioarray = array();
				$radioarray[] = $mform->createElement('radio', 'online', '', local_exalib_trans(['de:in Review', 'en:in review']), LOCAL_EXALIB_ITEM_STATE_IN_REVIEW);
				$radioarray[] = $mform->createElement('radio', 'online', '', local_exalib_get_string('offline'), 0);
				$radioarray[] = $mform->createElement('radio', 'online', '', local_exalib_get_string('online'), 1);
				$mform->addGroup($radioarray, 'online', local_exalib_get_string("status"), array(' '), false);
			}

			$radioarray = array();
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_exalib_trans(['de:Alle Benutzer/innen', 'en:All users']), '');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_exalib_trans(['de:Lehrende und Redaktionsteam', 'en:Teachers and Reviewers']), 'teachers_and_reviewers');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_exalib_trans(['de:Redaktionsteam', 'en:Reviewers']), 'reviewers');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_exalib_trans(['de:Keine Kommentare erlauben', 'en:No one (Disable comments)']), 'none');
			$mform->addGroup($radioarray, 'allow_comments', local_exalib_trans(['de:Kommentare erlauben von', 'en:Allow comments from']), array(' '), false);

			$this->add_action_buttons();
		}

		/**
		 * Get categories
		 * @return checkbox
		 */
		public function get_categories() {
			$mgr = new local_exalib_category_manager(true, local_exalib_course_settings::root_category_id());

			return $mgr->walktree(null, function($cat, $suboutput) {
				return '<div style="padding-left: '.(20 * $cat->level).'px;">'.
				'<input type="checkbox" name="categories[]" value="'.$cat->id.'" '.
				(in_array($cat->id, $this->_customdata['itemCategories']) ? 'checked ' : '').'/>'.
				($cat->level == 0 ? '<b>'.$cat->name.'</b>' : $cat->name).
				'</div>'.$suboutput;
			});
		}
	}

	$itemcategories = g::$DB->get_records_sql_menu("SELECT category.id, category.id AS val
    FROM {local_exalib_category} category
    LEFT JOIN {local_exalib_item_category} ic ON category.id=ic.category_id
    WHERE ic.item_id=?", array($id));

	if (!$itemcategories && $categoryid) {
		// at least one category
		$itemcategories[$categoryid] = $categoryid;
	}

	$itemeditform = new item_edit_form($_SERVER['REQUEST_URI'], [
		'itemCategories' => $itemcategories,
		'fileoptions' => $fileoptions,
		'type' => $type,
	]);

	if ($itemeditform->is_cancelled()) {
		if ($back = optional_param('back', '', PARAM_LOCALURL)) {
			redirect(new moodle_url($back));
		} else {
			redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
		}
	} else {
		if ($fromform = $itemeditform->get_data()) {
			// Edit/add.

			if ($type == 'mine' && empty($item->id)) {
				// normal user items should be offline first
				$fromform->online = LOCAL_EXALIB_ITEM_STATE_NEW;
			}

			if (!empty($item->id)) {
				$fromform->id = $item->id;
				$fromform->modified_by = $USER->id;
				$fromform->time_modified = time();
			} else {
				$fromform->created_by = $USER->id;
				$fromform->time_created = time();
				$fromform->time_modified = 0;
				$fromform->id = g::$DB->insert_record('local_exalib_item', $fromform);
			}

			$fromform->contentformat = FORMAT_HTML;
			$fromform = file_postupdate_standard_editor($fromform,
				'content',
				$textfieldoptions,
				context_system::instance(),
				'local_exalib',
				'item_content',
				$fromform->id);
			$fromform->abstractformat = FORMAT_HTML;
			$fromform = file_postupdate_standard_editor($fromform,
				'abstract',
				$textfieldoptions,
				context_system::instance(),
				'local_exalib',
				'item_content',
				$fromform->id);

			g::$DB->update_record('local_exalib_item', $fromform);

			// Save file.
			$fromform = file_postupdate_standard_filemanager($fromform,
				'file',
				$fileoptions,
				context_system::instance(),
				'local_exalib',
				'item_file',
				$fromform->id);
			$fromform = file_postupdate_standard_filemanager($fromform,
				'preview_image',
				$fileoptions,
				context_system::instance(),
				'local_exalib',
				'preview_image',
				$fromform->id);


			// Save categories.
			g::$DB->delete_records('local_exalib_item_category', array("item_id" => $fromform->id));
			$categories_request = local_exalib\param::optional_array('categories', PARAM_INT);

			if ($root_category_id = local_exalib_course_settings::root_category_id()) {
				// if course has a root category, always add it
				if (!in_array($root_category_id, $categories_request)) {
					$categories_request[$root_category_id] = $root_category_id;
				}
			}

			foreach ($categories_request as $categoryidforinsert) {
				g::$DB->execute('INSERT INTO {local_exalib_item_category} (item_id, category_id) VALUES (?, ?)',
					array($fromform->id, $categoryidforinsert));
			}

			if ($back = optional_param('back', '', PARAM_LOCALURL)) {
				redirect(new moodle_url($back));
			} elseif ($type == 'mine') {
				redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
			} else {
				redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
			}
			exit;

		} else {
			// Display form.

			$output = local_exalib_get_renderer();

			echo $output->header(defined('LOCAL_EXALIB_IS_ADMIN_MODE') && LOCAL_EXALIB_IS_ADMIN_MODE ? 'tab_manage_content' : null);

			$itemeditform->set_data($item);
			$itemeditform->display();

			echo $output->footer();
		}
	}
}

function local_exalib_format_url($url) {
	if (!preg_match('!^.*://!', $url)) {
		$url = 'http://'.$url;
	}

	return $url;
}

function local_exalib_get_fachsprachliches_lexikon_id() {
	return g::$DB->get_field('glossary', 'id', ['course' => g::$COURSE->id, 'name' => 'Fachsprachliches Lexikon']);
}

function local_exalib_get_fachsprachliches_lexikon_items() {
	$glossaryid = local_exalib_get_fachsprachliches_lexikon_id();

	return g::$DB->get_records_sql("
		SELECT concept, definition
		FROM {glossary_entries}
		WHERE glossaryid = ?
		ORDER BY concept
	", [$glossaryid]);

	return $records;
}

/**
 * @method static int root_category_id()
 * @method static bool alternative_wording()
 * @method static bool use_review()
 * @method static bool use_terms_of_service()
 * @method static bool allow_comments()
 * @method static bool allow_rating()
 * @property int root_category_id
 * @property bool alternative_wording
 * @property bool use_review
 * @property bool use_terms_of_service
 * @property bool allow_comments
 * @property bool allow_rating
 */
class local_exalib_course_settings {

	static protected $courses = [];

	protected $courseid;
	protected $settings;

	function __construct($courseid) {
		$this->courseid = $courseid;

		$settings = get_config('local_exalib', "course[$courseid]");
		if ($settings) {
			$settings = json_decode($settings);
		}

		if (!$settings) {
			$this->settings = (object)[];
		} else {
			$this->settings = (object)$settings;
		}
	}

	static function get_course($courseid = null) {
		if ($courseid === null) {
			$courseid = g::$COURSE->id;
		}

		if (isset(static::$courses[$courseid])) {
			return static::$courses[$courseid];
		} else {
			return static::$courses[$courseid] = new static($courseid);
		}
	}

	static function __callStatic($name, $arguments) {
		$settings = static::get_course();

		return $settings->$name;
	}

	function __get($name) {
		//if (in_array($name, ['root_category_id'])) {
		if ($name == 'allow_rating') {
			$name = 'allow_comments';
		}

		return @$this->settings->$name;
		//} else {
		//	throw new moodle_exception("function $name not found");
		//}
	}

	function __set($name, $value) {
		$this->settings->$name = $value;
	}

	function save() {
		$settings = json_encode($this->settings);
		set_config("course[{$this->courseid}]", $settings, 'local_exalib');
	}
}

function local_exalib_limit_item_to_category_where($category_id) {
	if (!$category_id) {
		return '';
	} else {
		return " AND item.id IN (
			SELECT item_id FROM {local_exalib_item_category}
			WHERE category_id=".(int)$category_id."
		)";
	}
}