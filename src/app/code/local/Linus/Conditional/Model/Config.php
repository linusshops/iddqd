<?php

class Linus_Conditional_Model_Config extends Mage_Core_Model_Config
{
    /**
     * Retrieve class name by class group
     *
     * NOTE: The only addition in this rewrite is the dispatch of an event,
     * which makes it easier to conditionally rewrite code that has multiple
     * rewrites in place, that typically cause conflicts.
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

        // Linus_Conditional
        // Throw event before rewrite, and verify whether the rewrite should
        // happen, dependent on the context it is being executed in.
        if (isset($config->rewrite->$class)) {
            $eventData = new Varien_Object(array(
                'group' => $group,
                'class' => $class
            ));
            Mage::dispatchEvent(
                'before_configxml_rewrite',
                array(
                    'class' => $eventData
                )
            );

            // If returns null, or empty string, the rewrite will not happen.
            // Optionally specify another class name.
            $class = $eventData->getData('class');
        }
        // /Linus_Conditional

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
}
