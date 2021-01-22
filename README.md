# contao-group-widget

This Contao CMS extension provides a widget type<sup>1)</sup> `group` that allows
repeatable groups of fields in the backend. The resulting data is either
stored as a serialized array (`blob`) or in a custom entity relationship.

![](docs/widget.png)

*<sup>1)</sup> Actually, it's not using a `\Contao\Widget` at all behind the
scenes but replaces a field with this `inputType` with a series of virtual 
group fields at runtime and handles storing them for you.*

#### Design decisions / Limitations
The group widget does not alter the rendering and state of the displayed child
widgets for the sake of a broader compatibility. As a result, adding new
elements via the *plus* button will submit the current state to render + add a
new group instance.

Visual reordering of elements is done via the CSS `order` property. This way
`iframes` can be kept alive (the DOM won't change) which is especially helpful
when dealing with components like the `tinyMCE` rich text editor.

## Documentation
* [Data definition](docs/data-definition.md)
* Storage backends
   - [Serialized storage](docs/storage/serialized-storage.md) (default)
   - [Entity storage](docs/storage/entity-storage.md)
   - [Extending](docs/storage/extending.md)


## TLDR;

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    'inputType' => 'group',
    'palette' => ['amount', 'singleSRC', 'text'],   
    'fields' => [
        'amount' => [ // new field
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
        ],
        '&singleSRC' => [ // merge with existing field
            'eval' => ['mandatory' => false],
        ]   
    ],   
    
    // have at least 1, at max 5 elements (defaults to unlimited)
    'min' => 1,
    'max' => 5,
    
    // store into a blob (serialized storage backend)
    'sql' => [
        'type' => 'blob',
        'length' => \Doctrine\DBAL\Platforms\MySqlPlatform::LENGTH_LIMIT_BLOB,
        'notnull' => false,
    ],
];
```
