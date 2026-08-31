<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deletes duplicate notifications, keeping the earliest (NULL-safe match on nullable columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('notifications')
            ->select('user_id', 'type', 'title', 'message', 'link', 'created_at')
            ->selectRaw('MIN(id) AS keep_id')
            ->selectRaw('MAX(is_read) AS any_read')
            ->selectRaw('MIN(read_at) AS first_read_at')
            ->groupBy('user_id', 'type', 'title', 'message', 'link', 'created_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicates = DB::table('notifications')
                ->where('user_id', $group->user_id)
                ->where('type', $group->type)
                ->where('title', $group->title)
                ->where('created_at', $group->created_at)
                ->where('id', '<>', $group->keep_id);

            // message is TEXT and both columns are nullable: NULL-safe matching.
            $group->message === null
                ? $duplicates->whereNull('message')
                : $duplicates->where('message', $group->message);
            $group->link === null
                ? $duplicates->whereNull('link')
                : $duplicates->where('link', $group->link);

            $duplicates->delete();

            if ($group->any_read) {
                DB::table('notifications')
                    ->where('id', $group->keep_id)
                    ->update(['is_read' => true, 'read_at' => $group->first_read_at]);
            }
        }
    }

    public function down(): void
    {
        // No-op: deleted duplicate rows cannot be reconstructed.
    }
};
