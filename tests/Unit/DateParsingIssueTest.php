<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DateParsingIssueTest extends TestCase
{
    /**
     * Test the specific issue reported by the user
     * Date format '01/09/2025 07:23' should be parsed as September 1st, not January 9th
     */
    public function test_date_parsing_issue_user_scenario()
    {
        // User's date format: '01/09/2025 07:23'
        $userDate = '01/09/2025 07:23';
        
        // Parse using current logic
        $parsedDate = $this->parseDateValue($userDate);
        
        $this->assertNotNull($parsedDate, "Failed to parse user's date format: {$userDate}");
        
        // This should be September 1st, 2025 (not January 9th)
        $this->assertEquals('2025-09-01', $parsedDate->format('Y-m-d'), 
            "Date should be parsed as September 1st, 2025, not January 9th");
        $this->assertEquals('07:23', $parsedDate->format('H:i'), 
            "Time should be preserved correctly");
    }

    /**
     * Test date range filtering with the user's scenario
     */
    public function test_date_range_filtering_user_scenario()
    {
        // User wants data from September 1-15, 2025
        $startDate = '01/09/2025';  // September 1st
        $endDate = '15/09/2025';    // September 15th
        
        // Test data: '01/09/2025 07:23' should be included
        $testDate = '01/09/2025 07:23';
        
        $startParsed = $this->parseDateValue($startDate);
        $endParsed = $this->parseDateValue($endDate);
        $testParsed = $this->parseDateValue($testDate);
        
        $this->assertNotNull($startParsed, "Failed to parse start date");
        $this->assertNotNull($endParsed, "Failed to parse end date");
        $this->assertNotNull($testParsed, "Failed to parse test date");
        
        // Debug output
        $this->assertEquals('2025-09-01', $startParsed->format('Y-m-d'), "Start date should be September 1st");
        $this->assertEquals('2025-09-15', $endParsed->format('Y-m-d'), "End date should be September 15th");
        $this->assertEquals('2025-09-01', $testParsed->format('Y-m-d'), "Test date should be September 1st");
        
        // Test date should be within the range
        // For date-only comparisons, we need to compare just the date part
        $testDateOnly = $testParsed->format('Y-m-d');
        $startDateOnly = $startParsed->format('Y-m-d');
        $endDateOnly = $endParsed->format('Y-m-d');
        
        $this->assertTrue(
            $testDateOnly >= $startDateOnly && $testDateOnly <= $endDateOnly,
            "Test date should be within September 1-15, 2025 range. Test: {$testDateOnly}, Start: {$startDateOnly}, End: {$endDateOnly}"
        );
    }

    /**
     * Test that different date formats are handled correctly
     */
    public function test_various_date_formats()
    {
        $testCases = [
            // DD/MM/YYYY format (European) - now prioritized
            '01/09/2025 07:23' => '2025-09-01 07:23',
            '15/09/2025 14:30' => '2025-09-15 14:30',
            
            // MM/DD/YYYY format (US) - still works but lower priority
            '09/01/2025 07:23' => '2025-01-09 07:23',  // This is January 9th in US format
            '09/15/2025 14:30' => '2025-09-15 14:30',  // This is September 15th in US format
            
            // YYYY-MM-DD format (ISO)
            '2025-09-01 07:23' => '2025-09-01 07:23',
            '2025-09-15 14:30' => '2025-09-15 14:30',
        ];

        foreach ($testCases as $input => $expected) {
            $parsed = $this->parseDateValue($input);
            $this->assertNotNull($parsed, "Failed to parse: {$input}");
            $this->assertEquals($expected, $parsed->format('Y-m-d H:i'), 
                "Incorrect parsing for: {$input}");
        }
    }

    /**
     * Copy of the parseDateValue method from GenerateReportJob.php for testing
     */
    private function parseDateValue(string $value): ?\DateTime
    {
        // Clean the value first
        $value = trim($value);

        // Prioritize DD/MM/YYYY formats first (European format)
        $dateFormats = [
            'd/m/Y', 'd-m-Y', 'j/n/Y', 'j-n-Y',  // DD/MM/YYYY formats first
            'm/d/Y', 'm-d-Y', 'Y-m-d',            // MM/DD/YYYY and YYYY-MM-DD
            'd/m/y', 'd-m-y', 'j/n/y', 'j-n-y',  // DD/MM/YY formats
            'm/d/y', 'm-d-y',                     // MM/DD/YY formats
        ];

        $datetimeFormats = [
            // DD/MM/YYYY datetime formats first (European format)
            'd/m/Y H:i', 'd-m-Y H:i', 'j/n/Y H:i', 'j/n-Y H:i',
            'd/m/Y G:i', 'd-m-Y G:i', 'j/n/Y G:i', 'j/n-Y G:i',
            'd/m/Y H:i:s', 'd-m-Y H:i:s', 'j/n/Y H:i:s', 'j/n-Y H:i:s',
            // MM/DD/YYYY datetime formats
            'm/d/Y H:i', 'm-d-Y H:i', 'Y-m-d H:i',
            'm/d/Y G:i', 'm-d-Y G:i', 'Y-m-d G:i',
            'm/d/Y H:i:s', 'm-d-Y H:i:s', 'Y-m-d H:i:s',
            // 2-digit year formats
            'd/m/y H:i', 'd-m-y H:i', 'j/n/y H:i', 'j/n-y H:i',
            'm/d/y H:i', 'm-d-y H:i',
            'd/m/y G:i', 'd-m-y G:i', 'j/n/y G:i', 'j/n-y G:i',
            'm/d/y G:i', 'm-d-y G:i',
            'd/m/y H:i:s', 'd-m-y H:i:s', 'j/n/y H:i:s', 'j/n-y H:i:s',
            'm/d/y H:i:s', 'm-d-y H:i:s',
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
}
