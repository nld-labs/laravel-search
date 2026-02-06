<?php

declare(strict_types=1);

namespace NLD\Search;

enum SearchStrategy
{
    case EXACT;
    case IN_WORDS;
    case START_OF_WORDS;
    case START_OF_STRING;
}
