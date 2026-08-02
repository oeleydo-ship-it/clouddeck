<?php

namespace App\Cloud\Data;

final readonly class CreateServerData
{
    public function __construct(public string $name, public string $region, public string $size, public string $image, public array $sshKeyIds = []) {}
}
