<?php

namespace justinholtweb\stars\events;

use craft\elements\Entry;
use yii\base\Event;

/**
 * Fired by SchemaService before an entry's JSON-LD is encoded.
 *
 * Change `$schema` to adjust what is printed (a per-section `@type`, a `sku`, a `brand`), or set it
 * to null to print nothing for this entry.
 */
class DefineSchemaEvent extends Event
{
    public Entry $entry;

    /**
     * The JSON-LD object about to be printed, or null for none.
     */
    public ?array $schema = null;

    /**
     * The options passed to `schemaOrg()` (`type`, `limit`), or [] for automatic injection.
     */
    public array $options = [];
}
