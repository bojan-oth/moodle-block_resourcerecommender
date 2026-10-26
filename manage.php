<?php

require_once(__DIR__ . '/../../config.php');

use core\output\notification;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('block/resourcerecommender:managetopics', $context);

$PAGE->set_url(new moodle_url('/blocks/resourcerecommender/manage.php', [
    'courseid' => $courseid,
]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('managetopics', 'block_resourcerecommender'));
$PAGE->set_heading($course->fullname);

global $DB, $OUTPUT;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'addtopic') {
        $name = required_param('name', PARAM_TEXT);
        $keywords = optional_param('keywords', '', PARAM_TEXT);
        $cmids = optional_param_array('cmids', [], PARAM_INT);

        $name = trim($name);
        $keywords = trim($keywords);

        if ($name === '') {
            $message = get_string('topicnamerequired', 'block_resourcerecommender');
        } else {
            $existing = $DB->record_exists('block_rr_topics', [
                'courseid' => $courseid,
                'name' => $name,
            ]);

            if ($existing) {
                $message = get_string('topicexists', 'block_resourcerecommender');
            } else {
                $now = time();

                $topic = (object) [
                    'courseid' => $courseid,
                    'name' => $name,
                    'keywords' => $keywords,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];

                $topicid = $DB->insert_record('block_rr_topics', $topic);

                $modinfo = get_fast_modinfo($course);
                foreach ($cmids as $cmid) {
                    if (!isset($modinfo->cms[$cmid])) {
                        continue;
                    }

                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }

                    if (!in_array($cm->modname, ['page', 'resource', 'url', 'book'], true)) {
                        continue;
                    }

                    $mapping = (object) [
                        'topicid' => $topicid,
                        'cmid' => $cmid,
                        'timecreated' => $now,
                    ];

                    $DB->insert_record('block_rr_mapping', $mapping);
                }

                redirect(
                    $PAGE->url,
                    get_string('topiccreated', 'block_resourcerecommender'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetopics', 'block_resourcerecommender'));

if ($message !== '') {
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);
}


echo $OUTPUT->heading(get_string('existingtopics', 'block_resourcerecommender'), 3);

$topics = $DB->get_records(
    'block_rr_topics',
    ['courseid' => $courseid],
    'name ASC'
);

$modinfo = get_fast_modinfo($course);

if (empty($topics)) {
    echo $OUTPUT->notification(
        get_string('notopics', 'block_resourcerecommender'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    foreach ($topics as $topic) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo $OUTPUT->heading(format_string($topic->name), 4);

        if (trim((string) $topic->keywords) !== '') {
            echo html_writer::tag(
                'p',
                html_writer::tag('strong', get_string('topickeywords', 'block_resourcerecommender') . ': ')
                . s($topic->keywords)
            );
        }

        echo html_writer::tag(
            'p',
            html_writer::tag('strong', get_string('associatedresources', 'block_resourcerecommender'))
        );

        $mappings = $DB->get_records(
            'block_rr_mapping',
            ['topicid' => $topic->id],
            'id ASC'
        );

        $resourceitems = [];

        foreach ($mappings as $mapping) {
            if (!isset($modinfo->cms[$mapping->cmid])) {
                continue;
            }

            $cm = $modinfo->cms[$mapping->cmid];

            if (!$cm->uservisible) {
                continue;
            }

            $resourceitems[] = html_writer::link(
                $cm->url,
                format_string($cm->name)
            );
        }

        if (empty($resourceitems)) {
            echo html_writer::tag(
                'p',
                get_string('nomappedresources', 'block_resourcerecommender')
            );
        } else {
            echo html_writer::alist($resourceitems);
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}


$resources = [];

foreach ($modinfo->get_cms() as $cm) {
    if (!$cm->uservisible) {
        continue;
    }

    if (!in_array($cm->modname, ['page', 'resource', 'url', 'book'], true)) {
        continue;
    }

    $resources[] = $cm;
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'action',
    'value' => 'addtopic',
]);

echo html_writer::tag('label', get_string('topicname', 'block_resourcerecommender'), [
    'for' => 'topicname',
]);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'name',
    'id' => 'topicname',
    'required' => 'required',
    'maxlength' => 255,
    'class' => 'form-control mb-3',
]);

echo html_writer::tag('label', get_string('topickeywords', 'block_resourcerecommender'), [
    'for' => 'topickeywords',
]);

echo html_writer::tag('input', '', [
    'type' => 'text',
    'name' => 'keywords',
    'id' => 'topickeywords',
    'class' => 'form-control mb-3',
    'placeholder' => 'SQL, SELECT, WHERE',
]);

echo html_writer::tag('p', get_string('selectresources', 'block_resourcerecommender'));

foreach ($resources as $cm) {
    echo html_writer::div(
        html_writer::checkbox('cmids[]', $cm->id, false, format_string($cm->name)),
        'mb-2'
    );
}

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('createtopic', 'block_resourcerecommender'),
    'class' => 'btn btn-primary mt-3',
]);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
