# iddqd

The purpose of this Magento module is to open up the possibility to
conditionally `rewrite` code based on a given context. For example, if two
modules are competing to rewrite layered navigation functionality across an
entire site, but only one module should rewrite the standard layered
navigation in category listings, while the other should rewrite the layered
navigation on search results, this module will contextualize the rewrite so
the relevant one is used.

##Usage

Observe the `before_configxml_rewrite` event.  This will pass an object called
"class" to your observer. To prevent a rewrite from occurring, change the "class"
property of the class object to null.  To specify a different class to use,
change the "class" property to the name of the other class to use.

### /index.php
```
Mage::run($mageRunCode, $mageRunType, array('config_model' => 'Linus_Conditional_Model_Config'));
```

### etc/config.xml
```
<global>
    <events>
        <before_configxml_rewrite>
            <observers>
                <linus_adaptersearch>
                    <type>singleton</type>
                    <class>Linus_AdapterSearch_Model_Observer</class>
                    <method>onBeforeConfigxmlRewrite</method>
                </linus_adaptersearch>
            </observers>
        </before_configxml_rewrite>
    </events>
</global>
```

### Model/Observer.php
```
public function onBeforeConfigxmlRewrite(Varien_Event_Observer $observer)
{
    // Get Varien object data.
    $config = $observer->getData('config');

    /** @var Mage_Core_Model_Config_Element $configXml */
    $configXml = $config->getData('xml');

    // Retrieve ANY path IN ENTIRE UNIVERSE.
    $cool = $configXml->descend('global/models/catalog');

    // Because you disagree with the chosen class path, SET YOUR OWN.
    $hiyooo = $configXml->setNode('global/models/catalog/class', 'LINUS_COOL');
}
```

## Warning

With great power comes even greater responsibility. You can TOTALLY GET **REKT**
using this method.



## Note

This is still a work in progress.

## TODO

- Complete documentation
- Write more functionality
