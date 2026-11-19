<?php
/**
 * Everything a validation stage needs to judge one AI response.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Blocks\BlockRegistry;

class ValidationContext
{
    /** @var array clientId => ['name' => string, 'attributes' => array, 'parentClientId' => string|null] */
    private $blockTree;

    /** @var string */
    private $selectionRootClientId;

    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var int */
    private $maxActions;

    /**
     * @param array         $blockTree             Blocks the editor submitted.
     * @param string        $selectionRootClientId The block the user selected.
     * @param BlockRegistry $registry              Capability registry.
     * @param TokenResolver $tokens                Live design tokens.
     * @param int           $maxActions            Action count cap.
     */
    public function __construct(array $blockTree, $selectionRootClientId, BlockRegistry $registry, TokenResolver $tokens, $maxActions = 25)
    {
        $this->blockTree             = $blockTree;
        $this->selectionRootClientId = $selectionRootClientId;
        $this->registry              = $registry;
        $this->tokens                = $tokens;
        $this->maxActions            = (int) $maxActions;
    }

    public function getBlockTree()
    {
        return $this->blockTree;
    }

    public function getSelectionRootClientId()
    {
        return $this->selectionRootClientId;
    }

    public function getRegistry()
    {
        return $this->registry;
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function getMaxActions()
    {
        return $this->maxActions;
    }

    /**
     * @param string $clientId Block to look up.
     *
     * @return array|null
     */
    public function getBlock($clientId)
    {
        return isset($this->blockTree[$clientId]) ? $this->blockTree[$clientId] : null;
    }

    /**
     * @param string $clientId Candidate descendant.
     *
     * @return bool Whether the block is the selection root or beneath it.
     */
    public function isWithinSelection($clientId)
    {
        $seen = [];

        while ($clientId !== null && ! isset($seen[$clientId])) {
            if ($clientId === $this->selectionRootClientId) {
                return true;
            }

            $seen[$clientId] = true;
            $block           = $this->getBlock($clientId);

            if ($block === null) {
                return false;
            }

            $clientId = isset($block['parentClientId']) ? $block['parentClientId'] : null;
        }

        return false;
    }
}
