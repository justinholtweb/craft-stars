<?php

namespace justinholtweb\stars\elements\base;

use craft\elements\db\ElementQuery;

/**
 * Base query for Stars' moderated elements. Holds the params shared by every
 * moderated type (entry attachment, submission IP) and maps the four custom
 * statuses onto Craft's element status mechanism.
 */
abstract class ModeratedQuery extends ElementQuery
{
    public ?int $entryId = null;
    public ?string $ipAddress = null;

    /**
     * The fully-qualified status column for this type
     * (e.g. `stars_reviews.reviewStatus`).
     */
    abstract protected function statusColumn(): string;

    public function entryId(?int $value): static
    {
        $this->entryId = $value;
        return $this;
    }

    public function ipAddress(?string $value): static
    {
        $this->ipAddress = $value;
        return $this;
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            'pending', 'approved', 'rejected', 'spam' => [$this->statusColumn() => $status],
            default => parent::statusCondition($status),
        };
    }
}
