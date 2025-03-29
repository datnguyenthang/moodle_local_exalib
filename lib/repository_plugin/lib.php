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

require_once($CFG->dirroot.'/repository/lib.php');
require __DIR__.'/../../inc.php';

class repository_exalib extends repository {
    /**
     * Exalib plugin doesn't require login, so list all files
     *
     * @return mixed
     */
    public function print_login() {
        return $this->get_listing();
    }

    private function get_item_list_items($item) {
        global $DB, $OUTPUT;

        $fs = get_file_storage();
        $listItems = [];

        if ($item->link) {
            $listItems[] = [
                'title' => $item->name.' - '.$item->link, // local_exalib_get_string('link'),
                // 'size' => $file->get_filesize(),
                'source' => $item->link,
                // 'datemodified' => $file->get_timemodified(),
                // 'datecreated' => $file->get_timecreated(),
                'author' => trim($item->authors),
                // 'license' => $file->get_license(),
                'isref' => true,
                'icon' => $OUTPUT->image_url(file_file_icon(null, 24))->out(false),
                'thumbnail' => $OUTPUT->image_url(file_file_icon(null, 90))->out(false)
            ];
        }

        $files = $fs->get_area_files(context_system::instance()->id,
            'local_exalib',
            'item_file',
            $item->id,
            'itemid',
            false);

        foreach ($files as $file) {
            $fileurl = moodle_url::make_draftfile_url($item->id, $file->get_filepath(), $file->get_filename());
            $listItems[] = [
                'title' => $item->name.' - '.$file->get_filename(),
                'size' => $file->get_filesize(),
                'source' => $fileurl->out(),
                'datemodified' => $file->get_timemodified(),
                'datecreated' => $file->get_timecreated(),
                'author' => trim($item->authors) ?: $file->get_author(),
                'license' => $file->get_license(),
                'isref' => $file->is_external_file(),
                'iscontrolledlink' => $file->is_controlled_link(),
                'icon' => $OUTPUT->image_url(file_file_icon($file, 24))->out(false),
                'thumbnail' => $OUTPUT->image_url(file_file_icon($file, 90))->out(false)
            ];
        }

        return $listItems;
    }


    private function get_children($categoryid) {
        global $DB;

        // -- AND item.id IN (".join(',', [0] + $currentcategory->item_ids_inc_subs).")
        $sqlwhere = "AND item.online > 0
            AND (item.online_from=0 OR item.online_from IS NULL OR item.online_from <= ".time().")
            AND (item.online_to=0 OR item.online_to IS NULL OR item.online_to >= ".time().")";
        $sql = "
            SELECT item.*
            FROM {local_exalib_item} item
		    JOIN {local_exalib_item_category} ic ON item.id=ic.item_id
            WHERE 1=1
                AND ic.category_id=?
                $sqlwhere
            ORDER BY GREATEST(item.time_created,item.time_modified) DESC
        ";

        $items = $DB->get_records_sql($sql, [$categoryid], 0, 0);

        $listItems = [];

        foreach ($items as $item) {
            $listItems = array_merge($listItems, $this->get_item_list_items($item));
        }

        return $listItems;
    }


    /**
     * Get file listing
     *
     * @param string $path
     * @param string $path not used by this plugin
     * @return mixed
     */
    public function get_listing($path = '', $page = '') {
        global $DB, $OUTPUT;

        $mgr = new local_exalib_category_manager(false, local_exalib_course_settings::root_category_id());
        $categoryid = $path;
        $currentcategory = $mgr->getcategory($categoryid);

        $rootcategory = (object)[
            'name' => local_exalib_get_string('pluginname'),
            'id' => 0,
        ];

        if (!$currentcategory) {
            // use root when category not found or not set
            $categoryid = 0;
            $currentcategory = $rootcategory;
        }

        $currentcategoryparents = $mgr->getcategoryparentids($categoryid);

        $categoryBreadcrumbs = array_merge(
            [$rootcategory],
            array_reverse(array_map(function($categoryid) use ($mgr) {
                return $mgr->getcategory($categoryid);
            }, $currentcategoryparents)),
        // $currentcategory->id > 0 ? [$currentcategory] : [],
        );

        $path = [];
        foreach ($categoryBreadcrumbs as $categoryBreadcrumb) {
            $path[] = [
                'name' => $categoryBreadcrumb->name,
                'path' => $categoryBreadcrumb->id,
            ];
        }

        $listItems = [];

        foreach ($mgr->getChildren($categoryid) as $child) {
            $listItems[] = [
                'title' => $child->name,
                'path' => $child->id,
                'date' => '0',
                'size' => '0',
                'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
                'children' => [], // $this->get_children($child->id),
            ];
        }

        $listItems = array_merge($listItems, $this->get_children($categoryid));

        $ret = [
            'dynload' => true,
            'nologin' => true,

            //this will be used to build navigation bar.
            'path' => $path,
            'list' => $listItems,
        ];

        $ret['list'] = array_filter($ret['list'], array($this, 'filter'));

        return $ret;
    }

    public function search($search_text, $page = 0) {
        global $DB;

        $q = $search_text;
        $q = trim($q);

        $qparams = preg_split('!\s+!', $q);

        $sqljoin = "";
        $sqlparams = array();

        // if ($currentcategory) {
        //     $sqlwhere .= " AND item.id IN (".join(',', [0] + $currentcategory->item_ids_inc_subs).")";
        // }

        foreach ($qparams as $i => $qparam) {
            $search_fields = [
                'item.link', 'item.source', 'item.file', 'item.name', 'item.authors',
                'item.abstract', 'item.content', 'item.link_titel', "c$i.name",
            ];

            $sqljoin .= " LEFT JOIN {local_exalib_item_category} ic$i ON item.id=ic$i.item_id";
            $sqljoin .= " LEFT JOIN {local_exalib_category} c$i ON ic$i.category_id=c$i.id";
            $sqlwhere .= " AND ".$DB->sql_concat_join("' '", $search_fields)." LIKE ?";
            $sqlparams[] = "%".$DB->sql_like_escape($qparam)."%";
        }

        $sql = "SELECT item.*
            FROM {local_exalib_item} item
            $sqljoin
            WHERE 1=1 $sqlwhere
            GROUP BY item.id
            ORDER BY name";

        $items = $DB->get_records_sql($sql, $sqlparams);

        $listItems = [];

        foreach ($items as $item) {
            $listItems = array_merge($listItems, $this->get_item_list_items($item));
        }

        return [
            'dynload' => true,
            'nologin' => true,
            'list' => $listItems,
        ];
    }

    /**
     * This plugin only can return link
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_REFERENCE;
    }
}
