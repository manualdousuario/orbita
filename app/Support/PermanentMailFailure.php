<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Classifies SMTP failures as permanent or not.
 */
final class PermanentMailFailure
{
    public const REASON_SUPPRESSED = 'suppressed';

    public const REASON_MAILBOX_UNAVAILABLE = 'mailbox_unavailable';

    public const REASON_INVALID_RECIPIENT = 'invalid_recipient';

    public const REASON_REJECTED = 'rejected';

    /** Anything matching this is a configuration problem on our side, never the recipient's. */
    private const CONFIG_FAILURE = '/\b(53[0458])\b|authentication\s+(failed|required|credentials)|5\.7\.0\b|SMTP\s+AUTH|must\s+issue\s+a\s+STARTTLS|sender\s+(address\s+)?(rejected|denied)|not\s+authori[sz]ed\s+to\s+send|domain\s+of\s+sender|\b(spf|dkim|dmarc)\b/i';

    private const SUPPRESSED = '/is\s+suppressed\s+for\s+sender|suppress(ed|ion)\s+list|on\s+the\s+suppression\s+list|suppressed\s+address/i';

    private const HARD_BOUNCE = '/user\s+unknown|unknown\s+user|no\s+such\s+user|mailbox\s+(unavailable|not\s+found|does\s+not\s+exist)|recipient\s+(address\s+)?rejected|unrouteable\s+address|invalid\s+recipient|recipient\s+not\s+found/i';

    private const PERMANENT_CODES = [
        550 => self::REASON_MAILBOX_UNAVAILABLE,
        551 => self::REASON_INVALID_RECIPIENT,
        553 => self::REASON_INVALID_RECIPIENT,
        554 => self::REASON_REJECTED,
    ];

    private const MAX_RESPONSE_LENGTH = 1000;

    /** SMTP replies that are part of a normal conversation and say nothing about delivery. */
    private const ROUTINE_CODES = [220, 221, 235, 250, 251, 252, 334, 354];

    /** How RoundRobinTransport labels each provider's conversation inside the aggregate debug. */
    private const TRANSPORT_SECTION = '/^Transport "/m';

    private function __construct(
        public readonly string $reason,
        public readonly ?string $email,
        public readonly string $response,
    ) {}

    /**
     * Classifies a transport exception as a permanent failure, or null.
     */
    public static function match(?Throwable $e): ?self
    {
        if ($e === null) {
            return null;
        }

        $messages = [];

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof TransportExceptionInterface && ($debug = $current->getDebug()) !== '') {
                if (preg_match(self::TRANSPORT_SECTION, $debug) === 1) {
                    return self::classifyAggregate($debug);
                }

                $messages[] = self::significantLines($debug);
            }

            $messages[] = $current->getMessage();
        }

        foreach ($messages as $message) {
            if (preg_match(self::CONFIG_FAILURE, $message) === 1) {
                return null;
            }
        }

        foreach ($messages as $message) {
            $failure = self::classify($message);

            if ($failure !== null) {
                return $failure;
            }
        }

        return null;
    }

    private static function classifyAggregate(string $debug): ?self
    {
        $sections = preg_split(self::TRANSPORT_SECTION, $debug, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($sections === []) {
            return null;
        }

        $first = null;

        foreach ($sections as $section) {
            $lines = self::significantLines($section);

            if ($lines === '' || preg_match(self::CONFIG_FAILURE, $lines) === 1) {
                return null;
            }

            $failure = self::classify($lines);

            if ($failure === null) {
                return null;
            }

            $first ??= $failure;
        }

        return $first;
    }

    private static function significantLines(string $debug): string
    {
        $kept = [];

        foreach (preg_split('/\R/', $debug) ?: [] as $line) {
            if (preg_match('/^\[[^\]]*\]\s*<\s*(.+)$/', $line, $matches) === 1) {
                $response = trim($matches[1]);

                if (preg_match('/^(\d{3})/', $response, $code) === 1
                    && in_array((int) $code[1], self::ROUTINE_CODES, true)) {
                    continue;
                }

                $kept[] = $response;

                continue;
            }

            if (preg_match('/^\[[^\]]*\]\s*>\s*(RCPT TO:.+)$/i', $line, $matches) === 1) {
                $kept[] = trim($matches[1]);
            }
        }

        return implode("\n", $kept);
    }

    public static function isPermanent(?Throwable $e): bool
    {
        return self::match($e) !== null;
    }

    private static function classify(string $message): ?self
    {
        $response = self::response($message);

        $code = self::extractCode($message, $response);
        $codeReason = $code !== null ? (self::PERMANENT_CODES[$code] ?? null) : null;

        if (preg_match(self::SUPPRESSED, $response) === 1) {
            return new self(self::REASON_SUPPRESSED, self::extractEmail($response), $response);
        }

        if (preg_match(self::HARD_BOUNCE, $response) === 1) {
            return new self($codeReason ?? self::REASON_MAILBOX_UNAVAILABLE, self::extractEmail($response), $response);
        }

        if ($codeReason !== null) {
            return new self($codeReason, self::extractEmail($response), $response);
        }

        return null;
    }

    private static function response(string $message): string
    {
        if (preg_match('/with message "(.*)"/s', $message, $matches) === 1) {
            $message = $matches[1];
        }

        $message = trim($message);

        return mb_substr($message, 0, self::MAX_RESPONSE_LENGTH);
    }

    private static function extractCode(string $message, string $response): ?int
    {
        if (preg_match('/but got code "(\d{3})"/', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^\s*(\d{3})[ -]/m', $response, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function extractEmail(string $response): ?string
    {
        $pattern = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

        if (preg_match_all($pattern, $response, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        foreach ($matches[0] as [$address, $offset]) {
            $before = mb_strtolower(substr($response, max(0, $offset - 48), min($offset, 48)));

            if (preg_match('/\b(from|sender|remetente)\b[^@]*$/', $before) === 1) {
                continue;
            }

            return mb_strtolower(trim($address, '<>'));
        }

        return null;
    }
}
