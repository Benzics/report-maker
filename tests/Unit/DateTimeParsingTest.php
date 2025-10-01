<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DateTimeParsingTest extends TestCase
{
    /**
     * Test parsing of ISO datetime formats from HTML5 datetime-local inputs
     */
    public function test_parses_iso_datetime_formats()
    {
        $isoFormats = [
            '2025-01-09T07:23',      // HTML5 datetime-local format
            '2025-01-09T07:23:00',   // With seconds
            '2025-01-09T07:23:00.000', // With milliseconds
        ];

        foreach ($isoFormats as $format) {
            $this->assertTrue(
                $this->parseDateValue($format) !== null,
                "Failed to parse ISO datetime format: {$format}"
            );
        }
    }

    /**
     * Test parsing of custom datetime formats from Excel files
     */
    public function test_parses_custom_datetime_formats()
    {
        $customFormats = [
            '01/09/2025 07:23',      // MM/DD/YYYY HH:MM
            '09/01/2025 07:23',      // DD/MM/YYYY HH:MM
            '2025-01-09 07:23',      // YYYY-MM-DD HH:MM
            '01-09-2025 07:23',      // MM-DD-YYYY HH:MM
            '09-01-2025 07:23',      // DD-MM-YYYY HH:MM
        ];

        foreach ($customFormats as $format) {
            $this->assertTrue(
                $this->parseDateValue($format) !== null,
                "Failed to parse custom datetime format: {$format}"
            );
        }
    }

    /**
     * Test datetime matching between different formats
     */
    public function test_datetime_matching_between_formats()
    {
        // These should all represent the same datetime
        $isoFormat = '2025-01-09T07:23';
        $customFormat = '01/09/2025 07:23';
        
        $isoDate = $this->parseDateValue($isoFormat);
        $customDate = $this->parseDateValue($customFormat);
        
        $this->assertNotNull($isoDate, "Failed to parse ISO format: {$isoFormat}");
        $this->assertNotNull($customDate, "Failed to parse custom format: {$customFormat}");
        
        // They should match when compared
        $this->assertTrue(
            $this->datesMatch($isoDate, $customDate),
            "ISO format {$isoFormat} should match custom format {$customFormat}"
        );
    }

    /**
     * Test that datetime matching preserves time components
     */
    public function test_datetime_matching_preserves_time()
    {
        $date1 = $this->parseDateValue('2025-01-09T07:23');
        $date2 = $this->parseDateValue('2025-01-09T07:24'); // Different minute
        
        $this->assertNotNull($date1);
        $this->assertNotNull($date2);
        
        // They should NOT match because times are different
        $this->assertFalse(
            $this->datesMatch($date1, $date2),
            "Different times should not match"
        );
    }

    /**
     * Copy of the parseDateValue method from GenerateReportJob.php for testing
     */
    private function parseDateValue(string $value): ?\DateTime
    {
        // Clean the value first
        $value = trim($value);

        $dateFormats = [
            'm/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y',
            'm/d/y', 'm-d-y', 'd/m/y', 'd-m-y',
            'n/j/Y', 'n-j-Y', 'j/n/Y', 'j-n-Y',
        ];

        $datetimeFormats = [
            'm/d/Y H:i', 'm-d-Y H:i', 'Y-m-d H:i', 'd/m/Y H:i', 'd-m-Y H:i',
            'm/d/y H:i', 'm-d-y H:i', 'd/m/y H:i', 'd-m-y H:i',
            'n/j/Y H:i', 'n-j-Y H:i', 'j/n/Y H:i', 'j-n-Y H:i',
            'm/d/Y G:i', 'm-d-Y G:i', 'Y-m-d G:i', 'd/m/Y G:i', 'd-m-Y G:i',
            'm/d/y G:i', 'm-d-y G:i', 'd/m/y G:i', 'd-m-y G:i',
            'n/j/Y G:i', 'n-j-Y G:i', 'j/n/Y G:i', 'j-n-Y G:i',
            'm/d/Y H:i:s', 'm-d-Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd-m-Y H:i:s',
            'm/d/y H:i:s', 'm-d-y H:i:s', 'd/m/y H:i:s', 'd-m-y H:i:s',
            'n/j/Y H:i:s', 'n-j-Y H:i:s', 'j/n/Y H:i:s', 'j-n-Y H:i:s',
            // ISO datetime formats (from HTML5 datetime-local inputs)
            'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s.u\Z',
        ];

        // Try date formats first
        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        // Try datetime formats
        foreach ($datetimeFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        // Try to parse as a general date/datetime string
        try {
            $date = new \DateTime($value);
            return $date;
        } catch (\Exception $e) {
            // If all else fails, return null
            return null;
        }
    }

    /**
     * Copy of the datesMatch method from GenerateReportJob.php for testing
     */
    private function datesMatch(\DateTime $date1, \DateTime $date2): bool
    {
        // Check if both dates have time components (not just date)
        $date1HasTime = $date1->format('H:i:s') !== '00:00:00';
        $date2HasTime = $date2->format('H:i:s') !== '00:00:00';
        
        // If either date has time, compare the full datetime
        if ($date1HasTime || $date2HasTime) {
            return $date1->format('Y-m-d H:i:s') === $date2->format('Y-m-d H:i:s');
        }
        
        // If both are date-only, compare just the date
        return $date1->format('Y-m-d') === $date2->format('Y-m-d');
    }
}
