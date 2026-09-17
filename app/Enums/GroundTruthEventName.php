<?php

namespace App\Enums;

/**
 * Canonical backend event names.
 *
 * These are deliberately vendor-neutral: no GA4- or Meta-specific concepts
 * belong here. Measurement systems are compared against these records.
 */
enum GroundTruthEventName: string
{
    case ViewItem = 'view_item';
    case AddToCart = 'add_to_cart';
    case BeginCheckout = 'begin_checkout';
    case Purchase = 'purchase';
}
