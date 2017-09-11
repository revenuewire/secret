[![Build Status](https://travis-ci.org/revenuewire/secret.svg?branch=master)](https://travis-ci.org/revenuewire/secret)

#Description
RW Secret Service is designed to hold encrypted keys through AWS KMS system.

#Install
```bash
composer require revenuewire/secret
```

#Put a secret
```bash
php ./bin/put --key=[key]
```

#Get a secret
```php
<?php
echo RW\Secret::get("[key]");
```