<?php

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // exécute les tâches manquées
            ->processOnlyLastMissedRun(true) // n'exécute que la dernière tâche manquée

            // notifications programmées + alertes atelier/conférence imminents
            ->add(RecurringMessage::every('1 minute', new RunCommandMessage('app:notify-upcoming')))
        ;
    }
}
