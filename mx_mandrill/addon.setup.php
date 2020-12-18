<?php

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

if (!defined('MX_MANDRILL_NAME')) {
    define('MX_MANDRILL_NAME', $addonJson->name);
    define('MX_MANDRILL_VERSION', $addonJson->version);
    define('MX_MANDRILL_DOCS', '');
    define('MX_MANDRILL_DESCRIPTION', $addonJson->description);
    define('MX_MANDRILL_AUTHOR', 'Max Lazar');
    define('MX_MANDRILL_DEBUG', false);
}

return [
    'name'           => $addonJson->name,
    'description'    => $addonJson->description,
    'version'        => $addonJson->version,
    'namespace'      => $addonJson->namespace,
    'author'         => 'Max Lazar',
    'author_url'     => 'https://eecms.dev',
    'settings_exist' => true,
    // Advanced settings
    'services'       => [],
];
