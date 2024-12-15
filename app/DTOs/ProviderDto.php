<?php

namespace App\DTOs;

class ProviderDto
{
    public $slug;
    public $name;
    public $image;
    public $provider;

    public function __construct(string $slug, string $name, ?string $image, int $provider)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->image = $image;
        $this->provider = $provider;
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'image' => $this->image,
            'provider' => $this->provider,
        ];
    }
}
