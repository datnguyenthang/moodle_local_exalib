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
local_exalib_require_view();

if ($itemid = optional_param('itemid', 0, PARAM_INT)) {
    if (!$item = $DB->get_record('local_exalib_item', array('id' => $itemid))) {
        print_error(local_exalib_get_string('itemnotfound'));
    }

    $videourl = $item->link;
} else if ($videourl = optional_param('video_url', '', PARAM_TEXT)) {
    $fcc = 1; // For code checker.
} else {
    print_error("no video url");
}

$PAGE->set_course($SITE);

$PAGE->set_url('/local/exalib');
$PAGE->set_pagelayout('embedded');

$width = optional_param('width', 'auto', PARAM_TEXT);
$height = optional_param('height', $width > 0 ? $width / 610 * 510 : 'auto', PARAM_TEXT);

/* <script type="text/javascript">jwplayer.key="YOUR_JWPLAYER_LICENSE_KEY";</script> */

$output = local_exalib_get_renderer();
echo $output->header();

?>
<style>
html, body {
    overflow: hidden;
    width: 100%;
    height: 100%;
}
.pagelayout-embedded #content {
    padding: 0;
    margin: 0;
}
</style>

<?php
    local_exalib_print_jwplayer(array(
        'file'    => $videourl,
        'width'    => $width,
        'height' => $height,
        'autostart' => optional_param('autostart', 0, PARAM_INT)
    ));

echo $output->footer();
