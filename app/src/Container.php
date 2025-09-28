<?php

declare(strict_types=1);

namespace App;

use App\DataLoader\DataRefreshCommand;
use App\HttpServer\Routes;
use App\Rankings\CachedTeamRepository;
use App\Rankings\GameRepository;
use App\Rankings\MarbleOrchestrator;
use App\Rankings\SqliteGameRepository;
use App\Rankings\SqliteTeamRepository;
use App\Rankings\TeamRepository;
use DI\ContainerBuilder;
use FastRoute\Dispatcher;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use PDO;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function FastRoute\simpleDispatcher;
use function file_exists;
use function file_get_contents;
use function getenv;
use function trim;

final readonly class Container
{
    public static function init(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions(self::definitions());

        return $builder->build();
    }

    /** @return array<string, mixed> */
    private static function definitions(): array
    {
        return [
            Dispatcher::class => static function (ContainerInterface $c) {
                return simpleDispatcher($c->get(Routes::class));
            },
            Logger::class => static function () {
                $serverData                = $_SERVER;
                $serverData['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

                return new Logger('app')
                    ->pushHandler(new StreamHandler(
                        'php://stdout',
                        getenv('LOG_LEVEL') ?: Level::Notice,
                    ))
                    ->pushProcessor(new WebProcessor($serverData));
            },
            PDO::class => static function () {
                return new PDO('sqlite:' . getenv('DB_PATH'));
            },
            TeamRepository::class => static function (ContainerInterface $c) {
                return new CachedTeamRepository(
                    new SqliteTeamRepository(
                        $c->get(PDO::class),
                    ),
                );
            },
            GameRepository::class => static function (ContainerInterface $c) {
                return new SqliteGameRepository(
                    $c->get(PDO::class),
                    $c->get(TeamRepository::class),
                );
            },
            DataRefreshCommand::class => static function (ContainerInterface $c) {
                $apiKey = self::getSecret('CFBD_API_KEY');

                return new DataRefreshCommand(
                    new Client([
                        'base_uri' => 'https://api.collegefootballdata.com',
                        RequestOptions::HEADERS => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Accept' => 'application/json',
                        ],
                    ]),
                    $c->get(PDO::class),
                );
            },
            MarbleOrchestrator::class => static function (ContainerInterface $c) {
                return new MarbleOrchestrator($c->get(Logger::class));
            },
        ];
    }

    private static function getSecret(string $name): string
    {
        $secretValue = getenv($name);

        if ($secretValue) {
            return $secretValue;
        }

        $dockerSecretPath = '/run/secrets/' . $name;

        if (! file_exists($dockerSecretPath)) {
            throw new RuntimeException('Docker secret file not found: ' . $dockerSecretPath);
        }

        $secretValue = file_get_contents($dockerSecretPath);

        if ($secretValue === false) {
            throw new RuntimeException('Failed to read secret: ' . $dockerSecretPath);
        }

        return trim($secretValue);
    }
}
