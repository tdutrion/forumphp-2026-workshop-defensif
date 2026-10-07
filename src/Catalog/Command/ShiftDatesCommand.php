<?php

declare(strict_types=1);

namespace App\Catalog\Command;

use App\Catalog\CatalogCalendar;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'catalog:shift-dates', description: 'Shifts every showtime so that the first day of the catalog becomes today')]
class ShiftDatesCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private CatalogCalendar $calendar,
        private ClockInterface $clock,
        #[Autowire('%app.chains%')]
        private array $chains,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $firstDay = $this->connection->fetchOne('SELECT MIN(local_date) FROM showtime');
        if (null === $firstDay || false === $firstDay) {
            $io->warning('No showtime to shift.');

            return Command::SUCCESS;
        }

        // The catalog only holds Pathé for now: "today" is taken in its time zone.
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($this->chains['pathe']['timezone']));
        $days = (int) (new \DateTimeImmutable($firstDay))->diff(new \DateTimeImmutable($today->format('Y-m-d')))->format('%r%a');
        if (0 === $days) {
            $io->success('The catalog already starts today.');

            return Command::SUCCESS;
        }

        $this->connection->executeStatement(
            'UPDATE showtime
             SET starts_at = DATE_ADD(starts_at, INTERVAL :days DAY),
                 ends_at = DATE_ADD(ends_at, INTERVAL :days DAY),
                 reservable_until = DATE_ADD(reservable_until, INTERVAL :days DAY),
                 local_date = DATE_ADD(local_date, INTERVAL :days DAY)',
            ['days' => $days],
        );

        $this->calendar->refresh();
        $io->success(sprintf('Showtimes shifted by %d day(s).', $days));

        return Command::SUCCESS;
    }
}
