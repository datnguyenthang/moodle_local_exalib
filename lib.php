<?php

defined('MOODLE_INTERNAL') || die();

require __DIR__.'/inc.php';

/**
 * plugin file
 * @param integer $course
 * @param integer $cm
 * @param string $context
 * @param string $filearea
 * @param array $args
 * @param integer $forcedownload
 * @param array $options
 * @return nothing
 */
function local_exalib_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options) {
	$fs = get_file_storage();

	$file = null;

	if ($filearea == 'item_file' || $filearea == 'preview_image') {
		local_exalib_require_view_item($args[0]);

		$file = $fs->get_file($context->id,
			'local_exalib',
			$filearea,
			$args[0],
			'/',
			$args[1]);
	}

	if (!$file) {
		send_file_not_found();
	}

	send_stored_file($file, 0, 0, $forcedownload, $options);
}
