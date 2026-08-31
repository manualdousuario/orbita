<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\FeedGeneration;

class PostObserver
{
    public function created(Post $post): void
    {
        $this->bump($post);
    }

    public function updated(Post $post): void
    {
        $this->bump($post);
    }

    public function deleted(Post $post): void
    {
        $this->bump($post);
    }

    public function restored(Post $post): void
    {
        $this->bump($post);
    }

    private function bump(Post $post): void
    {
        if ($post->version_of !== null) {
            return;
        }

        app(FeedGeneration::class)->bump();
    }
}
