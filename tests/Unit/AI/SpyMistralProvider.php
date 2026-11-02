<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\MistralProvider;

class SpyMistralProvider extends MistralProvider
{
    use SpiesOnPost;
}
