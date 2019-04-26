<?php

declare(strict_types=1);

namespace Kreait\Firebase\Messaging;

class RegistrationTokens implements \JsonSerializable
{
    /**
     * @var RegistrationToken[]
     */
    private $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromArray($data): self
    {
        if (\is_string($data)) {
            return new self([(string) RegistrationToken::fromValue($value)]);
        }
        $data = array_map(function ($value) {
            return (string) RegistrationToken::fromValue($value);
        }, $data);
        return new self($data);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
