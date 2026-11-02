<?php

namespace App\Reset;

enum PointsMode: string
{
    case Cumulative = 'cumulative';
    case Fixed = 'fixed';
}
