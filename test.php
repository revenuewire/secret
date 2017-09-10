<?php
/**
 * Created by IntelliJ IDEA.
 * User: swang
 * Date: 2017-09-10
 * Time: 2:42 PM
 */
require_once ("./vendor/autoload.php");

var_dump(\RW\Secret::get("test1"));
var_dump(\RW\Secret::get("test-1"));