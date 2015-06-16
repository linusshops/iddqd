<?php

/**
 * Class Linus_Iddqd_Helper_Data
 *
 * Provide common verification helpers while in the observed event.
 *
 * @author Dane MacMillan <work@danemacmillan.com>
 */
class Linus_Iddqd_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Helper for getting current category data.
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCurrentCategory()
    {
        return Mage::registry('current_category');
    }

    /**
     * Helper for getting current product data.
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getCurrentProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Get the front controller name, if available.
     *
     * Example front controller names:
     *  - category
     *  - result
     *  - product
     *
     * @return null|string
     */
    public function getFrontControllerName()
    {
        $frontControllerName = null;
        try {
            $frontControllerName = Mage::app()->getFrontController()->getRequest()->getControllerName();
        } catch (Mage_Core_Model_Store_Exception $e) {
            // Be graceful.
        }

        return $frontControllerName;
    }

    /**
     * Helper for getting full controller action name.
     *
     * Example front controller action names:
     *  - catalog_category_view
     *  - catalogsearch_result_index
     *  - catalog_product_view
     *
     * @return null
     */
    public function getFrontControllerActionName()
    {
        $frontControllerActionName = null;
        try {
            $frontController = Mage::app()->getFrontController();
            if ($frontController->getAction()) {
                $frontControllerActionName = $frontController->getAction()->getFullActionName();
            }
        } catch (Mage_Core_Model_Store_Exception $e) {
            // Be graceful.
        }

        return $frontControllerActionName;
    }

    /**
     * Get the Model_Config instance that was passed by event.
     *
     * This is the most important helper, because it gives access to
     * Model_Config instance, which has all event data passed in from the
     * Iddqd rewrite.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Linus_Iddqd_Model_Config
     */
    public function getInstance(Varien_Event_Observer $observer)
    {
        // Get event.
        $event = $observer->getEvent();

        // Get Varien_Object event data.
        $config = $event->getConfig();

        /** @var Linus_Iddqd_Model_Config $instance */
        $instance = $config->getInstance();

        return $instance;
    }

    /**
     * Helper for determining if on a category page.
     *
     * @return bool
     */
    public function isCategoryPage()
    {
        $isCategoryPage = false;
        if (!is_null($this->getFrontControllerName())
            && !is_null($this->getCurrentCategory())
        ) {
            $isCategoryPage = true;
        }

        return $isCategoryPage;
    }

    /**
     * Check whether part of a URL is in the request URI.
     *
     * @param $urlPart
     *
     * @return bool
     */
    public function isInUrl($urlPart)
    {
        $requestUri = Mage::app()->getRequest()->getRequestUri();
        return (stripos($requestUri, $urlPart) !== false)
            ? true
            : false;
    }

    /**
     * Helper for determining if on a product page.
     *
     * @return bool
     */
    public function isProductPage()
    {
        $isProductPage = false;
        if (!is_null($this->getFrontControllerName())
            && !is_null($this->getCurrentProduct())
        ) {
            $isProductPage = true;
        }

        return $isProductPage;
    }

    /**
     * Helper for determining if on a search-related page.
     *
     * @return bool
     */
    public function isSearchPage()
    {
        $isSearchPage = false;

        $frontControllerName = $this->getFrontControllerName();
        $searchPageControllerNames = array(
            'result',
            'advanced'
        );

        $frontControllerActionName = $this->getFrontControllerActionName();
        $searchPageControllerActionNames = array(
            'catalogsearch_result_index'
        );

        if (in_array($frontControllerName, $searchPageControllerNames)
            || in_array($frontControllerActionName, $searchPageControllerActionNames)
        ) {
            $isSearchPage = true;
        }

        return $isSearchPage;
    }
}
