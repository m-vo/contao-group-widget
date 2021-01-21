# Serialized storage backend

This storage backend uses serialization to store all of a group's data into 
a single string/blob. This strategy is enabled by default (`'storage' => 
'serialized'`). 

## Data definition
The group field in your DCA expects a blob as `sql` definition:

```php
$GLOBALS['TL_DCA']['tl_content']['fields']['accordion'] = [
    'inputType' => 'group',
    'palette' => ['headline', 'text'],
    // …
    'sql' => [
        'type' => 'blob',
        'length' => \Doctrine\DBAL\Platforms\MySqlPlatform::LENGTH_LIMIT_BLOB,
        'notnull' => false,
    ],
];
```

## Retrieving data
To use your group data, you need to deserialize it first, then you can 
iterate over the element. Each element will be an array with keys being the 
field names and values being the stored data.  

```php
$group = \Contao\StringUtil::deserialize($contentModel->accordion, true);

foreach ($group as $element) {
    $headline = $element['headline'];
    $text = $element['text'];
    
    // …
}
```
