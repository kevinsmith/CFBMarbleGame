<?php

declare(strict_types=1);

namespace Tests;

use ArgumentCountError;
use Attribute;
use BadFunctionCallException;
use BadMethodCallException;
use Closure;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Error;
use ErrorException;
use Generator;
use InvalidArgumentException;
use LengthException;
use LogicException;
use OutOfBoundsException;
use OutOfRangeException;
use OverflowException;
use Override;
use PDO;
use PDOStatement;
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Psr\Container\ContainerInterface;
use RangeException;
use RuntimeException;
use Throwable;
use TypeError;
use UnderflowException;
use UnexpectedValueException;
use UnhandledMatchError;
use ValueError;

use function array_filter;
use function array_map;
use function in_array;
use function is_dir;
use function scandir;
use function sort;

final class Architecture
{
    /**
     * Discover modules dynamically: every top-level dir under src/ except Kernel and Bootstrap.
     *
     * @return list<string>
     */
    private static function modules(): array
    {
        $src  = __DIR__ . '/../src'; // from tests/Architecture.php per docs/architecture/greenfield.md; adjust if moved — relative to this file, not CWD
        $dirs = array_filter(
            scandir($src) ?: [],
            static fn ($d) => is_dir("$src/$d") && ! in_array($d, ['Kernel', 'Bootstrap', '.', '..'], true),
        );
        sort($dirs); // deterministic rule order for PHPStan's result cache

        return array_map(static fn ($d) => 'App\\' . $d, $dirs);
    }

    /**
     * Vendor contracts, listed PHP value types, and listed exception bases any layer may use.
     * A new value-type package or PHP value class fails CI until added here — deliberately loud.
     * Globals are deny-by-default: no catch-all for the global namespace.
     *
     * @return list<SelectorInterface>
     */
    private static function vendorContractsAndValueTypes(): array
    {
        return [
            Selector::isInterface(), // PSR + package-own contracts + PHP interfaces (Throwable, DateTimeInterface, …)
            // PHP value types
            Selector::classname(DateTimeImmutable::class),
            Selector::classname(DateTimeZone::class),
            Selector::classname(DateInterval::class),
            Selector::classname(DatePeriod::class),
            // Exception/error bases modules may extend or throw
            Selector::classname(Throwable::class),
            Selector::classname(RuntimeException::class),
            Selector::classname(LogicException::class),
            Selector::classname(DomainException::class),
            Selector::classname(InvalidArgumentException::class),
            Selector::classname(UnexpectedValueException::class),
            Selector::classname(BadMethodCallException::class),
            Selector::classname(BadFunctionCallException::class),
            Selector::classname(OutOfBoundsException::class),
            Selector::classname(OutOfRangeException::class),
            Selector::classname(OverflowException::class),
            Selector::classname(UnderflowException::class),
            Selector::classname(LengthException::class),
            Selector::classname(RangeException::class),
            Selector::classname(ErrorException::class),
            Selector::classname(Error::class),
            Selector::classname(TypeError::class),
            Selector::classname(ValueError::class),
            Selector::classname(ArgumentCountError::class),
            Selector::classname(UnhandledMatchError::class),
            // Language utilities referenced in signatures / attributes
            Selector::classname(Closure::class),
            Selector::classname(Generator::class),
            Selector::classname(Attribute::class),
            Selector::classname(Override::class),
            // Vendor value-type allowlist — grows as adopted
            Selector::inNamespace('Brick\Money'),
            Selector::inNamespace('Ramsey\Uuid'),
        ];
    }

    /**
     * Packages Infrastructure wraps in adapters — grows as adapters are written.
     * Wired-as-is packages (Bootstrap binds the vendor class directly) never enter
     * any allowlist, so they are denied everywhere outside Bootstrap automatically.
     *
     * @return list<SelectorInterface>
     */
    private static function wrappedVendorPackages(): array
    {
        return [
            Selector::inNamespace('GuzzleHttp'),
            Selector::classname(PDO::class),
            Selector::classname(PDOStatement::class),
        ];
    }

    /**
     * Modules: only self, Kernel, and vendor — never other modules, never Bootstrap.
     *
     * @return iterable<Rule>
     */
    public function testModuleBoundaries(): iterable
    {
        foreach (self::modules() as $module) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace($module))
                ->canOnly()->dependOn()
                ->classes(
                    Selector::inNamespace($module),
                    Selector::inNamespace('App\Kernel'),
                    Selector::Not(Selector::inNamespace('App')), // vendor + PHP
                )
                ->because('modules depend only on themselves, Kernel, and vendor — see docs/architecture/README.md "Common Violations → Fixes"');
        }
    }

    /**
     * Kernel depends on nothing in App.
     */
    public function testKernelIsLeaf(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Kernel'))
            ->canOnly()->dependOn()
            ->classes(
                Selector::inNamespace('App\Kernel'),
                ...self::vendorContractsAndValueTypes(),
            )
            ->because('Kernel depends on nothing in App, and on vendor only through contracts and value types — see docs/architecture/README.md "What Goes Where"');
    }

    /**
     * Production code never references test code (Tests\ is autoload-dev only).
     */
    public function testSrcNeverReferencesTests(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace('Tests'))
            ->because('Tests\ is autoload-dev only; a src reference to it fatals in production — see docs/architecture/README.md "Inside a Module: Layers"');
    }

    /**
     * Nothing in the app depends on Bootstrap (modules rule covers modules; this covers Kernel too).
     * Tests live outside App\ and legitimately exercise Bootstrap code.
     */
    public function testBootstrapIsInvisible(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::inNamespace('App'),
                Selector::Not(Selector::inNamespace('App\Bootstrap')),
            ))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace('App\Bootstrap'))
            ->because('nothing in App depends on the composition root — see docs/architecture/README.md "What Goes Where"');
    }

    /**
     * No service-locator: modules never see the container.
     */
    public function testNoContainerInModules(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::inNamespace('App'),
                Selector::Not(Selector::inNamespace('App\Bootstrap')),
            ))
            ->shouldNot()->dependOn()
            ->classes(Selector::implements(ContainerInterface::class))
            ->because('constructor injection only; the container is invisible outside Bootstrap — see docs/architecture/README.md "Common Violations → Fixes"');
    }

    /**
     * Every module class lives under Domain | Application | Presentation | Infrastructure.
     *
     * @return iterable<Rule>
     */
    public function testModuleClassesLiveInLayers(): iterable
    {
        foreach (self::modules() as $m) {
            yield PHPat::rule()
                ->classes(Selector::AllOf(
                    Selector::inNamespace($m),
                    Selector::Not(Selector::inNamespace("$m\\Domain")),
                    Selector::Not(Selector::inNamespace("$m\\Application")),
                    Selector::Not(Selector::inNamespace("$m\\Presentation")),
                    Selector::Not(Selector::inNamespace("$m\\Infrastructure")),
                ))
                ->shouldNot()->exist()
                ->because('every module class lives in Domain, Application, Presentation, or Infrastructure — see docs/architecture/README.md "Inside a Module: Layers"');
        }
    }

    /**
     * Inside each module: Presentation → Application, Domain; Application → Domain;
     * Domain → nothing module-local; Infrastructure invisible except to Bootstrap.
     *
     * @return iterable<Rule>
     */
    public function testLayerBoundaries(): iterable
    {
        foreach (self::modules() as $m) {
            // Vendor is deny-by-default everywhere outside Bootstrap (see the two allowlists above)
            $shared      = [Selector::inNamespace('App\Kernel'), ...self::vendorContractsAndValueTypes()];
            $infraShared = [...$shared, ...self::wrappedVendorPackages()];

            yield PHPat::rule()
                ->classes(Selector::inNamespace("$m\Domain"))
                ->canOnly()->dependOn()
                ->classes(Selector::inNamespace("$m\Domain"), ...$shared)
                ->because('Domain is pure: itself, Kernel, and vendor value types only — see docs/architecture/README.md "Inside a Module: Layers"');

            yield PHPat::rule()
                ->classes(Selector::inNamespace("$m\Application"))
                ->canOnly()->dependOn()
                ->classes(Selector::inNamespace("$m\Application"), Selector::inNamespace("$m\Domain"), ...$shared)
                ->because('Application depends inward on Domain only — see docs/architecture/README.md "Inside a Module: Layers"');

            yield PHPat::rule()
                ->classes(Selector::inNamespace("$m\Presentation"))
                ->canOnly()->dependOn()
                ->classes(Selector::inNamespace("$m\Presentation"), Selector::inNamespace("$m\Application"), Selector::inNamespace("$m\Domain"), ...$shared)
                ->because('Presentation depends inward on Application and Domain — see docs/architecture/README.md "Inside a Module: Layers"');

            yield PHPat::rule()
                ->classes(Selector::inNamespace("$m\Infrastructure"))
                ->canOnly()->dependOn()
                ->classes(Selector::inNamespace("$m\Infrastructure"), Selector::inNamespace("$m\Application"), Selector::inNamespace("$m\Domain"), ...$infraShared)
                ->because('Infrastructure depends inward on Application and Domain — Presentation is a sibling adapter, see docs/architecture/README.md "Inside a Module: Layers"');

            yield PHPat::rule()
                ->classes(Selector::AllOf(
                    Selector::inNamespace($m),
                    Selector::Not(Selector::inNamespace("$m\Infrastructure")),
                ))
                ->shouldNot()->dependOn()
                ->classes(Selector::inNamespace("$m\Infrastructure"))
                ->because('Infrastructure is invisible inside the module: only container bindings reference it — see docs/architecture/README.md "Inside a Module: Layers"');

            yield PHPat::rule()
                ->classes(Selector::AllOf(
                    Selector::inNamespace($m),
                    Selector::Not(Selector::inNamespace("$m\Infrastructure")),
                ))
                ->shouldNot()->dependOn()
                ->classes(Selector::classname(PDO::class), Selector::classname(PDOStatement::class))
                ->because('PDO lives in Infrastructure repositories only; the connection is constructed in Bootstrap — see docs/architecture/README.md "Inside a Module: Layers"');
        }
    }

    /**
     * Kernel shape: concrete classes must be final.
     * Interfaces/enums/traits excluded — Selector::isClass() is not in PHPat 0.12.
     */
    public function testKernelClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::inNamespace('App\Kernel'),
                Selector::NoneOf(Selector::isInterface(), Selector::isEnum(), Selector::isTrait()),
            ))
            ->should()->beFinal()
            ->because('Kernel holds contracts and value objects only — see docs/architecture/README.md principle 6');
    }

    /**
     * Kernel shape: concrete classes must be readonly.
     */
    public function testKernelClassesAreReadonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::inNamespace('App\Kernel'),
                Selector::NoneOf(Selector::isInterface(), Selector::isEnum(), Selector::isTrait()),
            ))
            ->should()->beReadonly()
            ->because('Kernel holds contracts and value objects only — see docs/architecture/README.md principle 6');
    }
}
