[![Build Status](https://travis-ci.org/revenuewire/secret.svg?branch=master)](https://travis-ci.org/revenuewire/secret)

#Install
```bash
composer require revenuewire/secret
```

#Put a secret
```bash
php ./vendor/revenuewire/secret/bin/put [key] [secret]
```

#Get a secret
```php
<?php
echo RW\Secret::get("[key]");
```