<?php

declare(strict_types=1);

namespace Tests\Rankings\Teams;

use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Rankings\TeamGameFactory;
use ValueError;

#[CoversClass(Team::class)]
#[CoversClass(TeamId::class)]
#[CoversClass(Conference::class)]
#[CoversClass(Subdivision::class)]
final class TeamTest extends TestCase
{
    public function testNewTeamStartsWithZeroMarbles(): void
    {
        self::assertSame(0, TeamGameFactory::team(1, 'Texas')->getMarbles());
    }

    public function testReceiveMarblesAddsToTheCurrentTotal(): void
    {
        $team = TeamGameFactory::team(1, 'Texas');

        $team->receiveMarbles(5);
        $team->receiveMarbles(3);

        self::assertSame(8, $team->getMarbles());
    }

    public function testReceiveMarblesRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamGameFactory::team(1, 'Texas')->receiveMarbles(-1);
    }

    public function testGiveUpMarblesSubtractsFromTheCurrentTotal(): void
    {
        $team = TeamGameFactory::team(1, 'Texas');
        $team->receiveMarbles(10);

        $team->giveUpMarbles(4);

        self::assertSame(6, $team->getMarbles());
    }

    public function testGiveUpMarblesRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamGameFactory::team(1, 'Texas')->giveUpMarbles(-1);
    }

    public function testGiveUpMarblesCanDriveTheTotalBelowZero(): void
    {
        $team = TeamGameFactory::team(1, 'Texas');
        $team->receiveMarbles(5);

        $team->giveUpMarbles(10);

        self::assertSame(-5, $team->getMarbles());
    }

    public function testSetMarbleRankAndGetMarbleRankRoundTrip(): void
    {
        $team = TeamGameFactory::team(1, 'Texas');

        $team->setMarbleRank(7);

        self::assertSame(7, $team->getMarbleRank());
    }

    public function testSetMarbleRankRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rank must be at least 1');

        TeamGameFactory::team(1, 'Texas')->setMarbleRank(0);
    }

    public function testSetMarbleRankRejectsNegativeRanks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamGameFactory::team(1, 'Texas')->setMarbleRank(-1);
    }

    public function testGetMarbleRankBeforeSetMarbleRankThrows(): void
    {
        $team = TeamGameFactory::team(1, 'Texas');

        $this->expectException(Error::class);

        self::assertSame(0, $team->getMarbleRank());
    }

    public function testConstructorExposesTheIdentityProperties(): void
    {
        $team = TeamGameFactory::team(42, 'Texas', Subdivision::FBS, Conference::SEC);

        self::assertSame(42, $team->id->id);
        self::assertSame('Texas', $team->teamName);
        self::assertSame(Subdivision::FBS, $team->subdivision);
        self::assertSame(Conference::SEC, $team->conference);
    }

    public function testTeamIdFromDatabaseExposesTheId(): void
    {
        self::assertSame(42, TeamId::fromDatabase(42)->id);
    }

    public function testConferenceFromStringMapsBigSouthOvcToOvcBigSouth(): void
    {
        self::assertSame(Conference::OVCBigSouth, Conference::fromString('Big South-OVC'));
    }

    public function testConferenceFromStringMapsCoastalAthleticToCaa(): void
    {
        self::assertSame(Conference::CAA, Conference::fromString('Coastal Athletic'));
    }

    public function testConferenceFromStringMapsExactNames(): void
    {
        self::assertSame(Conference::SEC, Conference::fromString('SEC'));
    }

    public function testConferenceFromStringRejectsUnknownNames(): void
    {
        $this->expectException(ValueError::class);

        Conference::fromString('No Such Conference');
    }

    public function testSubdivisionFromStringIsCaseInsensitive(): void
    {
        self::assertSame(Subdivision::FBS, Subdivision::fromString('fbs'));
    }

    public function testSubdivisionFromStringTrimsWhitespace(): void
    {
        self::assertSame(Subdivision::FCS, Subdivision::fromString(' FCS '));
    }

    public function testSubdivisionFromStringRejectsUnknownValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Subdivision::fromString('NAIA');
    }
}
