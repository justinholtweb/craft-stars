<?php

namespace justinholtweb\stars\twig;

/**
 * `craft.stars` Twig API — the namespaced root for everything this plugin
 * exposes to templates: `craft.stars.reviews.*` and `craft.stars.comments.*`.
 */
class StarsVariable
{
    private ?ReviewsVariable $reviews = null;

    private ?CommentsVariable $comments = null;

    /**
     * `craft.stars.reviews`
     */
    public function getReviews(): ReviewsVariable
    {
        return $this->reviews ??= new ReviewsVariable();
    }

    /**
     * `craft.stars.comments`
     */
    public function getComments(): CommentsVariable
    {
        return $this->comments ??= new CommentsVariable();
    }
}
