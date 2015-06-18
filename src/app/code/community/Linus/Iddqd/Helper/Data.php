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
     * This is essential for helpers that check controller information.
     *
     * See app/code/core/Mage/Core/Model/App.php:747 to see how the
     * frontController gets instantiated and added to the registry. Trying to
     * call getFrontController off of Mage::app() before it has been
     * instantiated results in an exception that cannot be contained. The reason
     * it may not have been instantiated yet is because Iddqd throws its
     * event extremely early in the call stack, and many of these essential
     * methods have not become available yet. _frontController is protected and
     * unavailable to the observer context from Mage::app()->_frontController,
     * so checking for null is not possible. This is the best option, as it
     * always gets registered when instantiated. It is the only object that
     * occupies the "controller" registry key.
     *
     * @return Mage_Core_Controller_Varien_Front
     */
    public function getFrontController()
    {
        return Mage::registry('controller');
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
            $frontController = $this->getFrontController();
            if (!is_null($frontController)) {
                $frontControllerName = $frontController->getRequest()->getControllerName();
            }
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
            $frontController = $this->getFrontController();
            if (!is_null($frontController)
                && !is_null($frontController->getData('action'))
            ) {
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
     * Helper for determining if on a search result page.
     *
     * @return bool
     */
    public function isSearchResultPage()
    {
        $isSearchResultPage = false;

        // Checking against this URL part provides early access to call stack,
        // even before the frontController has been instantiated. See
        // getFrontController in this class for better explanation.
        $searchControllerUrlPath = '/catalogsearch/result';

        $frontControllerName = $this->getFrontControllerName();
        $searchPageControllerNames = array(
            'result'
        );

        $frontControllerActionName = $this->getFrontControllerActionName();
        $searchPageControllerActionNames = array(
            'catalogsearch_result_index'
        );

        if ($this->isInUrl($searchControllerUrlPath)
            || in_array($frontControllerName, $searchPageControllerNames)
            || in_array($frontControllerActionName, $searchPageControllerActionNames)
        ) {
            $isSearchResultPage = true;
        }

        return $isSearchResultPage;
    }
}
