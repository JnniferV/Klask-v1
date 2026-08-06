<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Group;
use App\Entity\User;

interface InscriptionService
{
    // Identité libre
    public function generateUniquePseudo(string $preferred = ''): string;

    public function findGroupByCode(string $code): ?Group;

    // si motif de refus d'inscription dans ce groupe
    public function refusalReason(?Group $group, Establishment $establishment, string $level): ?string;

    public function registerStudent(User $student): User;
}
