<?php

namespace App\Command;

use App\Repository\EventRepository;
use App\Service\EventResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// cycle d'un event : s'ouvre à l'heure programmée (beginningHourEvent),se ferme à endHourEvent

#[AsCommand(name: 'app:event-reset', description: 'Réinitialise les données élèves d\'un event à sa fermeture')]
class EventResetCommand extends Command
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventResetService $resetService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event', null, InputOption::VALUE_OPTIONAL, 'ID de l\'event à réinitialiser (si non précisé : tous les events terminés)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventId = $input->getOption('event');

        $events = $eventId !== null
            ? array_filter([$this->eventRepository->find((int) $eventId)])
            : $this->eventRepository->findTerminatedNotReset();

        if (empty($events)) {
            $output->writeln('Aucun event à réinitialiser.');

            return Command::SUCCESS;
        }

        foreach ($events as $event) {
            $output->writeln("Réinitialisation de l'event : {$event->getName()}");
            foreach ($this->resetService->reset($event) as $label => $count) {
                $output->writeln("  ✓ {$label} ({$count})");
            }
        }

        $this->em->flush();
        $output->writeln('Réinitialisation terminée.');

        return Command::SUCCESS;
    }
}
