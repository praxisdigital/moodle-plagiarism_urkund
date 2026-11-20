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

/**
 * Extracts an Urkund submission error code without treating arbitrary responses as API data.
 *
 * @package    plagiarism_urkund
 * @copyright  2026 Moxis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class error_response_parser {
    /**
     * Read the error code from the last result in a JSON API response.
     *
     * @param mixed $response API response or transport failure value.
     * @return int|null The error code, if present.
     */
    public static function from_json(mixed $response): ?int {
        if (!is_string($response)) {
            return null;
        }

        $results = json_decode($response);
        if (!is_array($results) || $results === []) {
            return null;
        }

        $last = $results[array_key_last($results)];
        return self::normalise_code($last->Status->ErrorCode ?? null);
    }

    /**
     * Read a stored error code, including from legacy XML records.
     *
     * @param string $response Stored API response.
     * @return int|null The error code, if present.
     */
    public static function from_stored_response(string $response): ?int {
        // Legacy XML can start with a UTF-8 byte order mark or whitespace.
        if (!preg_match('/\A(?:\xEF\xBB\xBF)?\s*</', $response)) {
            return self::from_json($response);
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($response, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || !isset($xml->SubmissionData->Status->ErrorCode)) {
                return null;
            }

            return self::normalise_code((string)$xml->SubmissionData->Status->ErrorCode);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Accept only positive integer error codes, not values PHP would coerce into codes.
     *
     * @param mixed $code Value from the response.
     * @return int|null The error code, if valid.
     */
    private static function normalise_code(mixed $code): ?int {
        if (!is_int($code) && !is_string($code)) {
            return null;
        }

        $code = filter_var($code, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $code === false ? null : $code;
    }
}
