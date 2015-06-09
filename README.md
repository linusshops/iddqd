# Conditional

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


## Note

This is still a work in progress.

## TODO

- Complete documentation
- Write more functionality
