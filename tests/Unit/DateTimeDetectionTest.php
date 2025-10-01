<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DateTimeDetectionTest extends TestCase
{
    /**
     * Test the looksLikeDate function logic with various datetime formats
     */
    public function test_looks_like_date_detects_datetime_formats()
    {
        // Test cases for datetime formats that should be detected
        $datetimeFormats = [
            '01/09/2025 07:23',    // MM/DD/YYYY HH:MM
            '01-09-2025 07:23',    // MM-DD-YYYY HH:MM
            '2025-01-09 07:23',    // YYYY-MM-DD HH:MM
            '09/01/2025 07:23',    // DD/MM/YYYY HH:MM
            '09-01-2025 07:23',    // DD-MM-YYYY HH:MM
            '1/9/2025 7:23',       // M/D/YYYY H:MM
            '1-9-2025 7:23',       // M-D-YYYY H:MM
            '2025-1-9 7:23',       // YYYY-M-D H:MM
            '9/1/2025 7:23',       // D/M/YYYY H:MM
            '9-1-2025 7:23',       // D-M-YYYY H:MM
            '01/09/25 07:23',      // MM/DD/YY HH:MM
            '01-09-25 07:23',      // MM-DD-YY HH:MM
            '25-01-09 07:23',      // YY-MM-DD HH:MM
            '09/01/25 07:23',      // DD/MM/YY HH:MM
            '09-01-25 07:23',      // DD-MM-YY HH:MM
        ];

        foreach ($datetimeFormats as $format) {
            $this->assertTrue(
                $this->looksLikeDate($format),
                "Failed to detect datetime format: {$format}"
            );
        }
    }

    /**
     * Test the looksLikeDate function logic with date formats (should still work)
     */
    public function test_looks_like_date_detects_date_formats()
    {
        // Test cases for date formats that should still be detected
        $dateFormats = [
            '01/09/2025',    // MM/DD/YYYY
            '01-09-2025',    // MM-DD-YYYY
            '2025-01-09',    // YYYY-MM-DD
            '09/01/2025',    // DD/MM/YYYY
            '09-01-2025',    // DD-MM-YYYY
            '1/9/2025',      // M/D/YYYY
            '1-9-2025',      // M-D-YYYY
            '2025-1-9',      // YYYY-M-D
            '9/1/2025',      // D/M/YYYY
            '9-1-2025',      // D-M-YYYY
            '01/09/25',      // MM/DD/YY
            '01-09-25',      // MM-DD-YY
            '25-01-09',      // YY-MM-DD
            '09/01/25',      // DD/MM/YY
            '09-01-25',      // DD-MM-YY
        ];

        foreach ($dateFormats as $format) {
            $this->assertTrue(
                $this->looksLikeDate($format),
                "Failed to detect date format: {$format}"
            );
        }
    }

    /**
     * Test the looksLikeDate function with non-date strings (should return false)
     */
    public function test_looks_like_date_rejects_non_date_strings()
    {
        // Test cases that should NOT be detected as dates
        $nonDateFormats = [
            'Product Name',
            '12345',
            'ABC123',
            '01/09/2025 07:23:45',  // Has seconds (not supported)
            '01/09/2025 7:23 PM',   // Has AM/PM (not supported yet)
            '2025-01-09T07:23:00',  // ISO format with T
            'January 9, 2025',      // Text month
            'Jan 9, 2025',          // Abbreviated text month
            '09 Jan 2025',          // Different text format
            '',                     // Empty string
            '   ',                  // Whitespace only
        ];

        foreach ($nonDateFormats as $format) {
            $this->assertFalse(
                $this->looksLikeDate($format),
                "Incorrectly detected as date: {$format}"
            );
        }
    }

    /**
     * Copy of the looksLikeDate method from Generate.php for testing
     * This allows us to test the logic without loading the full Livewire component
     */
    private function looksLikeDate(string $value): bool
    {
        // Common date formats
        $dateFormats = [
            'm/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y',
            'm/d/y', 'm-d-y', 'd/m/y', 'd-m-y',
            'n/j/Y', 'n-j-Y', 'j/n/Y', 'j-n-Y',
        ];

        // Common datetime formats
        $datetimeFormats = [
            'm/d/Y H:i', 'm-d-Y H:i', 'Y-m-d H:i', 'd/m/Y H:i', 'd-m-Y H:i',
            'm/d/y H:i', 'm-d-y H:i', 'd/m/y H:i', 'd-m-y H:i',
            'n/j/Y H:i', 'n-j-Y H:i', 'j/n/Y H:i', 'j-n-Y H:i',
            'm/d/Y G:i', 'm-d-Y G:i', 'Y-m-d G:i', 'd/m/Y G:i', 'd-m-Y G:i',
            'm/d/y G:i', 'm-d-y G:i', 'd/m/y G:i', 'd-m-y G:i',
            'n/j/Y G:i', 'n-j-Y G:i', 'j/n/Y G:i', 'j-n-Y G:i',
        ];

        // Try date formats first
        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // Try datetime formats
        foreach ($datetimeFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // Also check for common date and datetime patterns with regex
        $datePatterns = [
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/',  // MM/DD/YYYY, MM-DD-YYYY
            '/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/',    // YYYY/MM/DD, YYYY-MM-DD
        ];

        $datetimePatterns = [
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\s+\d{1,2}:\d{2}$/',  // MM/DD/YYYY HH:MM, MM-DD-YYYY HH:MM
            '/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\s+\d{1,2}:\d{2}$/',    // YYYY/MM/DD HH:MM, YYYY-MM-DD HH:MM
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        foreach ($datetimePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
