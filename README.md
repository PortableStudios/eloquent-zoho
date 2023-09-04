[![CI](https://github.com/PortableStudios/eloquent-zoho/actions/workflows/laravel.yml/badge.svg)](https://github.com/PortableStudios/eloquent-zoho/actions/workflows/laravel.yml)

# Eloquent Zoho

This package provides a rudimentary(aka, far from complete) implementation of the Zoho API as an Eloquent driver for Laravel, to enable the use of Zoho data models as eloquent models.

Database definitions in `config/databases.php` need to provide the following configuration values

```
'driver' => 'zoho',
'host' => // the base URL for your Zoho workspace, e.g. 'bi.myorg.com',
'port' => // Usually 443, assuming you have SSL for your workspace,
'username' => // the API email for your zoho workspace,
'database' => // The workspace name,
'prefix' => // The folder where your tables are stored within Zoho.  This is used when manipulating data schemas using ZohoSchema
'email' => // Your *user* email, used for generating tokens
'password' => // Your *user* password, used for generating tokens
```

The driver assumes that your database connection key is 'zoho'.

(E.g within `config/database.php`, you will define `connections['zoho']` with your config).

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