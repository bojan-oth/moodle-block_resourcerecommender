<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Main class for the Resource Recommender block.
 *
 * @package    block_resourcerecommender
 */
class block_resourcerecommender extends block_base {

    /**
     * Initialise the block title.
     */
    public function init(): void {
        $this->title = get_string(
            'pluginname',
            'block_resourcerecommender'
        );
    }

    /**
     * Return the content displayed inside the block.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $OUTPUT, $COURSE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $courseid = isset($COURSE->id) ? (int) $COURSE->id : 0;

        $currentcmid = isset($this->page->cm->id)
            ? (int) $this->page->cm->id
            : 0;

        $userid = isset($USER->id) ? (int) $USER->id : 0;

        // Call the recommendation engine. Its implementation comes later.
        $engine = new \block_resourcerecommender\local\recommendation_engine();

        $recommendations = $engine->get_recommendations(
            $courseid,
            $currentcmid,
            $userid
        );

        $data = [
            'recommendations' => $recommendations,
            'noresults' => get_string(
                'norecommendations',
                'block_resourcerecommender'
            ),
        ];

        $this->content->text = $OUTPUT->render_from_template(
            'block_resourcerecommender/recommendations',
            $data
        );

        return $this->content;
    }

    /**
     * Define the pages where this block can be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'admin' => false,
            'site-index' => false,
            'course-view' => true,
            'mod' => true,
            'my' => false,
        ];
    }
}
