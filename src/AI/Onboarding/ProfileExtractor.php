<?php
/**
 * Turns a pasted CV into a structured profile.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;

/**
 * The differentiator, and the honest version of it.
 *
 * The product idea was a LinkedIn URL. That does not work: LinkedIn serves a
 * login wall to server-side requests and its terms forbid scraping, so a URL
 * field would fail for almost everyone and fail confusingly. Pasted text works
 * for every source — a LinkedIn profile the owner copies, a CV, an About page,
 * a bio — and asks the owner for consent by construction, because they are the
 * one doing the pasting.
 *
 * This deliberately uses the structured-output path rather than tools: there is
 * one answer of a known shape and nothing to do to the site yet. The extracted
 * profile is a proposal the owner edits before anything is written.
 */
class ProfileExtractor
{
    const MIN_LENGTH = 80;
    const MAX_LENGTH = 20000;

    /** @var AIProviderInterface */
    private $provider;

    /** @var PromptManager */
    private $prompts;

    /**
     * @param AIProviderInterface $provider Provider that will answer.
     * @param PromptManager       $prompts  Prompt loader.
     */
    public function __construct(AIProviderInterface $provider, PromptManager $prompts)
    {
        $this->provider = $provider;
        $this->prompts  = $prompts;
    }

    /**
     * @param string $text  Pasted CV or profile text.
     * @param string $model Model identifier.
     *
     * @throws ProviderException When the provider fails.
     * @throws \InvalidArgumentException When there is too little text to work with.
     *
     * @return array The extracted profile, plus the brand fields it implies.
     */
    public function extract($text, $model)
    {
        $text = trim((string) $text);

        if (strlen($text) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(
                __('Paste a bit more — a few lines of a CV or profile is the minimum to work from.', 'swishfolio-core')
            );
        }

        // Capped before it reaches a provider: a pasted novel would cost the
        // owner real money on their own key and tell us nothing more.
        if (strlen($text) > self::MAX_LENGTH) {
            $text = substr($text, 0, self::MAX_LENGTH);
        }

        $response = $this->provider->generate(
            new AIRequest(
                $this->prompts->system('agent'),
                $this->prompts->task('extract-profile', ['profile' => $text]),
                ProfileSchema::build(),
                $model,
                4096
            )
        );

        $profile = $response->getDecoded();

        return [
            'profile' => $profile,
            'brand'   => $this->brandFrom($profile),
            'usage'   => [
                'inputTokens'  => $response->getInputTokens(),
                'outputTokens' => $response->getOutputTokens(),
            ],
        ];
    }

    /**
     * The brand fields the profile implies, shaped for Settings::saveBrand.
     *
     * Returned separately rather than saved here: the owner sees the extraction
     * and corrects it first. An import that silently rewrote their brand
     * settings would be the kind of helpfulness nobody asked for.
     *
     * @param array $profile Extracted profile.
     *
     * @return array
     */
    private function brandFrom(array $profile)
    {
        $brand = [];

        if (! empty($profile['name'])) {
            $brand['siteName'] = (string) $profile['name'];
        }

        if (! empty($profile['headline'])) {
            $brand['siteDescription'] = (string) $profile['headline'];
        }

        foreach (['designStyle', 'copywritingTone'] as $field) {
            if (! empty($profile[$field])) {
                $brand[$field] = (string) $profile[$field];
            }
        }

        return $brand;
    }
}
