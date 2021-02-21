<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Ralf Kornberger">

    <title>Rakoitde::Layout Sidebar</title>

    <!-- Bootstrap core CSS -->
    <link href="<?= Base_Url('css/bootstrap.css') ?>" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?= Base_Url('css/na_vbar-top-fixed.css') ?>" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?= Base_Url('css/sidebar.css') ?>" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <?= $this->renderSection('custom_css') ?>

</head>

<body>

<!-- Start: Navigation -->
<?= $this->include('App\Views\DefaultNavigation') ?>
<!-- End: Navigation -->

<!-- Start: Sidebar -->
<div class="container-fluid">
    <div class="row">
        <span class="col-12 col-md-3 col-lg-2 bg-light d-none d-md-block sidebar2"></span>
        <?= $this->renderSection('sidebar') ?>
        <main role="main" class="main col-12 col-md-9 col-lg-10 px-0">
        <!-- Start: Breadcrumb -->
        <?= $this->include('App\Views\DefaultBreadcrumb') ?>
        <!-- End: Breadcrumb -->
        <div class="container-fluid">
        <!-- Start: Alerts -->
        <div id="alerts">
        <?= $this->include('App\Views\DefaultAlert') ?>
        </div>
        <!-- End: Alerts -->
        <?= $this->renderSection('content') ?>
        </div>
        
        </main>
    </div>
</div>

<!-- Start: Custom JavaScript -->
<?= $this->renderSection('custom_js') ?>
<!-- End: Custom JavaScript -->

</body>

<script src="<?= Base_Url('js/jquery-3.5.1.min.js') ?>"></script>
<script src="<?= Base_Url('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= Base_Url('js/dashboard.js') ?>"></script>

</html>
