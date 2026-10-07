<?php

namespace App\Catalog\Command;

use App\Catalog\Sync\CatalogSyncRunner;
use App\Catalog\Sync\SyncAlreadyRunning;
use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\PatheUnavailableException;
use App\Sdk\Pathe\RateLimitedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'catalog:sync', description: 'Synchronizes cities, cinemas, films and showtimes from Pathé')]
class SyncCommand extends Command
{
    public function __construct(private CatalogSyncRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('city', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'City to synchronize (repeatable). Defaults to PATHE_CITIES (empty: every city).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $stats = $this->runner->run($input->getOption('city'));
        } catch (SyncAlreadyRunning|BotBlockedException|RateLimitedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (PatheUnavailableException) {
            $io->error('Cannot read the Pathé reference data (cities, cinemas or films).');

            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            '%d cities, %d cinemas, %d films, %d showtimes saved, %d deleted, %d works linked, %d errors.',
            $stats['cities'], $stats['cinemas'], $stats['films'], $stats['showtimes'], $stats['deleted'], $stats['linked'], $stats['errors'],
        ));

        if ($stats['errors'] > 0) {
            $io->warning('Partial synchronization: see the logs.');

            return Command::FAILURE;
        }

        $io->success('Synchronization complete.');

        return Command::SUCCESS;
    }
}
