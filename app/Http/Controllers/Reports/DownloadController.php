<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * Download a generated report file
     */
    public function download(Request $request, $reportId): StreamedResponse|Response
    {
        // Find the report and ensure user owns it
        $report = GeneratedReport::where('id', $reportId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$report) {
            Log::warning('Report download attempted for non-existent or unauthorized report', [
                'report_id' => $reportId,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
            
            abort(404, 'Report not found');
        }

        // Check if file exists using Laravel Storage
        if (!Storage::disk('local')->exists($report->file_path)) {
            Log::error('Report file not found on disk', [
                'report_id' => $reportId,
                'file_path' => $report->file_path,
                'user_id' => auth()->id(),
            ]);
            
            abort(404, 'Report file not found');
        }

        try {
            // Get file contents
            $fileContents = Storage::disk('local')->get($report->file_path);
            
            if ($fileContents === null) {
                Log::error('Failed to read report file contents', [
                    'report_id' => $reportId,
                    'file_path' => $report->file_path,
                    'user_id' => auth()->id(),
                ]);
                
                abort(500, 'Failed to read report file');
            }

            // Log successful download
            Log::info('Report downloaded successfully', [
                'report_id' => $reportId,
                'file_name' => $report->file_name,
                'file_size' => $report->file_size,
                'user_id' => auth()->id(),
            ]);

            // Return file download response
            return response()->streamDownload(
                function () use ($fileContents) {
                    echo $fileContents;
                },
                $report->file_name,
                [
                    'Content-Type' => $report->mime_type ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $report->file_name . '"',
                    'Content-Length' => strlen($fileContents),
                ]
            );

        } catch (\Exception $e) {
            Log::error('Report download failed', [
                'report_id' => $reportId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Failed to download report');
        }
    }
}