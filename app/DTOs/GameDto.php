<?php

namespace App\DTOs;

class GameDto
{
    public string $id;
    public string $name;
    public string $img;
    public int $providerId;

    public function __construct(string $id, string $name, string $img, int $providerId)
    {
        $this->id = $id;
        $this->name = $name;
        $this->img = $img;
        $this->providerId = $providerId;
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
            'providerId' => $this->providerId,
        ];
    }
}
