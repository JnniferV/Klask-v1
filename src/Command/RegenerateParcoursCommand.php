<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\RoleSecurity;
use App\Service\ParcoursService;
use App\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:regenerate-parcours', description: 'Recalcule les parcours après correction du sens des notes questionnaire')]
class RegenerateParcoursCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserService $userService,
        private readonly ParcoursService $parcoursService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->userRepository->findByAuthorityRole(RoleSecurity::STUDENT->value) as $user) {
            if (!$this->userService->hasCompletedQuestionnaire($user)) {
                continue;
            }
            $this->parcoursService->generateForUser($user);
            ++$count;
        }

        $output->writeln(sprintf('%d parcours régénéré(s).', $count));

        return Command::SUCCESS;
    }
}
