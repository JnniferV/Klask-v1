<?php

namespace App\Command;

use App\Repository\ActivityCategoryRepository;
use App\Repository\NotificationRepository;
use App\Service\AppParameterService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:notify-upcoming', description: 'Alertes Mercure : atelier/conférence + notifications programmées')]
class NotifyUpcomingEventCommand extends Command
{
    public function __construct(
        private readonly ActivityCategoryRepository $categoryRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly RealtimeNotifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly AppParameterService $params,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->sendCategoryAlerts($output);
        $this->sendScheduledNotifications($output);

        return Command::SUCCESS;
    }

    private function sendCategoryAlerts(OutputInterface $output): void
    {
        $now = new \DateTimeImmutable();
        $thresholds = $this->alertThresholds();

        foreach ($this->categoryRepository->findScheduledWithHour() as $category) {
            $start = $category->getBeginningHourCategory();
            if (null === $start) {
                continue;
            }

            $diffMin = (int) round(($start->getTimestamp() - $now->getTimestamp()) / 60);
            if (!in_array($diffMin, $thresholds, true)) {
                continue;
            }

            foreach ($category->getActivities() as $activity) {
                $this->notifier->publish('event-alert', [
                    'alert' => true,
                    'categoryType' => $category->getType(),
                    'activityName' => $activity->getName(),
                    'activityId' => $activity->getId(),
                    'minutesBefore' => $diffMin,
                ]);
                $output->writeln("Alerte {$diffMin}min : {$activity->getName()}");
            }
        }
    }

    private function sendScheduledNotifications(OutputInterface $output): void
    {
        foreach ($this->notificationRepository->findPendingScheduled() as $notification) {
            $this->notifier->publish($notification->getMercureTopic(), $notification->toMercurePayload());
            $notification->setSentAt(new \DateTimeImmutable());
            $output->writeln("Notif envoyée : {$notification->getTitle()}");
        }

        $this->em->flush();
    }

    /** @return int[] */
    private function alertThresholds(): array
    {
        $main = $this->params->getInt('ALERT_BEFORE_EVENT_MIN', 10);

        return $main > 5 ? [$main, 5] : [$main];
    }
}
