# Entity Storage Backend

This storage backend uses Doctrine ORM to store the records in your entity 
classes. To enable, set the `storage` key to `entitiy`:

```php
$GLOBALS['TL_DCA']['tl_island']['treasures'] = [
    'inputType' => 'group',
    'storage' => 'entity',
    // …
];
```

We'll need to entities - one for the group, one for the element - with an 
association between them. The group entity can either be the same entity 
you're using for your DCA (if you do so) or a separate, referenced one.

## Group entity
### DCA entity variant
Your entity class will be auto-detected. The association field must be named 
like your group field (`$treasures` in our example). For updating the 
relation you need to implement three methods to get/add/remove items:

```php
/**
 * @ORM\Entity()
 * @ORM\Table(name="tl_island")
 */
class Island
{
    /**
     * @ORM\OneToMany(targetEntity=Treasure::class, mappedBy="parent", orphanRemoval=true)
     */
    private $treasures;

    /**
     * @return Collection<int, Treasure>
     */
    public function getTreasures(): Collection
    {
        // … return treasure collection …
    }

    public function addTreasure(Treasure $treasure): self
    {
        // … update treasure collection …
    }

    public function removeTreasure(Treasure $treasure): self
    {
        // … update treasure collection …
    }
}
```

If you're using `make:entity` from Symfony's maker bundle, this will all be 
auto-generated for you in the same way the group widget expects it.

### Standalone entity variant
Similarly, using an individual entity class is possible. In this case you 
need to do a bit more setup:

1. Define the entity class in your group definition:

    ```php
    $GLOBALS['TL_DCA']['tl_island']['treasures'] = [
        'inputType' => 'group',
        'storage' => 'entity',
        'entity' => Map::class,
        // …
    ];
    ```

2. Implement the three methods like above but with `$element` being the 
   field name. Additionally, we'll need `$sourceId` and `$sourceTable` fields,
   so that we can reference the group. There is an abstract base class that 
   handles all these things for you:
   
   ```php
   /**
    * @ORM\Entity()
    * @ORM\Table(name="Map")
    */
   class Map extends AbstractGroupEntity
   {
       /**
        * @ORM\OneToMany(targetEntity=Treasure::class, mappedBy="parent", orphanRemoval=true)
        */
       protected $elements;
   }
   ```

## Element entity
Our associated element entity must implement the `GroupElementEntityInterface`. 
We need this to determine an element's position and ID in the group. There is
an abstract base class for your convenience:

```php
/**
 * @ORM\Entity()
 * @ORM\Table(name="tl_treasure")
 */
class Treasure extends AbstractGroupElementEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=Island::class, inversedBy="locations")
     * @ORM\JoinColumn(name="parent", nullable=false)
     */
    protected $parent;

    // … group field definitions go here …
}
```

You'll then need to add fields (or getter/setter pairs) matching the group 
fields:

```php
$GLOBALS['TL_DCA']['tl_island']['treasures'] = [
    // …
    'palette' => ['finding', 'location'],
];
```

```php
/**
 * @ORM\Entity()
 * @ORM\Table(name="tl_treasure")
 */
class Treasure extends AbstractGroupElementEntity
{
    // … 
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $finding = '';

    /**
     * @ORM\Column(type="float")
     */
    private float $latitude = 0;

    /**
     * @ORM\Column(type="float")
     */
    private float $longitude = 0;

    /**
     * Virtual field 'location' (get).
     */
    public function getLocation(): string
    {
        return sprintf('%d, %d', $this->latitude, $this->longitude);
    }

    /**
     * Virtual field 'location' (set).
     */
    public function setLocation(string $latLong): void
    {
        [$this->latitude, $this->longitude] = array_map('floatval', explode(',', $latLong));
    }
}
```

In this example, we defined the `$finding` field for our `finding` group 
field. For the `location` field we went another route and defined a getter 
and setter instead. The system will always try to use `PropertyAccess` first 
and fall back to reflection (like Doctrine does when populating data).

## Examples
You'll find the full code of these examples in the `tests/Fixtures/Entity` 
directory. 

  * `Island` is an entity backing a DCA with a group field,
  * `Map` a standalone one,
  * `Treasure` the element entity.
