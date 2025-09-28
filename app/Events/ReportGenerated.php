<?php

namespace App\Events;

use App\Models\GeneratedReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $generatedReport;

    public $sessionId;

    /**
     * Create a new event instance.
     */
    public function __construct(GeneratedReport $generatedReport, string $sessionId)
    {
        $this->generatedReport = $generatedReport;
        $this->sessionId = $sessionId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('report-generation.'.$this->sessionId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'success',
            'message' => 'Report generated successfully!',
            'report' => [
                'id' => $this->generatedReport->id,
                'name' => $this->generatedReport->name,
                'document_id' => $this->generatedReport->document_id,
                'download_url' => route('reports.download', $this->generatedReport->id),
                'saved_url' => route('reports.saved', $this->generatedReport->document_id),
            ],
        ];
    }
}
