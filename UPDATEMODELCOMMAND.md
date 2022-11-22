# CodeIgniter Update Model Command

Codeigniter 4 does not have an easy way to query tables that are in the relation one2many or many2many. this trait extends entities with simple methods for these relations.

## Installation

Usually the tools have been installed in the development environment and the required file has to be copied into your app structure. When using the tool in your productivity environment, you can implement the trait by using `use Rakoitde\Tools\Entities\EntityRelationTrait;`

### Sparked

Install all the tools and copy the EntityRelationTrait.php file to the App\Entities folder and the namespace will be renamed to `App\Entities`

~~~ shell
php spark tools:publish
~~~

### Manlually

If you don't need all Tools, copy the EntityRelationTrait.php from `.\vendor\rakoitde\ci4-tools\src\Entities` into `ROOTPATH\App\Entities`.
After that your have to rename the namespace to `App\Entities`

# Usage

## Help
~~~ shell
% php spark help update:model
~~~

~~~ shell
CodeIgniter v4.2.0 Command Line Tool - Server Time: 2022-11-22 00:39:54 UTC-06:00

Usage:
  update:model <name> [options]

Description:
  Updates an existing model file.

Arguments:
  name  The model class name.

Options:
  --namespace      Set root namespace. Default: "APP_NAMESPACE".
  --suffix         Append the component title to the class name (e.g. User => UserModel).
  --useTimestamps  Enable use of Timestamps and add missing fields to table
  --force          Force overwrite existing file and modify table if needed
~~~

## Update

After your have make a model with `php spark make:model ...` an if the table exists, you can update the model with `php spark update:model....`.

Without the `--force` Option, a preview will shown. 