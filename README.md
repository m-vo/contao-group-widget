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

## Data definition
Create your DCA and fields like you would without the group widget. Then add
the group field and replace your palette entries with it. 

```diff
- '{foo_legend},title,element_select,singleSRC;{bar_legend},other'
+ '{foo_legend},my_group_field;{bar_legend},other'
```

The repeated fields must be defined under the group field's `palette` key
instead. Additionally, a minimum/maximum number of allowed group elements can
be specified.

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
        'title' => [
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
    
    // minimum/maximum number of group elements (both default to 0 = no restriction) 
    'min' => 1,
    'max' => 5,
    
    // storage engine can be "entity" or "serialized" (defaults to "serialized")
    'storage' => 'serialized',
    'sql' => [
        'type' => 'blob',
        'length' => \Doctrine\DBAL\Platforms\MySqlPlatform::LENGTH_LIMIT_BLOB,
        'notnull' => false,
    ],
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

#### Group field translations
The translation key of all group elements will have the same default value as
if the field was part of the DCA. To add translations for your group field
`foo_field` in DCA `tl_bar` you would define a translation like so:

```php
$GLOBALS['TL_LANG']['tl_bar']['foo_field'] = ['Foo', 'Add some foo'];
```

If you want to deviate from this, just define your own 'label' key like usual.

#### Accessing data
```php
$group = \Contao\StringUtil::deserialize($myGroupField, true);

foreach ($group as $element) {
    $title = $element['title'];
    $select = $element['static_element'];
    $src = $element['singleSRC'];
    
    // …
}
```
Note, that keys of the `$group` array are random numeric element IDs. If you
need a canonical form (numeric keys starting from 0) use `array_values($group)`
instead.

### Using the entity storage engine
You can also set the storage engine to `entity` and reference a group entity class
instead of providing a `blob` field. Your data will then be stored in *your* entities
via Doctrine ORM.

```php
$GLOBALS['TL_DCA']['tl_my_dca']['fields']['my_group_field'] = [
    // …  other settings like above
    
    'storage' => 'entity',
    'entity' => \App\Entity\MyGroup::class
];
```

Your group entity must implement the `GroupEntityInterface` and reference a
child entity via the `elements` property that will be used for the individual
group elements. The child entity must implements the `GroupElementEntityInterface`.

Make sure to name your field's in the element entity like in the palette
definition (you can use camelCase instead of snake_case, though - we're using
the Symfony `PropertyAccessor` under the hood).  

There are abstract base classes for your convenience:

```php
use Mvo\ContaoGroupWidget\Entity\AbstractGroupEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(…)
 */
class MyGroup extends AbstractGroupEntity
{
    // Adjust the "targetEntity" property to match your element entity

    /**
     * @ORM\OneToMany(targetEntity=MyGroupElement::class, mappedBy="parent", orphanRemoval=true)
     */
    protected $elements;
}
```

```php
use Mvo\ContaoGroupWidget\Entity\AbstractGroupElementEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(…)
 */
class MyGroupElement extends AbstractGroupElementEntity
{
    // Adjust the "targetEntity" property to match your group entity

    /**
     * @ORM\ManyToOne(targetEntity=MyGroup::class, inversedBy="elements")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $parent;

    // Add your element fields, getters/setters and other entity
    // specific code here:

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $title;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }
    
    // …
}
```