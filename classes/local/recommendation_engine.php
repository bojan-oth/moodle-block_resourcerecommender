<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_resourcerecommender\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides recommendations for the Resource Recommender block.
 *
 * @package    block_resourcerecommender
 */
class recommendation_engine {

    /**
     * Return recommendations for the current resource.
     *
     * The recommendation logic will be implemented in a later issue.
     *
     * @param int $courseid The current course ID.
     * @param int $currentcmid The current course-module ID.
     * @param int $userid The current user ID.
     * @return array
     */
    public function get_recommendations(
        int $courseid,
        int $currentcmid,
        int $userid
    ): array {
        // No recommendations are generated in the foundation issue.
        return [];
    }
}
