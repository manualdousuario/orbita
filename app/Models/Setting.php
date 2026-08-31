<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One admin-editable override for a `config('orbita.*')` key. See App\Support\SettingsRegistry.
 */
class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value'];

    /**
     * Audit which config override changed to what, and who changed it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('setting');
    }
}
