<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Ensure that the settings object is available
    $settings = new admin_settingpage('local_exalib', get_string('pluginname', 'local_exalib'));

    // Add the settings field
    $settings->add(new admin_setting_configtextarea('local_exalib/item_detail_bottom_info',
        get_string('item_detail_bottom_info', 'local_exalib'),
        '', '', PARAM_RAW));

    // Add the settings page to the local category
    $ADMIN->add('localplugins', $settings);
}

