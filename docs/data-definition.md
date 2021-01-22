# Data definition

## Palette and fields
The group will contain the fields you specify in the `palette` array definition.

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    'palette' => ['text', 'singleSRC']
    
    // …
];
```

This can reference all the fields that are living in your DCA. If you want,
you can further include additional fields that are specific for this group 
and reference them as well:

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    'palette' => ['text', 'singleSRC', 'special'],
    'fields' => [
        'special' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
        ]
    ],
    
    // …
];
```

If you're defining a field under the group's `fields` definition that 
already exists in your DCA, the properties can be merged by adding a `&` 
symbol in front of the field name. You can use this to overwrite certain 
properties that should be different in your group:

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    'palette' => ['text', 'singleSRC'],
    'fields' => [
        'text' => [
            'eval' => ['tl_class' => 'w50'],
        ]
    ],
    
    // …
];
```


### Full-blown example
```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    
    // optionally inline some additional field definitions; please note that the
    // definitions provided here will be merged with any of the same name in this
    // DCA - this allows adjusting attributes for the use inside the group
    'fields' => [
        'static_element' => [
            // new inline DCA definition
            'inputType' => 'select',
            'options' => ['Text Blocks', 'Hero Image', 'Foobar'],
        ],
        '&title' => [
            // set some values, but take the rest from the existing definition
            // under '$GLOBALS['TL_DCA']['tl_my_dca']['fields']['title']'
            'eval' => ['mandatory' => false]
        ]   
    ],   
    
     // reference fields from the 'fields' key (see above) or other fields from
     // this DCA that should be included in your group (defaults to elements of
     // 'fields' key if not specified)
    'palette' => [
        'title',            // 1st group element (merged inline + sibling definition)
        'static_element',   // 2nd group element (inline definition)
        'singleSRC'         // 3rd group element (sibling definition)
    ],
    
    // …
];

$GLOBALS['TL_DCA']['tl_my_dca']['fields']['title'] = [
    'inputType' => 'text',
    // no sql definition needed here
];

// …
```

If you're reusing definitions across multiple groups or are using additional
field callbacks, you should prefer referencing fields from the DCA instead of
inlining them. This way you won't repeat yourself, and you can still use option
`@Callback` annotations (because you know your field's name).


## Group behavior
### Min/max constraints
You can force the group to have at least a minimum amount and/or a maximum 
amount of elements by setting the respective `min`/`max` definition:

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    // …
    
    // minimum/maximum number of group elements
    // (both default to 0 = no restriction) 
    'min' => 1,
    'max' => 5,
];
```


## Translations
The translation key of all group elements will have the same default value as
if the field was part of the DCA. To add translations for your group field
`foo_field` in DCA `tl_bar` you would define a translation like so:

```php
$GLOBALS['TL_LANG']['tl_bar']['foo_field'] = ['Foo', 'Add some foo'];
```

If you want to deviate from this, just add your own `label` definition like 
usual.
