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

/**
 * Tests parsing Urkund submission errors.
 *
 * @package    plagiarism_urkund
 * @copyright  2026 Moxis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \plagiarism_urkund\app\submission\error_response_parser
 */
#[CoversClass(error_response_parser::class)]
final class error_response_parser_test extends \basic_testcase {
    /** Test that only the last result of a JSON result list is used. */
    public function test_json_error_codes(): void {
        $response = '[{"Status":{"ErrorCode":101}},{"Status":{"ErrorCode":"5000"}}]';
        $this->assertSame(5000, error_response_parser::from_json($response));
        $this->assertSame(5000, error_response_parser::from_stored_response($response));
        $this->assertSame(101, error_response_parser::from_json('[{"Status":{"ErrorCode":101}}]'));
        $this->assertNull(error_response_parser::from_json('[{"Status":{"ErrorCode":101}},{}]'));
    }

    /** Test transport failures, malformed bodies and unexpected JSON shapes. */
    public function test_invalid_json_responses(): void {
        $responses = [
            'transport failure' => false,
            'null response' => null,
            'empty body' => '',
            'timeout message' => 'Gateway Timeout',
            'malformed JSON' => '[{"Status":',
            'JSON null' => 'null',
            'scalar JSON' => '42',
            'object instead of list' => '{"Status":{"ErrorCode":101}}',
            'empty list' => '[]',
            'non-object result' => '[false]',
            'missing status' => '[{}]',
            'missing error code' => '[{"Status":{}}]',
            'array error code' => '[{"Status":{"ErrorCode":[101]}}]',
            'nonnumeric error code' => '[{"Status":{"ErrorCode":"101oops"}}]',
            'zero error code' => '[{"Status":{"ErrorCode":0}}]',
        ];

        foreach ($responses as $description => $response) {
            $this->assertNull(error_response_parser::from_json($response), $description);
            if (is_string($response)) {
                $this->assertNull(error_response_parser::from_stored_response($response), $description);
            }
        }
    }

    /** Test that well-formed legacy responses still display a code. */
    public function test_legacy_xml(): void {
        $response = "\xEF\xBB\xBF" . '<?xml version="1.0"?>'
            . '<Result><SubmissionData><Status><ErrorCode>101</ErrorCode></Status></SubmissionData></Result>';

        $this->assertSame(101, error_response_parser::from_stored_response($response));
        $this->assertNull(error_response_parser::from_json($response));
    }

    /** Test that invalid XML is ignored without changing libxml's error mode. */
    public function test_invalid_xml(): void {
        $previous = libxml_use_internal_errors(false);
        try {
            $this->assertNull(error_response_parser::from_stored_response('<html><body>Bad Gateway'));
            $this->assertNull(error_response_parser::from_stored_response('<Result><SubmissionData>'));
            $this->assertNull(error_response_parser::from_stored_response('<Result/>'));
            $this->assertNull(error_response_parser::from_stored_response('<Result><SubmissionData/></Result>'));
            $this->assertNull(libxml_get_last_error() ?: null);
            $this->assertFalse(libxml_use_internal_errors());

            libxml_use_internal_errors(true);
            $this->assertNull(error_response_parser::from_stored_response('<Result>'));
            $this->assertTrue(libxml_use_internal_errors());
            $this->assertSame([], libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
