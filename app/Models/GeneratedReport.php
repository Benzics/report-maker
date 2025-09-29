<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'name',
        'description',
        'selected_columns',
        'filter_column',
        'filter_value',
        'filter_column2',
        'filter_value2',
        'filter_column3',
        'filter_value3',
        'filter_value_start',
        'filter_value_end',
        'filter_value2_start',
        'filter_value2_end',
        'filter_value3_start',
        'filter_value3_end',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'is_saved',
    ];

    protected function casts(): array
    {
        return [
            'selected_columns' => 'array',
            'is_saved' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
