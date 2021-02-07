<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Ralf Kornberger">

    <title>Rakoitde:Layout</title>

    <!-- Bootstrap core CSS -->
    <link href="<?= Base_Url('css/bootstrap.css') ?>" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?= Base_Url('css/navbar-top-fixed.css') ?>" rel="stylesheet">

    <?= $this->renderSection('custom_css') ?>
    
</head>

<body>
<!-- Start: Navigation -->
<?= $this->include('{namespace}\DefaultNavigation') ?>
<!-- End: Navigation -->

<div class="container-fluid">

<?= $this->renderSection('content') ?>

</div>

<!-- Start: Custom JavaScript -->
<?= $this->renderSection('custom_js') ?>
<!-- End: Custom JavaScript -->

</body>

<script src="<?= Base_Url('js/jquery-3.5.1.min.js') ?>"></script>
<script src="<?= Base_Url('js/bootstrap.bundle.min.js') ?>"></script>

</html>
