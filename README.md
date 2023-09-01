[![CI](https://github.com/PortableStudios/eloquent-zoho/actions/workflows/laravel.yml/badge.svg)](https://github.com/PortableStudios/eloquent-zoho/actions/workflows/laravel.yml)

# Eloquent Zoho

This package provides a rudimentary(aka, far from complete) implementation of the Zoho API as an Eloquent driver for Laravel, to enable the use of Zoho data models as eloquent models.

Database definitions in `config/databases.php` need to provide the following configuration values

```
'driver' => 'zoho',
'api_url' => // the URL for your Zoho workspace,
'api_email' => // the API  email for your Zoho workspace,
'workspace_name' => // The workspace name,
'folder_name' => // The folder where your tables are stored within Zoho.  This is used when manipulating data schemas using ZohoSchema
'auth_token' => // Your generated auth token, see below
```

The driver assumes that your database connection key is 'zoho'.

(E.g within `config/database.php`, you will define `connections['zoho']` with your config).

## Authentication
Your application must generate, and be responsbile for storing, an auth token prior to interacting with the API through the driver.

You can use the `ZohoSchema` facade to achieve this:
```
use Portable\EloquentZoho\Eloquent\Facades\ZohoSchema;
$authToken = ZohoSchema::generateAuthToken('zoho_user_email','zoho_user_password');
```

## Database schema
You can create and manipulate Schemas as normal with the `ZohoSchema` facade:

```
use Portable\EloquentZoho\Eloquent\Facades\ZohoSchema;

ZohoSchema::hasTable('my_zoho_table')

// or

ZohoSchema::create('my_zoho_table', function(Blueprint $table){
    $table->id();
    $table->timestamps();
});
```

## Model definitions

Models should be defined as a subclass of `Portable\EloquentZoho\Eloquent\ZohoModel`

Models currently support basic query, insert, update and delete, as well as upserts.

## PRs Welcome!
As stated, this driver is rudimentary and was written for a specific use case.  As such, much of the possible grammar is left unimplemented, and PRs are welcome.