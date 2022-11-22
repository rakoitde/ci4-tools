
# CodeIgniter Database Relations

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

### 

# Usage

Simply enhance your entities with the `EntityRelationTrait` as follows

~~~ php
use App\Entities\EntityRelationTrait;

class YourEntity extends Entity
{
    use EntityRelationTrait;

    public function notes()
    {
        return $this->many(NotesModel::class);
    }
~~~

# Class Reference

- [many](#many) - Returns the entity-many model filtered by the foreign key that matches the ID of the current entity
- [findMany](#findmany) - Returns the entities of the entity-many model filtered by the foreign key that matches the ID of the current entity
- [manyOver](#manyover) - Returns the many-to-many model filtered by the foreign key that matches the ID of the current entity
- [findManyOver](#findmanyover) - Returns the many-to-many entities filtered by the foreign key that matches the ID of the current entity

## many

~~~ php
function many( string $many_class [ , ?string $manyForeignKey = null ] ) {}
~~~

**Parameters:**
- **$many_class** (`string`) - Name of the class with many entities
- **$manyForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the entity-many model filtered by the foreign key that matches the ID of the current entity.

**Example:**

~~~ php
use App\Entities\EntityTrait;

class YourEntity extends Entity
{
    use EntityRelationTrait;

    public function notes()
    {
        return $this->many(NotesModel::class);
    }
~~~

~~~ php
class YourController extends BaseController
{
    public function show($id)
    {
        $yourModel = model(YourModel::class);
        $yourEntity = $yourModel->find($id);

        $nodesModel = $yourEntity->notes();

        $data = [
            'entity' => $yourEntity,
            'notes'  => $notesModel->findAll(),
        ];

        return view('index', $data);
    }
~~~

## findMany

~~~ php
function findMany(string $many_class [ , ?string $manyForeignKey = null ] ) {}
~~~

**Parameters:**
- **$many_class** (string) - Name of the class with many entities
- **$manyForeignKey** (?string) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the entities of the entity-many model filtered by the foreign key that matches the ID of the current entity.

**Example:**

~~~ php
use App\Entities\EntityTrait;

class YourEntity extends Entity
{
    use EntityRelationTrait;

    public function allNotes()
    {
        return $this->findMany(NotesModel::class);
    }
~~~

~~~ php
class YourController extends BaseController
{
    public function show($id)
    {
        $yourModel = model(YourModel::class);
        $yourEntity = $yourModel->find($id);

        $allNotesEntities = $yourEntity->allNotes();

        $data = [
            'entity' => $yourEntity,
            'notes'  => $allNotesEntities,
        ];

        return view('index', $data);
    }
~~~

## manyOver

~~~ php
function manyOver( string $over_class, string $many_class, [ ?string $manyForeignKey = null [ , ?string $thisForeignKey = null ]]) {}
~~~

**Parameters:**
- **$over_class** (`string`) - Name of the over class
- **$many_class** (`string`) - Name of the class with many entities
- **$manyForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`
- **$thisForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the many-2-many model filtered by the foreign key that matches the ID of the current entity.

**Example:**

~~~ php
use App\Entities\EntityTrait;

class UserEntity extends Entity
{
    use EntityRelationTrait;

    public function Groups()
    {
        return $this->manyOver(UsersGroupsModel::class, GroupsModel::class);
    }
~~~

~~~ php
class UsersController extends BaseController
{
    public function show($id)
    {
        $usersModel = model(UsersModel::class);
        $userEntity = $usersModel->find($id);

        $groupsModel = $userEntity->Groups();

        $data = [
            'entity' => $userEntity,
            'groups' => $groupsModel->findAll(),
        ];

        return view('index', $data);
    }
~~~

## findManyOver

~~~ php
function findManyOver( string $over_class, string $many_class, [ ?string $manyForeignKey = null [ , ?string $thisForeignKey = null ]]) {}
~~~

**Parameters:**
- **$over_class** (`string`) - Name of the over class
- **$many_class** (`string`) - Name of the class with many entities
- **$manyForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`
- **$thisForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the many-to-many entities filtered by the foreign key that matches the ID of the current entity.

**Example:**

~~~ php
use App\Entities\EntityTrait;

class UserEntity extends Entity
{
    use EntityRelationTrait;

    public function Groups()
    {
        return $this->findManyOver(UsersGroupsModel::class, GroupsModel::class);
    }
~~~

~~~ php
class UsersController extends BaseController
{
    public function show($id)
    {
        $usersModel = model(UsersModel::class);
        $userEntity = $usersModel->find($id);

        $groupsEntities = $userEntity->Groups();

        $data = [
            'entity' => $userEntity,
            'groups' => $groupsEntities,
        ];

        return view('index', $data);
    }
~~~