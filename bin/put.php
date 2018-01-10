<?php
require_once __DIR__ . "./../vendor/autoload.php";
date_default_timezone_set( 'UTC' );

function read_stdin()
{
    $fr=fopen("php://stdin","r");   // open our file pointer to read from stdin
    $input = fgets($fr,1280);        // read a maximum of 1280 characters
    $input = rtrim($input);         // trim any trailing spaces.
    fclose ($fr);                   // close the file handle
    return $input;                  // return the text entered
}

try {
    $options = getopt('', ['key::', 'override::', 'region::', 'alias::', 'dynamo::']);
    if (empty($options['key'])) {
        throw new Exception("Key is required. php ./vendor/put.php --key=[your_key]");
    }

    $override = empty($options['override']) ? false : filter_var($options['override'], FILTER_VALIDATE_BOOLEAN);
    $regions = empty($options['region']) ? ["us-west-1"] : explode(',', $options['region']);
    $alias = empty($options['alias']) ? "rw-secret" : $options['alias'];
    $dynamoTable = empty($options['dynamo']) ? "secrets" : $options['dynamo'];

    //generate new secret automatically
    $secret = null;
    if ($override === true) {
        echo "Please input the secret ";
        $secret = read_stdin();
    }

    foreach ($regions as $region) {
        \RW\Secret::put($options['key'], $region, $dynamoTable, $alias, $secret, $override);
    }
    echo "ok\n";
} catch (Exception $e) {
    echo "failed. [Exception: {$e->getMessage()}]\n";
}
