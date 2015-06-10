# iddqd

The purpose of this module is to programmatically intercept Magento's merged 
and parsed XML representation of all the `config.xml` files in operation, and 
modify it on the fly, JIT-like. *This has unbelievably powerful consequences*.

## Why?

A common boon to Magento development is the introduction of third-party modules.
~~Most of them are terrible. I digress.~~ Often these third-party modules
compete for the right to extend core Magento classes, but unfortunately Magento
does not make that easy to control. In scenarios like this, where multiple
modules rewrite the same classes, only one can survive, and its name is always
Zoidberg.

The consensus among community help forums is to hack away at these modules
until they are a symbiotic Loch Ness of code--hopefully never to be seen by
the eye of scrutiny--but otherwise most commonly known as a steaming pile 
of :hankey:. There is a lot of that in the Magento world.

The other, more clean solution is to create a kind of middleware or adapter
module that serves as common ground for both modules to interact between.
Ultimately, Magento will fail to please, though. While this approach ensures that
both modules can be untouched, thus easing their ability to update, there is
still a lot of redundant adapter code that needs to be written.

**iddqd** attempts to make this a bit more easy. Consider, for example, two
modules that modify the layered navigation. Both modules rewrite very similar or
identical classes, but each of them have very specific, contexual requirements.
One module should only modify the layered navigation on standard category
listings, while the other should only modify the layered navigation on
search results listings. **iddqd** makes this possible.

**iddqd makes it possible contextually instantiate classes within Magento.**

### Impetus

The need for this solution arose when searching to see whether it would be
possible to add a custom XML attribute like `ifcontroller="someController"` 
to the `<rewrite>` nodes in `config.xml` files, in an attempt at a clean 
integration between two distinct modules that rewrite the same core classes.
It did not look straightforward, but the possibility of throwing an event right
before the classes are named, seemed like a good opportunity for this kind of
magic.

## How?

This is done by a simple rewrite of the `getGroupedClassName` method in the
`Mage_Core_Model_Config` class. That class is responsible for deciding whether
a class has been rewritten by a module. If there is a `<rewrite>` provided in
a module, the method determines that for the given component, use the rewritten
class if it exists, otherwise fall back to the Magento default. **iddqd**
rewrites that method to insert an event dispatcher immediately before the logic
to determine whether a class rewrite is valid; the entire XML representation is
passed by reference as event data, which can then be observed in any other
module and the XML representation can be manipulated without restriction before
it is finally passed back to the original flow.

A rewrite is just a simple example, though. **iddqd** lifts any and every
restriction when it comes to what classes get instantiated by Magento.

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
