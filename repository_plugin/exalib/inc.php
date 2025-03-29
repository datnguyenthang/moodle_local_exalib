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

function repository_exalib_dir($file) {
    $sub_path = str_replace(__DIR__, '', $file);

    $exalib_path = dirname(dirname(__DIR__)).'/local/exalib';
    $file_path = $exalib_path.'/lib/repository_plugin'.$sub_path;

    if (!is_dir($exalib_path)) {
        die('Exabis Library not installed');
    }
    if (!is_file($file_path)) {
        die('Repository Plugin and Exabis Library not compatible?!?');
    }

    return $file_path;
}
