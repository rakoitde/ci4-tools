<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Ralf Kornberger">

    <title>Rakoitde::Layout</title>

    <!-- Bootstrap core CSS -->
    <link href="<?= Base_Url('css/bootstrap.css') ?>" rel="stylesheet">

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
    <!-- Start: Breadcrumb -->
    <?= $this->include('App\Views\DefaultBreadcrumb') ?>
    <!-- End: Breadcrumb -->
    <!-- Start: Alerts -->
    <div id="alerts">
    <?= $this->include('App\Views\DefaultAlert') ?>
    </div>
    <!-- End: Alerts -->
    <?= $this->renderSection('content') ?>
</div>


</body>

<script src="<?= Base_Url('js/jquery-3.5.1.min.js') ?>"></script>
<script src="<?= Base_Url('js/bootstrap.bundle.min.js') ?>"></script>
<!-- Start: Custom JavaScript -->
<?= $this->renderSection('custom_js') ?>
<!-- End: Custom JavaScript -->

</html>
