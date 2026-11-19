<?php
/**
 * The one switch that lets an eval spend money and touch the network.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

/**
 * Live mode has to be impossible to trigger by accident, which rules out the
 * usual "is the variable set" test: a stray SWISHFOLIO_AI_EVAL_LIVE=0 or
 * =false in someone's shell would then turn it on. So the opt-in is an exact
 * string, and the credentials have to be there as well — two independent things
 * that both have to be deliberate.
 *
 * The decision is pure and takes its environment as an argument, so the guard
 * itself is testable in recorded mode without setting anything.
 */
final class LiveMode
{
    const ENABLE_VAR   = 'SWISHFOLIO_AI_EVAL_LIVE';
    const ENABLE_VALUE = '1';
    const KEY_VAR      = 'SWISHFOLIO_AI_EVAL_API_KEY';
    const BASE_URL_VAR = 'SWISHFOLIO_AI_EVAL_BASE_URL';
    const MODEL_VAR    = 'SWISHFOLIO_AI_EVAL_MODEL';

    const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    const DEFAULT_MODEL    = 'gpt-4o-mini';

    /**
     * @param array $env Environment, as a name => value map.
     *
     * @return bool
     */
    public static function enabledFor(array $env)
    {
        $flag = isset($env[self::ENABLE_VAR]) ? (string) $env[self::ENABLE_VAR] : '';

        if ($flag !== self::ENABLE_VALUE) {
            return false;
        }

        $key = isset($env[self::KEY_VAR]) ? trim((string) $env[self::KEY_VAR]) : '';

        return $key !== '';
    }

    /**
     * @param array $env Environment, as a name => value map.
     *
     * @return string Why live mode is off, or an empty string when it is on.
     */
    public static function reasonDisabledFor(array $env)
    {
        if (self::enabledFor($env)) {
            return '';
        }

        $flag = isset($env[self::ENABLE_VAR]) ? (string) $env[self::ENABLE_VAR] : '';

        if ($flag !== self::ENABLE_VALUE) {
            return sprintf(
                'Live provider evals are opt-in: set %s=%s to run them. They call a real provider and cost real money.',
                self::ENABLE_VAR,
                self::ENABLE_VALUE
            );
        }

        return sprintf('%s is set but %s is empty, so there is nothing to authenticate with.', self::ENABLE_VAR, self::KEY_VAR);
    }

    /**
     * @return array The real process environment, narrowed to the names that matter.
     */
    public static function environment()
    {
        $env = [];

        foreach ([self::ENABLE_VAR, self::KEY_VAR, self::BASE_URL_VAR, self::MODEL_VAR] as $name) {
            $value = getenv($name);

            if ($value !== false) {
                $env[$name] = $value;
            }
        }

        return $env;
    }

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return self::enabledFor(self::environment());
    }

    /**
     * @return string
     */
    public static function reasonDisabled()
    {
        return self::reasonDisabledFor(self::environment());
    }

    /**
     * @param array $env Environment map.
     *
     * @return array Credentials for a provider.
     */
    public static function credentialsFor(array $env)
    {
        return [
            'api_key'  => isset($env[self::KEY_VAR]) ? (string) $env[self::KEY_VAR] : '',
            'base_url' => isset($env[self::BASE_URL_VAR]) && $env[self::BASE_URL_VAR] !== ''
                ? (string) $env[self::BASE_URL_VAR]
                : self::DEFAULT_BASE_URL,
        ];
    }

    /**
     * @param array $env Environment map.
     *
     * @return string
     */
    public static function modelFor(array $env)
    {
        return isset($env[self::MODEL_VAR]) && $env[self::MODEL_VAR] !== ''
            ? (string) $env[self::MODEL_VAR]
            : self::DEFAULT_MODEL;
    }
}
