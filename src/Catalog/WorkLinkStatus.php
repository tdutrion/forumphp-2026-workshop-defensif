<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * How a work got its identity: not linked yet, found in Wikidata, merged by fingerprint with the
 * same film of another chain, linked by hand, or declared without match (no more attempts).
 */
enum WorkLinkStatus: string
{
    case Unlinked = 'unlinked';
    case Wikidata = 'wikidata';
    case Fingerprint = 'fingerprint';
    case Manual = 'manual';
    case NoMatch = 'no_match';
}
