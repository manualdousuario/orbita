<?php

namespace App\Enums;

/**
 * Outcome of a social authentication attempt.
 */
enum SocialAuthStatus: string
{
    /** Authenticated and ready to browse. */
    case LoggedIn = 'logged_in';

    /** Authenticated, but the account is pending activation (verification email on its way). */
    case NeedsVerification = 'needs_verification';

    /** The provider gave us no email; send the visitor to the complete-registration screen. */
    case NeedsEmail = 'needs_email';

    /** A confirmation link was emailed to an existing account. Deliberately NOT authenticated. */
    case LinkPending = 'link_pending';

    /** Refused. `message` carries the pt-BR reason. */
    case Error = 'error';
}
