<?php

/*
 * This file is part of the Force Login module for Magento2.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bitExpert\ForceCustomerLogin\Controller;

use \bitExpert\ForceCustomerLogin\Api\Controller\LoginCheckInterface;
use \bitExpert\ForceCustomerLogin\Api\Repository\WhitelistRepositoryInterface;
use \bitExpert\ForceCustomerLogin\Model\ResourceModel\WhitelistEntry\Collection;
use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use \Magento\Framework\UrlInterface;
use \Magento\Framework\App\DeploymentConfig;
use \Magento\Backend\Setup\ConfigOptionsList as BackendConfigOptionsList;

/**
 * Class LoginCheck
 * @package bitExpert\ForceCustomerLogin\Controller
 */
class LoginCheck extends Action implements LoginCheckInterface
{

    const REDIRECT_URL = 'force_customer_login/url_config/redirect_url';
    /**
     * @var UrlInterface
     */
    protected $url;
    /**
     * @var DeploymentConfig
     */
    protected $deploymentConfig;
    /**
     * @var WhitelistRepositoryInterface
     */
    protected $whitelistRepository;
    /**
     * @var string
     */
    protected $targetUrl;
    /**
     * @var
     */
    private $scopeConfig;

    /**
     * Creates a new {@link \bitExpert\ForceCustomerLogin\Controller\LoginCheck}.
     *
     * @param Context $context
     * @param DeploymentConfig $deploymentConfig
     * @param WhitelistRepositoryInterface $whitelistRepository
     * @internal param string $targetUrl
     */
    public function __construct(
        Context $context,
        DeploymentConfig $deploymentConfig,
        WhitelistRepositoryInterface $whitelistRepository,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->whitelistRepository = $whitelistRepository;
        $this->scopeConfig = $scopeConfig;
        $this->targetUrl = $this->getRedirectUrlConfig();
        parent::__construct($context);
    }

    /**
     * Manages redirect
     */
    public function execute()
    {
        $url = $this->_url->getCurrentUrl();
        $path = \parse_url($url, PHP_URL_PATH);

        // current path is already pointing to target url, no redirect needed
        if ($this->targetUrl === $path) {
            return;
        }

        $ignoreUrls = $this->getUrlRuleSetByCollection($this->whitelistRepository->getCollection());
        $extendedIgnoreUrls = $this->extendIgnoreUrls($ignoreUrls);

        // check if current url is a match with one of the ignored urls
        foreach ($extendedIgnoreUrls as $ignoreUrl) {
            if (\preg_match(\sprintf('#^.*%s/?.*$#i', $this->quoteRule($ignoreUrl)), $path)) {
                return;
            }
        }

        $this->_redirect($this->targetUrl)->sendResponse();
    }

    protected function getRedirectUrlConfig() {
        $url = $this->getConfigUrl();
        if ($url == '') {
            $url = $this->getDefaultUrl();
        }
        return $url;
    }

    /**
     * Quote delimiter in whitelist entry rule
     * @param string $rule
     * @param string $delimiter
     * @return string
     */
    protected function quoteRule($rule, $delimiter = '#')
    {
        return \str_replace($delimiter, \sprintf('\%s', $delimiter), $rule);
    }

    /**
     * @param Collection $collection
     * @return string[]
     */
    protected function getUrlRuleSetByCollection(Collection $collection)
    {
        $urlRuleSet = array();
        foreach ($collection->getItems() as $whitelistEntry) {
            /** @var $whitelistEntry \bitExpert\ForceCustomerLogin\Model\WhitelistEntry */
            \array_push($urlRuleSet, $whitelistEntry->getUrlRule());
        }
        return $urlRuleSet;
    }

    /**
     * Add dynamic urls to forced login whitelist.
     *
     * @param array $ignoreUrls
     * @return array
     */
    protected function extendIgnoreUrls(array $ignoreUrls)
    {
        $adminUri = \sprintf(
            '/%s',
            $this->deploymentConfig->get(BackendConfigOptionsList::CONFIG_PATH_BACKEND_FRONTNAME)
        );

        \array_push($ignoreUrls, $adminUri);

        return $ignoreUrls;
    }

    /**
     * @return mixed
     */
    protected function getConfigUrl()
    {
        return $this->scopeConfig->getValue(
            self::REDIRECT_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    protected function getDefaultUrl()
    {
        return $this->scopeConfig->getValue(
            'force_customer_login/default_redirect_url',
            ScopeInterface::SCOPE_STORE
        );
    }
}
