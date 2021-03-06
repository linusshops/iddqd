<?php

/**
 * Rewrite of class Linus_Iddqd_Model_Config
 *
 * The purpose of this rewrite is to dispatch an event right before the
 * config.xml classes are instantiated. The event lets any external module
 * observe and modify the config.xml object representation just before getting
 * instantiated, JIT-style.
 *
 * @author Dane MacMillan <work@danemacmillan.com>
 * @company Linus Shops
 */
class Linus_Iddqd_Model_Config extends Mage_Core_Model_Config
{
    /**
     * Retrieve class name by class group
     *
     * NOTE: The only addition in this rewrite is the dispatch of an event,
     * which makes it easier to conditionally modify any class paths or
     * rewrites before they are instantiated.
     *
     * @param   string $groupType currently supported model, block, helper
     * @param   string $classId slash separated class identifier, ex. group/class
     * @param   string $groupRootNode optional config path for group config
     * @return  string
     */
    public function getGroupedClassName($groupType, $classId, $groupRootNode=null)
    {
        if (empty($groupRootNode)) {
            $groupRootNode = 'global/'.$groupType.'s';
        }
        $classArr = explode('/', trim($classId));
        $group = $classArr[0];
        $class = !empty($classArr[1]) ? $classArr[1] : null;

        if (isset($this->_classNameCache[$groupRootNode][$group][$class])) {
            return $this->_classNameCache[$groupRootNode][$group][$class];
        }

        $config = $this->_xml->global->{$groupType.'s'}->{$group};

        //iddqd
        // Throw event before rewrite, and verify whether the rewrite should
        // happen, dependent on the context it is being executed in.
        if (isset($config->rewrite->$class)) {
            $eventData = new Varien_Object(array(
                'instance' => $this,
                'group' => $group,
                'class' => $class
            ));
            Mage::dispatchEvent(
                'before_configxml_rewrite',
                array(
                    'config' => $eventData
                )
            );

            // If returns null, or empty string, the rewrite will not happen.
            // Optionally specify another class name. This is only here as a
            // convenience. The proper way to modify this is by accessing the
            // event `instance` data and redefining the rewrite, or removing the
            // rewrite, so that the proceeding rewrite check can evaluate it.
            $class = $eventData->getData('class');
        }
        ///iddqd

        // First - check maybe the entity class was rewritten
        $className = null;
        if (isset($config->rewrite->$class)) {
            $className = (string)$config->rewrite->$class;
        } else {
            /**
             * Backwards compatibility for pre-MMDB extensions.
             * In MMDB release resource nodes <..._mysql4> were renamed to <..._resource>. So <deprecatedNode> is left
             * to keep name of previously used nodes, that still may be used by non-updated extensions.
             */
            if (isset($config->deprecatedNode)) {
                $deprecatedNode = $config->deprecatedNode;
                $configOld = $this->_xml->global->{$groupType.'s'}->$deprecatedNode;
                if (isset($configOld->rewrite->$class)) {
                    $className = (string) $configOld->rewrite->$class;
                }
            }
        }

        // Second - if entity is not rewritten then use class prefix to form class name
        if (empty($className)) {
            if (!empty($config)) {
                $className = $config->getClassName();
            }
            if (empty($className)) {
                $className = 'mage_'.$group.'_'.$groupType;
            }
            if (!empty($class)) {
                $className .= '_'.$class;
            }
            $className = uc_words($className);
        }

        $this->_classNameCache[$groupRootNode][$group][$class] = $className;
        return $className;
    }

    /**
     * Helper for getting adminhtml events config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getAdminhtmlEvents()
    {
        return $this->getXml()->descend('adminhtml/events');
    }

    /**
     * Helper for getting frontend events config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getFrontendEvents()
    {
        return $this->getXml()->descend('frontend/events');
    }

    /**
     * Helper for getting global config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getGlobalConfig()
    {
        return $this->getXml()->descend('global');
    }

    /**
     * Helper for getting global events config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getGlobalEvents()
    {
        return $this->getXml()->descend('global/events');
    }

    /**
     * Helper for getting blocks config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getGlobalBlocks()
    {
        return $this->getXml()->descend('global/blocks');
    }

    /**
     * Helper for getting helpers config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getGlobalHelpers()
    {
        return $this->getXml()->descend('global/helpers');
    }

    /**
     * Helper for getting models config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getGlobalModels()
    {
        return $this->getXml()->descend('global/models');
    }

    /**
     * Helper for getting frontend layout updates config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getLayoutUpdates()
    {
        return $this->getXml()->descend('frontend/layout/updates');
    }

    /**
     * Helper for getting modules config.
     *
     * @return Varien_Simplexml_Element
     */
    public function getModules()
    {
        return $this->getXml()->descend('modules');
    }

    /**
     * Get empty configuration object for loading and merging configuration parts.
     *
     * @return Mage_Core_Model_Config_Base
     */
    public function getPrototype()
    {
        return $this->_prototype;
    }

    /**
     * Helper method for getting protected _xml property in observer.
     *
     * @return Varien_Simplexml_Element
     */
    public function getXml()
    {
        return $this->_xml;
    }

    /**
     * Merge custom config.xml on top of existing XML object.
     *
     * This will merge/overwrite existing nodes in XML object by default.
     *
     * @param $filePath Complete path to config.xml file.
     *
     * @return $this
     */
    public function mergeConfig($filePath)
    {
        $merge = clone $this->getPrototype();
        $merge->loadFile($filePath);
        $this->extend($merge);

        return $this;
    }

    /**
     * Pass registered observer handles and disable their execution.
     *
     * This prevents registered event observers from ever being called. Note,
     * the registered observer can be disabled using two different techniques:
     *
     *  - Destructive: unset the registered observer, literally removing it.
     *  - Proper: Set the registered observer type as `disabled`, which
     *      is non-destructive, and uses built-in Magento logic to disable
     *      events.
     *
     * This method opts to use the proper, non-destructive approach.
     *
     * The observer name is the XML element directly after the <observers>
     * element. This is an example:
     *
     *  <frontend>
     *      <events>
     *          <dispatched_event_name>
     *              <observers>
     *                  <registered_observer_namespace>
     *                      <type>singleton</type>
     *                      <class>Linus_Example_Model_Observer</class>
     *                      <method>onBeforeConfigxmlRewrite</method>
     *                  </registered_observer_namespace>
     *              </observers>
     *          </dispatched_event_name>
     *      </events>
     *  </frontend>
     *
     * @param String $area One of: global|frontend|adminhtml.
     * @param Array $registeredObserverHandles
     *
     * @return $this
     *
     * @throws Mage_Core_Exception
     */
    public function disableEventObserversFor($area, $registeredObserverHandles = array())
    {
        // Determine event area to disable events.
        $frontendEventsConfig = '';
        switch ($area) {
            case 'global':
                $frontendEventsConfig = $this->getFrontendEvents();
                break;
            case 'frontend':
                $frontendEventsConfig = $this->getFrontendEvents();
                break;
            case 'adminhtml':
                $frontendEventsConfig = $this->getFrontendEvents();
                break;
        }

        if (!$frontendEventsConfig || !count($registeredObserverHandles)) {
            Mage::throwException(Mage::helper('core')->__(
                'Invalid arguments passed to disableEventObserversFor.'
            ));
        }

        foreach ($registeredObserverHandles as $registeredObserverNamespace) {
            foreach ($frontendEventsConfig as $iterableEventsConfig) {
                foreach ($iterableEventsConfig as $dispatchedEvent) {
                    $eventObservers = $dispatchedEvent->observers;
                    if (isset($eventObservers->$registeredObserverNamespace)) {
                        $eventObservers->$registeredObserverNamespace->type = 'disabled';
                        // This also works, but no need to use it.
                        //unset($eventObservers->$registeredObserverNamespace);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Disable frontend layout.xml updates files for provided handles.
     *
     * @param $layoutXmlHandles
     *
     * @return $this
     *
     * @throws Mage_Core_Exception
     */
    public function disableFrontendLayoutXmlUpdatesFor($layoutXmlHandles = array())
    {
        if (is_array($layoutXmlHandles)
            && !count($layoutXmlHandles)
        ) {
            Mage::throwException(Mage::helper('core')->__(
                'At least one layout handle must be provided.'
            ));
        }

        $layoutXmlPath = $this->getLayoutUpdates();
        foreach ($layoutXmlHandles as $layoutXmlHandle) {
            if (isset($layoutXmlPath->$layoutXmlHandle)) {
                unset($layoutXmlPath->$layoutXmlHandle);
            }
        }

        return $this;
    }

    /**
     * Set given active flag for given module names to false.
     *
     * @param array $moduleNames
     *
     * @return $this
     *
     * @throws Mage_Core_Exception
     */
    public function disableModulesByName($moduleNames = array())
    {
        if (is_array($moduleNames)
            && !count($moduleNames)
        ) {
            Mage::throwException(Mage::helper('core')->__(
                'At least one module name must be provided.'
            ));
        }

        $moduleXmlPath = $this->getModules();
        foreach ($moduleNames as $moduleName) {
            if (!empty($moduleXmlPath->$moduleName)) {
                $moduleXmlPath->$moduleName->setNode('active', 'false');
            }
        }

        return $this;
    }

    /**
     * Remove codebase group from existence.
     *
     * Pass a codegroup within the global config, and an array of codegroup
     * names, and they will be removed from the config.
     *
     * @param String $codeGroup Example: "blocks|helpers|models..."
     * @param array $codeGroupNames The codegroup handle
     *
     * @return $this
     *
     * @throws Mage_Core_Exception
     */
    public function removeGlobalCodebasesFor($codeGroup, $codeGroupNames = array())
    {
        if (!$codeGroup
            || (is_array($codeGroupNames)
                && !count($codeGroupNames))
        ) {
            Mage::throwException(Mage::helper('core')->__(
                'Invalid arguments passed to removeGlobalCodebasesFor.'
            ));
        }

        $globalCodeGroup = $this->getGlobalConfig()->$codeGroup;
        foreach ($codeGroupNames as $codeGroupName) {
            if (isset($globalCodeGroup->$codeGroupName)) {
                unset($globalCodeGroup->$codeGroupName);
            }
        }

        return $this;
    }

    /**
     * Helper to easily rewrite path with another class name.
     *
     * @param String $xmlPath Path to class handle.
     * @param String $className Class name to be instantiated.
     *
     * @return $this
     */
    public function rewriteClass($xmlPath, $className)
    {
        $configXml = $this->getXml();
        $configXml->setNode($xmlPath, $className);

        return $this;
    }

    /**
     * Helper to easily rewrite path with corresponding class names.
     *
     * If there are many rewrites to define, it is probably better to use the
     * mergeConfig method, which can merge an entire config.xml, effectively
     * doing what this helper does, but includes everything else, too. This is
     * here in case the mergeConfig method is not flexible enough.
     *
     * @param String $xmlPath
     * @param Array $classHandleNames Pass array with classHandle => className.
     *
     * @return $this
     */
    public function rewriteClasses($xmlPath, $classHandleNames)
    {
        if (is_array($classHandleNames)
            && count($classHandleNames)
        ) {
            foreach ($classHandleNames as $classHandle => $className) {
                $this->rewriteClass("$xmlPath/$classHandle", $className);
            }
        }

        return $this;
    }

    /**
     * Helper to easily delete class handle from path.
     *
     * This can be used to delete any class name from an XML path, not just
     * other rewrites.
     *
     * @param String $xmlPath
     * @param String $classHandle
     *
     * @return $this
     */
    public function deleteClass($xmlPath, $classHandle)
    {
        $configXml = $this->getXml();
        $deleteClass = $configXml->descend($xmlPath);
        if (isset($deleteClass->$classHandle)) {
            unset($deleteClass->$classHandle);
        }

        return $this;
    }

    /**
     * Helper to easily delete class handles from path.
     *
     * Pass an XML path and an array of class handles found at that path, which
     * will be removed.
     *
     * @param String $xmlPath
     * @param Array $classHandles
     *
     * @return $this
     */
    public function deleteClasses($xmlPath, $classHandles)
    {
        if (is_array($classHandles)
            && count($classHandles)
        ) {
            foreach ($classHandles as $classHandle) {
                $this->deleteClass($xmlPath, $classHandle);
            }
        }

        return $this;
    }
}
