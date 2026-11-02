<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\GeminiProvider;

class SpyGeminiProvider extends GeminiProvider
{
    use SpiesOnPost;
}
