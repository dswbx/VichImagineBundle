# VichImagineBundle
This bundle integrates VichUploaderBundle as uploader and LiipImagineBundle as processor (resize etc.) component.
## Installation
```sh
composer require itf/vich-imagine-bundle "dev-master"
php app/console assets:install
```
Active the bundles in AppKernel.php:
```php
public function registerBundles() {
    $bundles = array(
        //[...]
        new Liip\ImagineBundle\LiipImagineBundle(),
        new Vich\UploaderBundle\VichUploaderBundle(),
        
        new VichImagineBundle\VichImagineBundle(),
    ),
    // ...
}    
```

## Configure VichUploaderBundle
Refer to its [documentation](https://github.com/dustin10/VichUploaderBundle) to configure this bundle. Here's an example:
```yml
vich_uploader:
    db_driver: orm # or mongodb or propel or phpcr
    mappings:
        product_image:
            uri_prefix:         /images/products
            upload_destination: %kernel.root_dir%/../web/images/products
            namer: vich.custom.random_namer
```
## Configure LiipImagineBundle
Refer to its [documentation](https://github.com/liip/LiipImagineBundle) to configure this bundle. Here's an example:
```yml
liip_imagine:
    resolvers:
        default:
            web_path:
                web_root: %kernel.root_dir%/../web
                cache_prefix: cache/
    loaders:
        default:
            filesystem:
                data_root: %kernel.root_dir%/../web/
    driver:               gd
    cache:                default
    data_loader:          default
    default_image:        null
    controller:
        filter_action:         liip_imagine.controller:filterAction
        filter_runtime_action: liip_imagine.controller:filterRuntimeAction
    filter_sets:
        product_image:
            filters:
                jpeg_quality: 75
                #png_compression_level:  ~
                format: jpg
                relative_resize: { widen: 800 }
```
## Configuration Example
* Configuration example: [config_sample.yml](https://github.com/RSSfeed/VichImagineBundle/blob/master/Resources/config/config_sample.yml)
* Entity example: [Image.php](https://github.com/RSSfeed/VichImagineBundle/blob/master/Entity/Image.php)
* FormType example: [ImageType.php](https://github.com/RSSfeed/VichImagineBundle/blob/master/Form/ImageType.php)

That's it.

## License
MIT
