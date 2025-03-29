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

$output = local_exalib_get_renderer();
$output->set_tabs('tab_fachsprachliches_lexikon');

$items = local_exalib_get_fachsprachliches_lexikon_items();

echo $output->header();

if (!$items) {
	echo local_exalib_get_string('noitemsfound');
} else {
	$lastLetter = '';

	?>
	<style>
		table.fachsprachliches_lexikon td {
			border: 1px solid #aaa;
			padding: 10px;
			vertical-align: top;
		}
		table.fachsprachliches_lexikon .spacer td {
			border: none;
		}
	</style>
	<?php
	echo '<table class="fachsprachliches_lexikon">';
	foreach ($items as $item) {
		$letter = substr($item->concept, 0, 1);
		if ($letter != $lastLetter) {
			if ($lastLetter) {
				echo '<tr class="spacer"><td>&nbsp;</td></tr>';
			}
			$lastLetter = $letter;
			echo '<tr style="background: lightblue">';
			echo '<td colspan="3">'.$letter.'</td>';
			echo '</tr>';

			echo '<tr style="font-weight: bold; background: #ddd">';
			echo '<td>'.'Fachsprache'.'</td>';
			echo '<td>'.'Bedeutung'.'</td>';
			echo '</tr>';
		}

		echo '<tr>';
		echo '<td>'.$item->concept.'</td>';
		echo '<td>'.$item->definition.'</td>';
		echo '</tr>';
	}
	echo '</table>';
}

echo $output->footer();
