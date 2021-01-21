# Add your own storage backend

**Disclaimer**: Adding your own storage backend is possible but not recommended 
(yet). Until we're sure the `StorageInterface` is stable, changes are likely.
If you've got a use case, please feel free to open an issue/PR.

If you want to have a go:

  1. Add a new storage class implementing the `StorageInterface`.
     
  1. Add a new factory class implementing the `StorageFactoryInterface` that 
     can create a new instance of your storage class.
     
  1. Use the storage backend in your group definition:
     ```php
     $GLOBALS['TL_DCA']['tl_content']['fields']['accordion'] = [
         'inputType' => 'group',
         'storage' => 'yourStorage'
         // …
     ];
     ```
     The storage name (`yourStorage`) is the one that you defined when 
     implementing `StorageFactoryInterface#getName()`.
