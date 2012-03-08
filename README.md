# JSON Decode Fallback

This file is meant to be used with systems that do not run PHP 5.2 or greater.
Unlike other fallbacks it does not include a json\_decode helper method. You
should not be including the file if your system doesn't need it.

## Usage

Usage is pretty straightforward. Simply instantiate the class and call the
`decode()` method with your json string.

    $json = new Json();
    $php_data = $json->decode($json_string);