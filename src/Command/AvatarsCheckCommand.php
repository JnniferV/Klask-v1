<?php

namespace App\Command;

use App\Service\InscriptionService;
use App\Twig\AvatarExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:avatars:check', description: 'Vérifie que chaque pseudo a bien ses 3 visuels')]
class AvatarsCheckCommand extends Command
{
    public function __construct(
        private readonly InscriptionService $inscription,
        private readonly AvatarExtension $avatars,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('csv', null, InputOption::VALUE_NONE, 'Tableau des fichiers attendus, à transmettre à l\'illustrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csv = $input->getOption('csv') ? (fopen('php://output', 'w') ?: null) : null;
        if (null !== $csv) {
            fputcsv($csv, ['pseudo', ...array_values(AvatarExtension::VARIANTS)]);
        }

        $missing = [];
        $seen = [];

        foreach ($this->inscription->allPseudos() as $pseudo) {
            $slug = AvatarExtension::slug($pseudo);

            // deux pseudos qui donneraient le même fichier : l'un écraserait l'autre en silence
            if (isset($seen[$slug])) {
                $output->writeln(sprintf('<error>Collision : « %s » et « %s » → %s</error>', $seen[$slug], $pseudo, $slug));

                return Command::FAILURE;
            }
            $seen[$slug] = $pseudo;

            $files = array_map(
                static fn (string $variant): string => $variant.$slug.'.webp',
                array_keys(AvatarExtension::VARIANTS),
            );

            if (null !== $csv) {
                fputcsv($csv, [$pseudo, ...$files]);
                continue;
            }

            foreach ($files as $file) {
                if (!is_file($this->avatars->path($file))) {
                    $missing[] = $file;
                }
            }
        }

        if (null !== $csv) {
            return Command::SUCCESS;
        }

        $expected = count($seen) * count(AvatarExtension::VARIANTS);
        $output->writeln(sprintf('%d pseudos x %d visuels = %d fichiers attendus dans public/%s/', count($seen), count(AvatarExtension::VARIANTS), $expected, AvatarExtension::DIR));

        if ([] === $missing) {
            $output->writeln('<info>Tous les fichiers sont présents.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<comment>%d manquants. Les 20 premiers :</comment>', count($missing)));
        foreach (array_slice($missing, 0, 20) as $file) {
            $output->writeln('  '.$file);
        }
        $output->writeln('');
        $output->writeln('--csv pour le tableau complet à transmettre.');

        return Command::FAILURE;
    }
}
