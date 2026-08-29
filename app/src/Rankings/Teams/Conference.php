<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

enum Conference: string
{
    case ACC = 'ACC';
    case AmericanAthletic = 'American Athletic';
    case Big12 = 'Big 12';
    case BigSky = 'Big Sky';
    case BigTen = 'Big Ten';
    case CAA = 'CAA';
    case ConferenceUSA = 'Conference USA';
    case FBSIndependents = 'FBS Independents';
    case FCSIndependents = 'FCS Independents';
    case MEAC = 'MEAC';
    case MidAmerican = 'Mid-American';
    case MountainWest = 'Mountain West';
    case MVFC = 'MVFC';
    case NEC = 'NEC';
    case OVC = 'OVC';
    case OVCBigSouth = 'OVC-Big South';
    case Pac12 = 'Pac-12';
    case Patriot = 'Patriot';
    case SEC = 'SEC';
    case Southern = 'Southern';
    case Southland = 'Southland';
    case SunBelt = 'Sun Belt';
    case SWAC = 'SWAC';
    case UAC = 'UAC';

    public static function fromString(string $value): self
    {
        return match ($value) {
            'Big South-OVC' => self::OVCBigSouth,
            'Coastal Athletic' => self::CAA,
            default => self::from($value),
        };
    }
}
