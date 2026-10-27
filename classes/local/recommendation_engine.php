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
        global $DB;

        if ($courseid <= 0 || $currentcmid <= 0 || $userid <= 0) {
            return [];
        }

        // Find other resources sharing topics with the current resource.
        $sql = "SELECT targetmap.cmid,
                       COUNT(DISTINCT currentmap.topicid) AS relevance
                  FROM {block_rr_mapping} currentmap
                  JOIN {block_rr_topics} topic
                    ON topic.id = currentmap.topicid
                   AND topic.courseid = :courseid
                  JOIN {block_rr_mapping} targetmap
                    ON targetmap.topicid = currentmap.topicid
                  JOIN {course_modules} targetcm
                    ON targetcm.id = targetmap.cmid
                   AND targetcm.course = :targetcourseid
                 WHERE currentmap.cmid = :currentcmid
                   AND targetmap.cmid <> :excludedcmid
              GROUP BY targetmap.cmid
              ORDER BY relevance DESC, targetmap.cmid ASC";

        $matches = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'targetcourseid' => $courseid,
            'currentcmid' => $currentcmid,
            'excludedcmid' => $currentcmid,
        ]);

        if (empty($matches)) {
            return [];
        }

        // Moodle's module information respects the current user's access.
        $modinfo = get_fast_modinfo($courseid, $userid);
        $recommendations = [];

        foreach ($matches as $match) {
            $cmid = (int) $match->cmid;

            if (!isset($modinfo->cms[$cmid])) {
                continue;
            }

            $cm = $modinfo->cms[$cmid];

            if (!$cm->uservisible || empty($cm->url)) {
                continue;
            }

            if (!in_array($cm->modname, ['page', 'resource', 'url', 'book'], true)) {
                continue;
            }

            $recommendations[] = [
                'title' => format_string($cm->name),
                'url' => $cm->url->out(false),
                'relevance' => (int) $match->relevance,
            ];
        }

        return $recommendations;
    }
}
