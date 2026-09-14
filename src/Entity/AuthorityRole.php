<?php

namespace App\Entity;

use App\Repository\AuthorityRoleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorityRoleRepository::class)]
class AuthorityRole
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'authorityRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private Authority $authority;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'authorityRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private Role $role;

    public function __construct(Authority $authority, Role $role)
    {
        $this->authority = $authority;
        $this->role = $role;
    }

    public function getAuthority(): Authority
    {
        return $this->authority;
    }

    public function getRole(): Role
    {
        return $this->role;
    }
}
