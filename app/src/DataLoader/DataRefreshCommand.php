<?php

declare(strict_types=1);

namespace App\DataLoader;

use GuzzleHttp\Client;
use PDO;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function ctype_digit;
use function date;
use function is_string;

#[AsCommand(
    name: 'data:refresh',
    description: 'Retrieve and store fresh teams and games data from the CollegeFootballData.com API',
)]
final class DataRefreshCommand extends Command
{
    public function __construct(
        private readonly Client $cfbdApiClient,
        private readonly PDO $pdo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'year',
            null,
            InputOption::VALUE_REQUIRED,
            'Season year to refresh',
            (string) date('Y'),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consoleLogger = new ConsoleLogger($output, [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
        ]);

        $retriever = new GamesDataRefresher(
            $this->cfbdApiClient,
            $this->pdo,
            $consoleLogger,
        );

        $year = $input->getOption('year');

        if (! is_string($year) || ! ctype_digit($year)) {
            $output->writeln('<error>Error: Year must be a positive integer.</error>');

            return Command::FAILURE;
        }

        try {
            $retriever->pullAndStoreFreshData((int) $year);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
