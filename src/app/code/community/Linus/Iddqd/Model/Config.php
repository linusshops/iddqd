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

        // iddqd
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
            // Optionally specify another class name. This does not even need
            // to be here as all the data is passed by reference to observer,
            // which can then unset this data.
            $class = $eventData->getData('class');
        }
        // /iddqd

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
     * Helper to easily rewrite path with another class name.
     *
     * @param $xmlPath
     * @param $className
     *
     * @return $this
     */
    public function rewriteClass($xmlPath, $className)
    {
        $this->setNode($xmlPath, $className);
        return $this;
    }
}
