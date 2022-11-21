
# CodeIgniter Database Tools

## Relations

### EntityRelationTrait

~~~ php
<?php

namespace App\Entities;

use CodeIgniter\Entity;
use App\Entities\EntityTrait;

class UserEntity extends Entity
{

    use EntityRelationTrait;

    public function permissions()
    {
        return $this->findManyOver(PermissionsUsersModel::class, PermissionsModel::class, 'permission_id', 'user_id');
    }

    public function notes()
    {
        return $this->findMany(UsernotesModel::class);
    }

}
~~~


## Class Reference

- many
- findMany
- manyOver
- findManyOver

### many

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

### findMany

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

### manyOver

~~~ php
function manyOver( string $over_class, string $many_class, [ ?string $manyForeignKey = null [ , ?string $thisForeignKey = null ]]) {}
~~~

**Parameters:**
- **$over_class** (`string`) - Name of the over class
- **$many_class** (`string`) - Name of the class with many entities
- **$manyForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`
- **$thisForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the entity-many model filtered by the foreign key that matches the ID of the current entity.

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

### findManyOver

~~~ php
function findManyOver( string $over_class, string $many_class, [ ?string $manyForeignKey = null [ , ?string $thisForeignKey = null ]]) {}
~~~

**Parameters:**
- **$over_class** (`string`) - Name of the over class
- **$many_class** (`string`) - Name of the class with many entities
- **$manyForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`
- **$thisForeignKey** (`?string`) - The foreign key in the class with many entities. Default: `table_pk` = `user_id`

**Returns:**
- Returns the entity-many model filtered by the foreign key that matches the ID of the current entity.

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