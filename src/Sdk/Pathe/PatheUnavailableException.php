<?php

namespace App\Sdk\Pathe;

/**
 * A one-off failure: the network, an error status, or an answer that is not what Pathé usually answers.
 * The next call may well work, so a caller that has many of them can skip this one and go on.
 */
final class PatheUnavailableException extends PatheApiException
{
}
