<?php

namespace App\DTOs;

class GameDto
{
    public string $id;
    public string $name;
    public string $img;
    public int $externalProviderId;
    public string $provider;

    public function __construct(string $id, string $name, string $img, int $externalProviderId, string $provider)
    {
        $this->id = $id;
        $this->name = $name;
        $this->img = $img;
        $this->externalProviderId = $externalProviderId;
        $this->provider = $provider;
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'img' => $this->img,
            'externalProviderId' => $this->externalProviderId,
            'provider' => $this->provider,
        ];
    }
}
