<?php
/**
 * @author Alan Barber <alan@cadence-labs.com>
 */
namespace Cadence\PageBuilderDisable\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\Config\CompositeReader;

class Config extends \Magento\PageBuilder\Model\Config
{
    const CONFIG_PATH_DISABLED_BLOCKS = 'cms/page_builder_disable/excluded_blocks';

    /**
     * @var string
     */
    protected string $disableBlockRegex = '~cms/block/edit/block_id/{{id}}/~';

    /**
     * @var BlockRepositoryInterface
     */
    protected BlockRepositoryInterface $blockRepository;
    
    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlInterface;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Config constructor.
     * @param BlockRepositoryInterface $blockRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param UrlInterface $urlInterface
     * @param CompositeReader $reader
     * @param CacheInterface $cache
     * @param ScopeConfigInterface $scopeConfig
     * @param string $cacheId
     */
    public function __construct(
        BlockRepositoryInterface $blockRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        UrlInterface $urlInterface,
        CompositeReader $reader,
        CacheInterface $cache,
        ScopeConfigInterface $scopeConfig,
        string $cacheId = 'pagebuilder_config'
    ) {
        $this->blockRepository = $blockRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->urlInterface = $urlInterface;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($reader, $cache, $scopeConfig, $cacheId);
    }

    /**
     * Returns config setting if page builder enabled
     *
     * @return bool
     * @throws LocalizedException
     */
    public function isEnabled(): bool
    {
        if (parent::isEnabled()) {
            $excludedBlocks = trim((string)$this->scopeConfig->getValue(self::CONFIG_PATH_DISABLED_BLOCKS));
            if (strlen($excludedBlocks) > 0 && $this->_isDisabledUrlCandidate()) {
                $excludedBlocks = explode(",", $excludedBlocks);
                foreach($excludedBlocks as $excludedBlock) {
                    if ($this->_isDisabledBlock($excludedBlock)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Determine if we should examine the URL further for exclusion of page builder
     * @return bool
     */
    protected function _isDisabledUrlCandidate(): bool
    {
        $disableCandidateRegex = str_replace('{{id}}/', '', $this->disableBlockRegex);
        return preg_match($disableCandidateRegex, $this->urlInterface->getCurrentUrl());
    }

    /**
     * Examine the URL to determine if pagebuilder is disabled
     * @param string $block
     * @return bool
     * @throws LocalizedException
     */
    protected function _isDisabledBlock(string $block): bool
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('identifier', $block,'eq')->create();
            $cmsBlocks = $this->blockRepository->getList($searchCriteria)->getItems();
            foreach ($cmsBlocks as $blockModel) {
                // Create the url pattern for this specific block based on the primary key id
                $urlPattern = str_replace("{{id}}", $blockModel->getId(), $this->disableBlockRegex);
                // If it matches, return false
                if(preg_match($urlPattern, $this->urlInterface->getCurrentUrl())) {
                    return true;
                }
            }
        } catch (NoSuchEntityException $e) {
            // If that block id no longer exists, don't worry about it
            return false;
        }
        return false;
    }
}
