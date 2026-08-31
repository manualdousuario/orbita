<?php

declare(strict_types=1);

namespace App\Pipeline\Image;

/**
 * Runs sequential image pipeline stages until one sets $ctx->result.
 */
final class ImagePipeline
{
    /** @var array<int, callable(ImageUploadContext): ImageUploadContext> */
    private array $stages = [];

    /**
     * @param  callable(ImageUploadContext): ImageUploadContext  $stage
     */
    public function pipe(callable $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    public function process(ImageUploadContext $ctx): ImageUploadContext
    {
        foreach ($this->stages as $stage) {
            $ctx = $stage($ctx);
        }

        return $ctx;
    }
}
