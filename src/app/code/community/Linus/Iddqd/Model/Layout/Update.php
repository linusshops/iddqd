<?php

/**
 * Throw event to allow injection of layout files based on page context.
 * To accomplish this, an event is dispatched right before the layout updates
 * are processed. Using this event, additional layout files can be injected
 * based on programmatic rules.
 *
 * @author Sam Schmidt <samuel@dersam.net>
 * @date 2015-07-29
 * @company Linus Shops
 */
class Linus_Iddqd_Model_Layout_Update extends Mage_Core_Model_Layout_Update
{
    /**
     * Collect and merge layout updates from file
     *
     * @param string $area
     * @param string $package
     * @param string $theme
     * @param integer|null $storeId
     * @return Mage_Core_Model_Layout_Element
     */
    public function getFileLayoutUpdatesXml($area, $package, $theme, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = Mage::app()->getStore()->getId();
        }
        /* @var $design Mage_Core_Model_Design_Package */
        $design = Mage::getSingleton('core/design_package');
        $layoutXml = null;
        $elementClass = $this->getElementClass();
        $updatesRoot = Mage::app()->getConfig()->getNode($area.'/layout/updates');
        Mage::dispatchEvent('core_layout_update_updates_get_after', array('updates' => $updatesRoot));
        $updates = $updatesRoot->asArray();
        $themeUpdates = Mage::getSingleton('core/design_config')->getNode("$area/$package/$theme/layout/updates");
        if ($themeUpdates && is_array($themeUpdates->asArray())) {
            //array_values() to ensure that theme-specific layouts don't override, but add to module layouts
            $updates = array_merge($updates, array_values($themeUpdates->asArray()));
        }
        $updateFiles = array();
        foreach ($updates as $updateNode) {
            if (!empty($updateNode['file'])) {
                $module = isset($updateNode['@']['module']) ? $updateNode['@']['module'] : false;
                if ($module && Mage::getStoreConfigFlag('advanced/modules_disable_output/' . $module, $storeId)) {
                    continue;
                }
                $updateFiles[] = $updateNode['file'];
            }
        }
        // custom local layout updates file - load always last
        $updateFiles[] = 'local.xml';

        /**
         * IDDQD: Inject custom layout files via event.
         */
        $eventData = new Varien_Object(array(
            'update_files' => $updateFiles
        ));

        Mage::dispatchEvent('before_layoutxml_compile', array(
            'updates' => $eventData,
            'god_mode' => $this
        ));

        $updateFiles = $eventData->getUpdateFiles();

        $layoutStr = '';
        foreach ($updateFiles as $file) {
            $filename = $design->getLayoutFilename($file, array(
                '_area'    => $area,
                '_package' => $package,
                '_theme'   => $theme
            ));
            if (!is_readable($filename)) {
                continue;
            }
            $fileStr = file_get_contents($filename);
            $fileStr = str_replace($this->_subst['from'], $this->_subst['to'], $fileStr);
            $fileXml = simplexml_load_string($fileStr, $elementClass);
            if (!$fileXml instanceof SimpleXMLElement) {
                continue;
            }
            $layoutStr .= $fileXml->innerXml();
        }

        $layoutXml = simplexml_load_string('<layouts>'.$layoutStr.'</layouts>', $elementClass);
        return $layoutXml;
    }

    /**
     * Append a layout update file to the layout updates file list.
     * For use in the thrown event.
     *
     * @param $observer Varien_Event_Observer
     * @param $newFile string file name of the additional layout update file.
     */
    public function addLayoutUpdate(Varien_Event_Observer $observer, $newFile)
    {
        $updates = $observer->getUpdates()->getUpdateFiles();
        $updates[] = $newFile;
        $observer->getUpdates()->setUpdateFiles($updates);
    }
}
