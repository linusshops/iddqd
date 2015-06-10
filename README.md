# iddqd

The purpose of this module is to programmatically intercept Magento's merged 
and parsed XML object representation of all the configuration `*.xml` files in
operation, and modify it on the fly, JIT-like. *This has unbelievably powerful
consequences*.

## Why?

A common challenge to Magento development is the introduction of third-party
modules. ~~Most of them are terrible. I digress.~~ Often these third-party
modules compete for the right to extend core Magento classes, but unfortunately
Magento does not make that easy to control. In scenarios like this, where
multiple modules rewrite the same classes, only one can survive, and its name
is always Zoidberg.

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

*iddqd makes it possible to contextually instantiate classes within Magento.*

### Impetus

The need for this solution arose when searching to see whether it would be
possible to add a custom XML attribute like `ifcontroller="someController"` 
to the `<rewrite>` nodes in `config.xml` files, in an attempt at a clean 
integration between two distinct modules that rewrite the same core classes.
That did not seem like a fruitful endeavour, but the possibility of throwing
an event right before the classes are named, seemed like a good opportunity
for this kind of magic.

## How?

This is done by a simple rewrite of the `getGroupedClassName` method in the
`Mage_Core_Model_Config` class. That class is responsible for deciding whether
a class has been rewritten by a module. If there is a `<rewrite>` provided in
a module, the method determines that for the given component, use the rewritten
class if it exists, otherwise fall back to the Magento default. **iddqd**
rewrites that method to insert an event dispatcher immediately before the logic
to determine whether a class rewrite is valid; the entire XML representation is
passed by reference as event data, which can then be observed in any other
module, at which point the XML representation can be manipulated without
restriction before it is finally passed back to the original flow.

A rewrite is just a simple example, though. **iddqd** lifts any and every
restriction when it comes to what classes get instantiated by Magento.

## Installation

This should be installed using Composer. A magento build should also include the
[Magento Composer Installer](https://github.com/Cotya/magento-composer-installer).
This module follows the module structure guidelines provided by
[Firegento](https://github.com/firegento/coding-guidelines/tree/master/sample-module),
which will also make it very easy to submit to the
[Firegento Composer Repository](https://github.com/magento-hackathon/composer-repository).

### Warning

Magento does not allow directly rewriting the `Mage_Core_Model_Config` class.
This is because it is integral to the proper functioning of Magento, and should
it be rewritten badly, Magento will catastrophically fail. However, Magento
did create a way to modify it, in a very explicit I-know-what-I'm-doing-because-I'm-a-pro
type of way. If it is not already clear, this is an experimental module.
If the technique applied in this module appears like black magic, installing 
this is not advised. This is for advanced Magento development.

Having typed that all out, the last installation step is to modify Magento's
`index.php` file to replace the last line so it reads like this instead:

```
Mage::run($mageRunCode, $mageRunType, array('config_model' => 'Linus_Iddqd_Model_Config'));
```

## Usage (i.e., the fun bits)

In a new module, say, in an adapter module that serves as a way of orchestrating
multiple modules, create the boilerplate necessary to observe the following
custom event:

```
before_configxml_rewrite
```

This event will pass by reference a new `Varien_Object` with the event payload
data, that can then be manipulated before passing control flow back to the 
original method. Read the source to see what data is passed.


#### `etc/config.xml`
```
<global>
    ...
    <events>
        <before_configxml_rewrite>
            <observers>
                <linus_example>
                    <type>singleton</type>
                    <class>Linus_Example_Model_Observer</class>
                    <method>onBeforeConfigxmlRewrite</method>
                </linus_example>
            </observers>
        </before_configxml_rewrite>
    </events>
    ...
</global>
```

#### `Model/Observer.php`
```
public function onBeforeConfigxmlRewrite(Varien_Event_Observer $observer)
{
    // Get event.
    $event = $observer->getEvent();

    // Get Varien_Object event data.
    $config = $event->getConfig();
    
    /** @var Linus_Iddqd_Model_Config $instance */
    $instance = $config->getInstance();
    $class = $config->getClass();
    $group = $config->getGroup();

    /** @var Mage_Core_Model_Config_Element $configXml */
    $configXml = $instance->getXml(); // Custom Linus_Iddqd method.

    // Retrieve ANY path IN ENTIRE UNIVERSE.
    $catalogModel = $configXml->descend('global/models/catalog');

    // Because you disagree with the chosen class path, SET YOUR OWN.
    $configXml->setNode('global/models/catalog/class', 'Linus_CoolerModule_Something_Something');
    // Or this for short.
    $catalogModel->setNode('class', 'Linus_CoolerModule_Something_Something');
    
    // Get all rewrites for provided path, and pass whatever other classes
    // should be instantiated instead.
    $catalogRewrites = $configXml->descend('global/models/catalog/rewrite');
    $catalogRewrites->setNode('layer_filter_attribute' => 'A_Better_Class');
    
    // ...or just obliterate them, causing Magento to use built-in core classes.
    unset($catalogRewrites->layer_filter_attribute);
    unset($catalogRewrites->layer_filter_category);
    unset($catalogRewrites->layer_filter_item);
    
    // Maybe you just want to modify classes and rewrites on a given page.
    if (stripos(Mage::app()->getRequest()->getRequestUri(), 'helmets') !== false) {
        // https://i.imgur.com/Re3Ti2c.jpg
    }
    
    // Custom Linus_Iddqd methods:
    
    // Merge in new config.xml.
    $instance->mergeConfig('path/to/custom/config.xml');
    
    // Rewrite a class, or multiple.
    $instance
        ->rewriteClass('global/models/catalog/rewrite/layer_filter_attribute', 'Linus_Better_Class')
        ->rewriteClass('global/models/catalog/class', 'Linus_CoolerModule_Something_Something');
}
```

![](https://i.imgur.com/jujcaDB.gif)

Take a moment. That is your mind being blown.

## TODO

- Add helpers to make setting and unsetting classes and rewrites much easier.

## Author

[Dane MacMillan](https://github.com/danemacmillan)

## Contributors

[Samuel Schmidt](https://github.com/dersam)

## Origin of the name

**iddqd** will be obvious to anyone who grew up playing PC games in the 90s.
What does it mean? It means you operate on *God Mode* and nothing can stop you.
That is also what this module does.

## License

This module was created by Linus Shops and enthusiastically licensed to the
Magento community under the [MIT License](http://opensource.org/licenses/MIT).
