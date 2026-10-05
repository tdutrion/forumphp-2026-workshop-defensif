<?php

namespace App\Catalog\Command;

use App\Catalog\Entity\Film;
use App\Catalog\WorkLinker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'work:link', description: 'Links the work of a film to a Wikidata item by hand, or stops the attempts (--none)')]
class WorkLinkCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private WorkLinker $workLinker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('film', InputArgument::REQUIRED, 'Slug of a film, e.g. cars')
            ->addArgument('wikidata-id', InputArgument::OPTIONAL, 'Wikidata item of the film, e.g. Q182153')
            ->addOption('none', null, InputOption::VALUE_NONE, 'The film is not in Wikidata: stop looking for it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $film = $this->em->find(Film::class, (string) $input->getArgument('film'));
        if (null === $film) {
            $io->error('Unknown film.');

            return Command::FAILURE;
        }

        if ($input->getOption('none')) {
            $this->workLinker->markNoMatch($film->getWork());
            $io->success('The work will not be looked up any more.');

            return Command::SUCCESS;
        }

        $id = (string) $input->getArgument('wikidata-id');
        if (1 !== preg_match('/^Q\d+$/', $id) || !$this->workLinker->linkManually($film->getWork(), $id)) {
            $io->error('Not linked: give the Q id of a film (Wikidata may also be unreachable).');

            return Command::FAILURE;
        }
        $io->success(sprintf('The work of %s is linked to %s.', $film->getTitle(), $id));

        return Command::SUCCESS;
    }
}
