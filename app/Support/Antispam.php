<?php

namespace App\Support;

/** Shared antispam helpers used by PostService and CommentService. */
class Antispam
{
    /** Renders a remaining-wait duration as pt-BR text ("45 segundos", "3 minutos"). */
    public static function humanizeWait(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return $seconds.($seconds === 1 ? ' segundo' : ' segundos');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.($minutes === 1 ? ' minuto' : ' minutos');
    }
}
