<!-- Layout -->
<?= $this->extend('{lastQualifiedLayoutName}') ?>


<!-- Custom CSS -->
<?= $this->section('custom_css') ?>

  <script type="text/javascript">
    console.log("Custom CSS");
  </script>

<?= $this->endSection() ?>

<!-- Content -->
<?= $this->section('content') ?>

<!-- Start: Breadcrumb -->
<?= $this->include('{lastQualifiedBreadcrumbName}') ?>
<!-- End: Breadcrumb -->

<div class="container-fluid">
<!-- Start: Alerts -->
<div id="alerts">
<?= $this->include('{lastQualifiedAlertName}') ?>
</div>
<!-- End: Alerts -->

<!-- Start: Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 border-bottom">
  <h1 class="h2">Header:  <span class="text-secondary">Detail</span></h1>

  <!-- Start: Tabs -->
  <div class="btn-toolbar mb-md-0">
    <ul class="nav nav-tabs mb-3" id="tab" role="tablist">
      <div class="nav nav-pill" id="project_navtab_" role="tablist">
        <a class="nav-link active" id="tab1-tab" data-toggle="tab" href="#tab1"  role="tab" aria-controls="nav-home" aria-selected="true">Tab1</a>
        <a class="nav-link" id="tab2-tab" data-toggle="tab" href="#tab2"  role="tab" aria-controls="nav-profile" aria-selected="false">Tab2</a>
        <a class="nav-link" id="tab3-tab" data-toggle="tab" href="#tab3"  role="tab" aria-controls="nav-profile" aria-selected="false">Tab3</a>
      </div>
    </ul>
  </div>
  <!-- End: Tabs -->
</div>
<!-- End: Page Header -->

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
  

</div>
<?= $this->endSection() ?>


<!-- Custom JS -->
<?= $this->section('custom_js') ?>

  <script type="text/javascript">
    console.log("Custom JS");
  </script>

<?= $this->endSection() ?>