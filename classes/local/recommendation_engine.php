<?php

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
