<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace plagiarism_urkund\app\submission;

// @codeCoverageIgnoreStart
defined('MOODLE_INTERNAL') || die();
// @codeCoverageIgnoreEnd

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

require_once(__DIR__ . '/../../../../lib.php');

/**
 * Tests the display of stored Urkund errors beside submissions.
 *
 * @package    plagiarism_urkund
 * @copyright  2026 Moxis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \plagiarism_plugin_urkund
 * @covers     ::plagiarism_get_errorcode_from_jsonresponse
 */
#[CoversClass(\plagiarism_plugin_urkund::class)]
#[CoversFunction('plagiarism_get_errorcode_from_jsonresponse')]
final class error_display_test extends \advanced_testcase {
    /** Test that a teacher can still reset a file when the stored response is unreadable. */
    public function test_teacher_can_reset_after_bad_response(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'plagiarism_urkund');
        set_config('api', 'https://example.invalid', 'plagiarism_urkund');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        $DB->insert_record('plagiarism_urkund_config', ['cm' => $forum->cmid, 'name' => 'use_urkund', 'value' => '1']);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($forum->cmid)->id,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'submission.txt',
        ], 'A sample submission');
        $brokenresponse = '<html><body>Bad Gateway';
        $recordid = $DB->insert_record('plagiarism_urkund_files', [
            'cm' => $forum->cmid,
            'userid' => $student->id,
            'identifier' => $file->get_contenthash(),
            'filename' => $file->get_filename(),
            'statuscode' => URKUND_STATUSCODE_INVALID_RESPONSE,
            'errorresponse' => $brokenresponse,
        ]);

        $plugin = new \plagiarism_plugin_urkund();
        $link = ['cmid' => $forum->cmid, 'userid' => $student->id, 'file' => $file];
        $output = $plugin->get_links($link);
        $this->assertStringContainsString(get_string('unknownwarning', 'plagiarism_urkund'), $output);
        $this->assertStringContainsString('/plagiarism/urkund/reset.php?', $output);
        $this->assertStringContainsString('pf=' . $recordid, $output);
        $this->assertStringNotContainsString('Bad Gateway', $output);
        $this->assertSame($brokenresponse, $DB->get_field('plagiarism_urkund_files', 'errorresponse', ['id' => $recordid]));

        $response = '[{"Status":{"ErrorCode":101}}]';
        $DB->set_field('plagiarism_urkund_files', 'errorresponse', $response, ['id' => $recordid]);
        $this->assertStringContainsString(get_string('errorcode_101', 'plagiarism_urkund'), $plugin->get_links($link));
        $this->assertSame(101, plagiarism_get_errorcode_from_jsonresponse($response));
        $this->assertNull(plagiarism_get_errorcode_from_jsonresponse('{"error":"Bad Gateway"}'));
    }
}
