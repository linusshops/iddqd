<?php

/**
 * Class Linus_Iddqd_Helper_Data
 *
 * Provide some common verifications while in the observed event.
 *
 * @author Dane MacMillan <work@danemacmillan.com>
 */
class Linus_Iddqd_Helper_Data extends Mage_Core_Helper_Abstract
{
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
}
