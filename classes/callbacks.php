<?php

namespace plagiarism_urkund;

use core\hook\output\before_standard_top_of_body_html_generation;
use html_writer;
use moodle_url;

class callbacks {
    /**
     * Add resubmit button to overall grading pages
     *
     * @param before_standard_top_of_body_html_generation $hook
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void
    {
        global $PAGE, $OUTPUT, $DB;
        if (!$PAGE->context instanceof \context_module) {
            return;
        }

        if (!has_capability('plagiarism/urkund:resubmitallfiles', $PAGE->context)) {
            return;
        }

        if ($PAGE->url->compare(new moodle_url('/mod/quiz/report.php'), URL_MATCH_BASE)) {
            $module = 'quiz';
        } else if ($PAGE->url->compare(new moodle_url('/mod/assign/view.php'), URL_MATCH_BASE)) {
            $module = 'assign';
        } else {
            return;
        }

        if (empty(get_config('plagiarism_urkund', 'enable_mod_' . $module))) {
            return;
        }

        $useurkund = $DB->get_field('plagiarism_urkund_config', 'value',
            ['cm' => $PAGE->context->instanceid, 'name' => 'use_urkund']);

        if (empty($useurkund)) {
            return;
        }

        $url = new moodle_url('/plagiarism/urkund/reset.php',  ['cmid' => $PAGE->context->instanceid, 'resetall' => 1]);
        $button = $OUTPUT->single_button($url, get_string('resubmittourkund', 'plagiarism_urkund'));
        $PAGE->set_button($PAGE->button . html_writer::div($button), 'urkundresubmit');
    }
}