<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DateColumnDetectionTest extends TestCase
{
    /**
     * Test that the date column detection works correctly
     */
    public function test_date_column_detection()
    {
        $testData = [
            ['Date', 'Description', 'Amount'],
            ['01/09/2025 07:23', 'Transaction 1', '100.00'],
            ['02/09/2025 08:15', 'Transaction 2', '200.00'],
            ['05/09/2025 09:30', 'Transaction 3', '150.00'],
            ['Description', 'Amount', 'Date'], // Non-date column
            ['Product A', '100.00', '01/09/2025 07:23'],
        ];

        // Test column 0 (should be detected as date column)
        $this->assertTrue(
            $this->isColumnDateType($testData, 0),
            'Column 0 should be detected as date column'
        );

        // Test column 1 (should NOT be detected as date column)
        $this->assertFalse(
            $this->isColumnDateType($testData, 1),
            'Column 1 should NOT be detected as date column'
        );

        // Test column 2 (should NOT be detected as date column)
        $this->assertFalse(
            $this->isColumnDateType($testData, 2),
            'Column 2 should NOT be detected as date column'
        );
    }

    /**
     * Test date value detection
     */
    public function test_date_value_detection()
    {
        $dateValues = [
            '01/09/2025 07:23',
            '02/09/2025 08:15',
            '05/09/2025 09:30',
            '15/09/2025 12:00',
        ];

        $nonDateValues = [
            'Transaction 1',
            'Product A',
            '100.00',
            'Description',
        ];

        foreach ($dateValues as $value) {
            $this->assertTrue(
                $this->isDateValue($value),
                "Should detect '{$value}' as date"
            );
        }

        foreach ($nonDateValues as $value) {
            $this->assertFalse(
                $this->isDateValue($value),
                "Should NOT detect '{$value}' as date"
            );
        }
    }

    /**
     * Copy of the isColumnDateType method from GenerateReportJob.php for testing
     */
    private function isColumnDateType(array $data, int $columnIndex): bool
    {
        if (empty($data) || $columnIndex < 0 || $columnIndex >= count($data[0])) {
            return false;
        }

        $sampleCount = 0;
        $dateCount = 0;
        $maxSamples = min(10, count($data) - 1); // Sample up to 10 rows, excluding header

        // Sample the first few rows to determine if column contains dates
        for ($i = 1; $i < count($data) && $sampleCount < $maxSamples; $i++) {
            $cellValue = $data[$i][$columnIndex] ?? '';
            
            if (!empty($cellValue)) {
                $sampleCount++;
                
                // Check if this value looks like a date
                if ($this->isDateValue($cellValue)) {
                    $dateCount++;
                }
            }
        }

        // Consider it a date column if more than 50% of sampled values are dates
        return $sampleCount > 0 && ($dateCount / $sampleCount) > 0.5;
    }

    /**
     * Copy of the isDateValue method from GenerateReportJob.php for testing
     */
    private function isDateValue(string $value): bool
    {
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
            '/^\d{4}-\d{1,2}-\d{1,2}T\d{1,2}:\d{2}$/',               // YYYY-MM-DDTHH:MM (ISO datetime)
            '/^\d{4}-\d{1,2}-\d{1,2}T\d{1,2}:\d{2}:\d{2}$/',         // YYYY-MM-DDTHH:MM:SS (ISO datetime)
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
