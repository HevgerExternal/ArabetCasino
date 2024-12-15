<?php

namespace App\DTOs;

class ProviderDto
{
    public $slug;
    public $name;
    public $image;
    public $provider;
    public $type;

    public function __construct(string $slug, string $name, ?string $image, int $provider, string $type)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->image = $image;
        $this->provider = $provider;
        $this->type = $type;
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'image' => $this->image,
            'provider' => $this->provider,
            'type' => $this->type,
        ];
    }
}
