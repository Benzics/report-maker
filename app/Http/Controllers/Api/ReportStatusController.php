<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportStatusController extends Controller
{
    /**
     * Check if there are any recently completed reports for the current user
     */
    public function checkCompletion(Request $request)
    {
        $user = Auth::user();
        
        // Check for recently completed reports (within the last 5 minutes)
        $recentReport = GeneratedReport::where('user_id', $user->id)
            ->where('is_saved', true)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recentReport) {
            return response()->json([
                'completed' => true,
                'download_url' => route('reports.download', $recentReport->id),
                'report_name' => $recentReport->name,
            ]);
        }

        // Check for failed jobs (this is a simplified approach)
        // In a real implementation, you might want to store job status in the database
        $failedJobs = \DB::table('failed_jobs')
            ->where('payload', 'like', '%"userId":' . $user->id . '%')
            ->where('failed_at', '>=', now()->subMinutes(5))
            ->first();

        if ($failedJobs) {
            $payload = json_decode($failedJobs->payload, true);
            $exception = $payload['data']['commandName'] ?? 'Unknown error';
            
            return response()->json([
                'failed' => true,
                'error_message' => 'Report generation failed: ' . $exception,
            ]);
        }

        return response()->json([
            'completed' => false,
            'failed' => false,
        ]);
    }
}