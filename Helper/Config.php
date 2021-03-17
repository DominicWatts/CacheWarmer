<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class Config extends AbstractHelper
{
    const CONFIG_XML_AGENT_NAME = 'cachewarmer/options/agent_name';
    const CONFIG_XML_AGENT_VERSION = 'cachewarmer/options/agent_version';
    const CONFIG_XML_AGENT_EDITION = 'cachewarmer/options/agent_edition';
    const CONFIG_XML_PROXY = 'cachewarmer/options/proxy';

    /**
     * @param Context $context
     */
    // phpcs:disable 
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }
    // phpcs:enable 

    /**
     * Get user agent name
     * @param Store $store
     * @return string
     */
    public function getAgentName(Store $store)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_AGENT_NAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get user agent version
     * @param Store $store
     * @return string
     */
    public function getAgentVersion(Store $store)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_AGENT_VERSION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get user agent edition
     * @param Store $store
     * @return string
     */
    public function getAgentEdition(Store $store)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_AGENT_EDITION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get proxy
     * @param Store $store
     * @return string
     */
    public function getProxy(Store $store)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PROXY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
