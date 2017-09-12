[![Build Status](https://travis-ci.org/revenuewire/secret.svg?branch=master)](https://travis-ci.org/revenuewire/secret)
[![Coverage Status](https://coveralls.io/repos/github/revenuewire/secret/badge.svg?branch=master)](https://coveralls.io/github/revenuewire/secret?branch=master)
[![Latest Stable Version](https://poser.pugx.org/revenuewire/secret/v/stable)](https://packagist.org/packages/revenuewire/secret)

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