<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\OpenRouterProvider;

class SpyOpenRouterProvider extends OpenRouterProvider
{
    use SpiesOnPost;
}
