<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\GroqProvider;

class SpyGroqProvider extends GroqProvider
{
    use SpiesOnPost;
}
