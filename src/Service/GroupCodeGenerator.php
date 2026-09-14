<?php

namespace App\Service;

use App\Repository\GroupRepository;

final readonly class GroupCodeGenerator
{
    public function __construct(private GroupRepository $groupRepository)
    {
    }

    public function generate(): string
    {
        do {
            $code = sprintf('GRP%04d', \random_int(0, 9999));
        } while (null !== $this->groupRepository->findByCode($code));

        return $code;
    }
}
