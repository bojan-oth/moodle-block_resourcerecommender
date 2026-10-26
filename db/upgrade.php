<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_resourcerecommender_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    $file = __DIR__ . '/install.xml';

    if ($oldversion < 2026102600) {
        $topics = new xmldb_table('block_rr_topics');

        if (!$dbman->table_exists($topics)) {
            $dbman->install_one_table_from_xmldb_file(
                $file,
                'block_rr_topics'
            );
        }

        $mapping = new xmldb_table('block_rr_mapping');

        if (!$dbman->table_exists($mapping)) {
            $dbman->install_one_table_from_xmldb_file(
                $file,
                'block_rr_mapping'
            );
        }

        upgrade_block_savepoint(
            true,
            2026102600,
            'resourcerecommender'
        );
    }

    return true;
}