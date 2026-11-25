<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes the copies of document
 * text and the tag suggestions the plugin keeps with posts.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_post_meta_by_key('_scouting_pdf_text');
delete_post_meta_by_key('_scouting_pdf_words');
delete_post_meta_by_key('_scouting_pdf_tag_suggestions');
delete_post_meta_by_key('_scouting_pdf_suggestions_waiting');
