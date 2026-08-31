<?php

namespace App\Support;

use Hashids\Hashids;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates short, opaque, persisted hashids for posts and comments.
 */
class HashId
{
    public static function encode(int $id): string
    {
        $salt = bin2hex(random_bytes(10));

        return (new Hashids($salt, 10))->encode($id);
    }

    /**
     * Returns a unique hashid not yet used in the table.
     */
    public static function unique(string $table, string $column = 'hashid'): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = self::encode(random_int(1, 100_000_000));

            if (! DB::table($table)->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException("Could not generate a free {$table}.{$column} in 5 attempts.");
    }
}
