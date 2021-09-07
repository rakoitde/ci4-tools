<!-- Layout -->
<?= $this->extend('App\Views\DefaultLayout') ?>

<!-- Custom CSS -->
<?= $this->section('custom_css') ?>
<?= $this->endSection() ?>

<!-- Content -->
<?= $this->section('content') ?>
<!-- ################ Start: Content ################ --> 

<!-- Table Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 mb-3 border-bottom">
    <h1 class="h3">Gruppen</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?= $pager->links() ?>
        <form>
            <div class="input-group input-group-sm pl-3">
                <select name="perPage" class="custom-select custom-select-sm w-auto">
                    <option value="10" <?= $request->getGet('perPage')=="10" ? 'selected' : '';  ?>>10</option>
                    <option value="25" <?= $request->getGet('perPage')=="25" ? 'selected' : '';  ?>>25</option>
                    <option value="50" <?= $request->getGet('perPage')=="50" ? 'selected' : '';  ?>>50</option>
                    <option value="100"<?= $request->getGet('perPage')=="100" ? 'selected' : '';  ?>>100</option>
                </select>
                <input type="search" name="ts" class="form-control form-control-sm w-auto" aria-label="Text input with segmented dropdown button" value="<?= $request->getGet('ts') ?>"></input>
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="submit">Suchen</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Start: Header -->
<nav class="navbar navbar-expand-lg navbar-light border-bottom px-0">
  <p class="h4 my-auto pr-5">Navbar:  <span class="text-secondary">Detail</span></p>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
    <!-- Start: Tabs -->
    <ul class="nav nav-tabs mr-auto justify-content-center" id="tabfff" role="tablist">
      <div class="nav" id="project_navtab_" role="tablist">
        <a class="nav-link active" id="tab1-tab" data-toggle="tab" href="#tab1"  role="tab" aria-controls="tab1" aria-selected="true">Tab1</a>
        <a class="nav-link" id="tab2-tab" data-toggle="tab" href="#tab2"  role="tab" aria-controls="tab2" aria-selected="false">Tab2</a>
        <a class="nav-link" id="tab3-tab" data-toggle="tab" href="#tab3"  role="tab" aria-controls="nav-profile" aria-selected="false">Tab3</a>
      </div>
    </ul>
    <!-- End: Tabs -->
    <!-- Start: Form -->
    <form class="form-inline my-2 my-lg-0">
      <div class="input-group input-group-sm">
        <div class="input-group-prepend">
          <span class="input-group-text" id="basic-addon1">@</span>
        </div>
        <input type="text" class="form-control" placeholder="Username" aria-label="Username" aria-describedby="basic-addon1">
        <button type="button" class="btn btn-sm btn-outline-danger ml-3">Danger</button>
      </div>
    </form>
    <!-- End: Form -->
  </div>
</nav>
<!-- End: Header -->

<!-- Datenbank Liste -->
<table class="table table-sm table-hover">
    <caption>Datensatz 
    <?= min(($pager->getCurrentPage()*$pager->getPerPage())-$pager->getPerPage()+1, $pager->getTotal()) ?> bis 
    <?= min($pager->getCurrentPage()*$pager->getPerPage(), $pager->getTotal()) ?> von <?= $pager->getTotal() ?></caption>
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Objekt ID</th>
            <th scope="col">Name</th>
            <th scope="col">Beschreibung</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($entities as $entity) : ?>
        <tr>
            <td scope="row"><?= $entity->id ?></td>
            <td><a href="#" target="_blanc"><?= $entity->object_id ?></a></td>
            <td><?= $entity->name ?></td>
            <td><?= $entity->description ?></td>
            <td class="text-right">
            <div class="btn-group btn-group-sm mr-2">
                <a role="button" class="btn btn-outline-secondary" href="<?= base_url("table") ?>">Ändern</a>
                <a role="button" class="btn btn-outline-secondary" href="<?= base_url("table") ?>">Löschen</a>
            </div>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<!-- ################ Stop: Content ################ -->
<?= $this->endSection() ?>


<!-- Custom JS -->
<?= $this->section('custom_js') ?>

  <script type="text/javascript">
    console.log("Custom JS");
  </script>

<?= $this->endSection() ?>