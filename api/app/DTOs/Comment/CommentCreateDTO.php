<?php

namespace App\DTOs\Comment;

class CommentCreateDTO
{
    public function __construct(
        public string $body,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(body: $validated['body']);
    }

    public function toArray(): array
    {
        return ['body' => $this->body];
    }
}
