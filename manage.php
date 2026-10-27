<?php

require_once(__DIR__ . '/../../config.php');

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
$messagetype = \core\output\notification::NOTIFY_ERROR;

$modinfo = get_fast_modinfo($course);
$resources = [];

foreach ($modinfo->get_cms() as $cm) {
    if (!$cm->uservisible || empty($cm->url)) {
        continue;
    }

    if (!in_array($cm->modname, ['page', 'resource', 'url', 'book'], true)) {
        continue;
    }

    $resources[$cm->id] = $cm;
}

// Handle form submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHA);
    $now = time();

    // Create a new topic.
    if ($action === 'addtopic') {
        $name = trim(required_param('name', PARAM_TEXT));
        $keywords = trim(optional_param('keywords', '', PARAM_TEXT));
        $cmids = optional_param_array('cmids', [], PARAM_INT);

        if ($name === '') {
            $message = get_string(
                'topicnamerequired',
                'block_resourcerecommender'
            );
        } else if ($DB->record_exists('block_rr_topics', [
            'courseid' => $courseid,
            'name' => $name,
        ])) {
            $message = get_string(
                'topicexists',
                'block_resourcerecommender'
            );
        } else {
            $transaction = $DB->start_delegated_transaction();

            $topic = (object) [
                'courseid' => $courseid,
                'name' => $name,
                'keywords' => $keywords,
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            $topicid = $DB->insert_record('block_rr_topics', $topic);

            foreach (array_unique($cmids) as $cmid) {
                if (!isset($resources[$cmid])) {
                    continue;
                }

                $DB->insert_record('block_rr_mapping', (object) [
                    'topicid' => $topicid,
                    'cmid' => $cmid,
                    'timecreated' => $now,
                ]);
            }

            $transaction->allow_commit();

            redirect(
                $PAGE->url,
                get_string('topiccreated', 'block_resourcerecommender'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

    // Edit an existing topic.
    } else if ($action === 'edittopic') {
        $topicid = required_param('topicid', PARAM_INT);
        $name = trim(required_param('name', PARAM_TEXT));
        $keywords = trim(optional_param('keywords', '', PARAM_TEXT));

        $topic = $DB->get_record('block_rr_topics', [
            'id' => $topicid,
            'courseid' => $courseid,
        ]);

        if (!$topic) {
            $message = get_string(
                'topicnotfound',
                'block_resourcerecommender'
            );
        } else if ($name === '') {
            $message = get_string(
                'topicnamerequired',
                'block_resourcerecommender'
            );
        } else if ($DB->record_exists_select(
            'block_rr_topics',
            'courseid = :courseid AND name = :name AND id <> :id',
            [
                'courseid' => $courseid,
                'name' => $name,
                'id' => $topicid,
            ]
        )) {
            $message = get_string(
                'topicexists',
                'block_resourcerecommender'
            );
        } else {
            $topic->name = $name;
            $topic->keywords = $keywords;
            $topic->timemodified = $now;

            $DB->update_record('block_rr_topics', $topic);

            redirect(
                $PAGE->url,
                get_string('topicupdated', 'block_resourcerecommender'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

    // Delete a topic and its mappings, not the Moodle resources.
    } else if ($action === 'deletetopic') {
        $topicid = required_param('topicid', PARAM_INT);

        $topic = $DB->get_record('block_rr_topics', [
            'id' => $topicid,
            'courseid' => $courseid,
        ]);

        if (!$topic) {
            $message = get_string(
                'topicnotfound',
                'block_resourcerecommender'
            );
        } else {
            $transaction = $DB->start_delegated_transaction();

            $DB->delete_records('block_rr_mapping', [
                'topicid' => $topicid,
            ]);

            $DB->delete_records('block_rr_topics', [
                'id' => $topicid,
                'courseid' => $courseid,
            ]);

            $transaction->allow_commit();

            redirect(
                $PAGE->url,
                get_string('topicdeleted', 'block_resourcerecommender'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

    // Add resources to an existing topic.
    } else if ($action === 'addresources') {
        $topicid = required_param('topicid', PARAM_INT);
        $cmids = optional_param_array('cmids', [], PARAM_INT);

        $topic = $DB->get_record('block_rr_topics', [
            'id' => $topicid,
            'courseid' => $courseid,
        ]);

        if (!$topic) {
            $message = get_string(
                'topicnotfound',
                'block_resourcerecommender'
            );
        } else if (empty($cmids)) {
            $message = get_string(
                'selectresourcestoadd',
                'block_resourcerecommender'
            );
        } else {
            $transaction = $DB->start_delegated_transaction();
            $added = 0;

            foreach (array_unique($cmids) as $cmid) {
                if (!isset($resources[$cmid])) {
                    continue;
                }

                if ($DB->record_exists('block_rr_mapping', [
                    'topicid' => $topicid,
                    'cmid' => $cmid,
                ])) {
                    continue;
                }

                $DB->insert_record('block_rr_mapping', (object) [
                    'topicid' => $topicid,
                    'cmid' => $cmid,
                    'timecreated' => $now,
                ]);

                $added++;
            }

            $transaction->allow_commit();

            if ($added > 0) {
                redirect(
                    $PAGE->url,
                    get_string('resourcesadded', 'block_resourcerecommender'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

            $message = get_string(
                'noresourcesadded',
                'block_resourcerecommender'
            );
        }

    // Remove a resource association from a topic.
    } else if ($action === 'removeresource') {
        $topicid = required_param('topicid', PARAM_INT);
        $cmid = required_param('cmid', PARAM_INT);

        $topic = $DB->get_record('block_rr_topics', [
            'id' => $topicid,
            'courseid' => $courseid,
        ]);

        if (!$topic) {
            $message = get_string(
                'topicnotfound',
                'block_resourcerecommender'
            );
        } else if (!$DB->record_exists('block_rr_mapping', [
            'topicid' => $topicid,
            'cmid' => $cmid,
        ])) {
            $message = get_string(
                'mappingnotfound',
                'block_resourcerecommender'
            );
        } else {
            $DB->delete_records('block_rr_mapping', [
                'topicid' => $topicid,
                'cmid' => $cmid,
            ]);

            redirect(
                $PAGE->url,
                get_string('resourceremoved', 'block_resourcerecommender'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(
    get_string('managetopics', 'block_resourcerecommender')
);

if ($message !== '') {
    echo $OUTPUT->notification($message, $messagetype);
}


// Display existing topics.
echo $OUTPUT->heading(
    get_string('existingtopics', 'block_resourcerecommender'),
    3
);

$topics = $DB->get_records(
    'block_rr_topics',
    ['courseid' => $courseid],
    'name ASC'
);

if (empty($topics)) {
    echo $OUTPUT->notification(
        get_string('notopics', 'block_resourcerecommender'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    foreach ($topics as $topic) {
        $collapseid = 'topic-details-' . $topic->id;

        echo html_writer::start_div('card mb-3');

        // Visible topic title and keywords.
        echo html_writer::start_div('card-header');

        echo html_writer::tag(
            'button',
            format_string($topic->name),
            [
                'type' => 'button',
                'class' => 'btn btn-link text-start text-decoration-none p-0 fw-bold',
                'data-toggle' => 'collapse',
                'data-target' => '#' . $collapseid,
                'aria-expanded' => 'false',
                'aria-controls' => $collapseid,
            ]
        );

        if (trim((string) $topic->keywords) !== '') {
            echo html_writer::tag(
                'div',
                html_writer::tag(
                    'span',
                    get_string('topickeywords', 'block_resourcerecommender') . ': ',
                    ['class' => 'fw-bold']
                ) . s($topic->keywords),
                ['class' => 'text-muted small mt-1']
            );
        }

        echo html_writer::end_div();

        // Hidden topic management controls.
        echo html_writer::start_div('collapse', [
            'id' => $collapseid,
        ]);

        echo html_writer::start_div('card-body');

        // Edit topic form.
        echo $OUTPUT->heading(
            get_string('edittopic', 'block_resourcerecommender'),
            5
        );

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
            'value' => 'edittopic',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'topicid',
            'value' => $topic->id,
        ]);

        echo html_writer::tag(
            'label',
            get_string('topicname', 'block_resourcerecommender'),
            ['for' => 'topicname-' . $topic->id]
        );

        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'name',
            'id' => 'topicname-' . $topic->id,
            'value' => $topic->name,
            'required' => 'required',
            'maxlength' => 255,
            'class' => 'form-control mb-2',
        ]);

        echo html_writer::tag(
            'label',
            get_string('topickeywords', 'block_resourcerecommender'),
            ['for' => 'keywords-' . $topic->id]
        );

        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'keywords',
            'id' => 'keywords-' . $topic->id,
            'value' => $topic->keywords,
            'class' => 'form-control mb-2',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('savetopic', 'block_resourcerecommender'),
            'class' => 'btn btn-primary btn-sm mt-1',
        ]);

        echo html_writer::end_tag('form');

        // Delete topic form.
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'mt-2',
            'onsubmit' => 'return confirm(' .
                json_encode(get_string(
                    'confirmdeletetopic',
                    'block_resourcerecommender'
                )) . ');',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'deletetopic',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'topicid',
            'value' => $topic->id,
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('deletetopic', 'block_resourcerecommender'),
            'class' => 'btn btn-danger btn-sm',
        ]);

        echo html_writer::end_tag('form');

        // Associated resources.
        echo html_writer::tag(
            'p',
            html_writer::tag(
                'strong',
                get_string('associatedresources', 'block_resourcerecommender')
            ),
            ['class' => 'mt-3']
        );

        $mappings = $DB->get_records(
            'block_rr_mapping',
            ['topicid' => $topic->id],
            'id ASC'
        );

        $mappedcmids = [];
        $resourceitems = [];

        foreach ($mappings as $mapping) {
            $mappedcmids[] = (int) $mapping->cmid;

            if (!isset($resources[$mapping->cmid])) {
                continue;
            }

            $cm = $resources[$mapping->cmid];

            $resourcelink = html_writer::link(
                $cm->url,
                format_string($cm->name)
            );

            $removeform = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $PAGE->url->out(false),
                'class' => 'd-inline',
            ]);

            $removeform .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'sesskey',
                'value' => sesskey(),
            ]);

            $removeform .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'action',
                'value' => 'removeresource',
            ]);

            $removeform .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'topicid',
                'value' => $topic->id,
            ]);

            $removeform .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'cmid',
                'value' => $cm->id,
            ]);

            $removeform .= html_writer::tag(
                'button',
                get_string('removeresource', 'block_resourcerecommender'),
                [
                    'type' => 'submit',
                    'class' => 'btn btn-sm btn-outline-danger',
                ]
            );

            $removeform .= html_writer::end_tag('form');

            $resourceitems[] = html_writer::div(
                $resourcelink . ' ' . $removeform,
                'mb-2'
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

        // Resources not yet associated with this topic.
        $available = array_diff_key(
            $resources,
            array_flip($mappedcmids)
        );

        echo html_writer::tag('hr', '');

        echo html_writer::tag(
            'p',
            html_writer::tag(
                'strong',
                get_string('addresourcestotopic', 'block_resourcerecommender')
            )
        );

        if (empty($available)) {
            echo html_writer::tag(
                'p',
                get_string('allresourcesassigned', 'block_resourcerecommender')
            );
        } else {
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
                'value' => 'addresources',
            ]);

            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'topicid',
                'value' => $topic->id,
            ]);

            foreach ($available as $cm) {
                echo html_writer::div(
                    html_writer::checkbox(
                        'cmids[]',
                        $cm->id,
                        false,
                        format_string($cm->name)
                    ),
                    'mb-2'
                );
            }

            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('addresources', 'block_resourcerecommender'),
                'class' => 'btn btn-secondary mt-2',
            ]);

            echo html_writer::end_tag('form');
        }

        echo html_writer::end_div(); // card-body.
        echo html_writer::end_div(); // collapse.
        echo html_writer::end_div(); // card.
    }
}


// Create a new topic form.
echo $OUTPUT->heading(
    get_string('createtopic', 'block_resourcerecommender'),
    3
);

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

echo html_writer::tag(
    'label',
    get_string('topicname', 'block_resourcerecommender'),
    ['for' => 'new-topic-name']
);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'name',
    'id' => 'new-topic-name',
    'required' => 'required',
    'maxlength' => 255,
    'class' => 'form-control mb-3',
]);

echo html_writer::tag(
    'label',
    get_string('topickeywords', 'block_resourcerecommender'),
    ['for' => 'new-topic-keywords']
);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'keywords',
    'id' => 'new-topic-keywords',
    'class' => 'form-control mb-3',
    'placeholder' => 'SQL, SELECT, WHERE',
]);

echo html_writer::tag(
    'p',
    get_string('selectresources', 'block_resourcerecommender')
);

foreach ($resources as $cm) {
    echo html_writer::div(
        html_writer::checkbox(
            'cmids[]',
            $cm->id,
            false,
            format_string($cm->name)
        ),
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
