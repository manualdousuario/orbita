<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Read-only view over Laravel's `failed_jobs` table, used by the admin Queue page.
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    protected function jobName(): Attribute
    {
        return Attribute::get(function (): string {
            $payload = json_decode((string) $this->payload, true);

            $name = $payload['displayName'] ?? $payload['job'] ?? null;

            return is_string($name) && $name !== '' ? $name : '—';
        });
    }

    protected function exceptionClass(): Attribute
    {
        return Attribute::get(function (): string {
            $firstLine = trim(Str::before($this->exceptionBody, "\n"));

            $class = Str::before($firstLine, ': ');

            return preg_match('/^[\w\\\\]+$/', $class) === 1 ? $class : $firstLine;
        });
    }

    protected function exceptionMessage(): Attribute
    {
        return Attribute::get(function (): string {
            $body = $this->exceptionBody;
            $class = $this->exceptionClass;

            $message = Str::startsWith($body, $class.': ')
                ? Str::after($body, $class.': ')
                : $body;

            return trim(preg_replace('/ in [^ ]+\.php:\d+$/m', '', $message, 1) ?? $message);
        });
    }

    protected function exceptionTrace(): Attribute
    {
        return Attribute::get(function (): string {
            $exception = (string) $this->exception;

            return Str::contains($exception, 'Stack trace:')
                ? trim(Str::after($exception, 'Stack trace:'))
                : '';
        });
    }

    protected function exceptionBody(): Attribute
    {
        return Attribute::get(fn (): string => trim(Str::before((string) $this->exception, 'Stack trace:')));
    }
}
