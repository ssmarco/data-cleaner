# Data Versions Cleaner

At some point we need to do housekeeping of large tables in our website especially versions table.

## Example Use Case

Simple class with basic inheritance but has versioning applied and is only created/updated by a custom API

```php
class DigitalAsset extends File {
    private static $db = [
        'FileCategory' => 'Enum("Brochures, Manuals, Polices,", "")',
        'Sort'         => DBInt::class
    ];

    private static $has_many = [];  // Not taken into account with
    private static $many_many = []; // complex relationships

}
```

This generates 2 tables with identical rows having `RecordID` and `Version` columns.
`DigitalAsset_Versions` and `File_Versions`

The task will create a queue job that will delete eg, latest 5 records in each versions table.


## Disclaimer

This is just a proof of concept and in early developement.

Has not been tested in pages with complex relationships defined.

But for simple dataobject inheritance, then please check it out.

## Requirements

* SilverStripe ^4.0

## Installation

```
composer require ssmarco/data-cleaner
```

## License
See [License](license.md)

We have included a 3-clause BSD license you can use as a default. We advocate for the BSD license as 
it is one of the most permissive and open licenses.

Feel free to alter the [license.md](license.md) to suit if you wan to use an alternative license.
You can use [choosealicense.com](http://choosealicense.com) to help pick a suitable license for your project.

## Documentation
 * [Documentation readme](docs/en/readme.md)

Add links into your docs/<language> folder here unless your module only requires minimal documentation 
in that case, add here and remove the docs folder. You might use this as a quick table of content if you
mhave multiple documentation pages.

## Example configuration (optional)
If your module makes use of the config API in SilverStripe it's a good idea to provide an example config
 here that will get the module working out of the box and expose the user to the possible configuration options.

Provide a yaml code example where possible.

```yaml

Marcz\Cleaner\DataVersionCleanerTask:
  RecordClass: 'SilverStripe\Blog\Model\BlogPost'
  VersionsToKeep: 5
```

Or execute in your terminal
```bash
sake dev/tasks/Marcz-Cleaner-DataVersionCleanerTask '' RecordClass='SilverStripe\Blog\Model\BlogPost' VersionsToKeep=3
```

## Maintainers
 * Marco Hermo <marco@silverstripe.com>
 
## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over 
existing issues to ensure yours is unique. 
 
If the issue does look like a new bug:
 
 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots 
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version, 
 Operating System, any installed SilverStripe modules.
 
Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.
 
## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
