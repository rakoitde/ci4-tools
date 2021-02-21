<!-- Layout -->
<?= $this->extend('App\Views\DefaultSidebarLayout') ?>

<!-- Custom CSS -->
<?= $this->section('custom_css') ?>
<?= $this->endSection() ?>

<!-- Sidebar -->
<?= $this->section('sidebar') ?>
<?= $this->include('App\Views\DefaultSidebar') ?>
<?= $this->endSection() ?>

<!-- Content -->
<?= $this->section('content') ?>
<!-- ################ Start: Content ################ --> 

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

<!-- Start: Tab Content -->
<div class="tab-content" id="tablist">
  <!-- Start: Project Tab Project -->
  <div class="tab-pane fade show active" id="tab1" role="tabpanel" aria-labelledby="tab1-tab">
    < ?= $this->include('pages/_tab1') ?>
  </div>  <!-- End: Project Tab Project -->
  <!-- Start: Project Tab Database -->
  <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
    < ?= $this->include('layout/_tab2') ?>
  </div>
  <!-- Start: Project Tab Controller -->
  <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="tab3-tab">
    < ?= $this->include('layout/_tab3') ?>
  </div>
  <!-- Start: Project Tab Controller -->
  <div class="tab-pane fade" id="tab4" role="tabpanel" aria-labelledby="tab4-tab">
    < ?= $this->include('layout/_alert') ?>
  </div>
</div>
<!-- End: Tab Content -->
 <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br> 
<!-- ################ Stop: Content ################ -->
<?= $this->endSection() ?>


<!-- Custom JS -->
<?= $this->section('custom_js') ?>

  <script type="text/javascript">
    console.log("Custom JS");
  </script>

<?= $this->endSection() ?>