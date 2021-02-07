<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Ralf Kornberger">

    <title>Rakoitde:Layout Sidebar</title>

    <!-- Bootstrap core CSS -->
    <link href="<?= Base_Url('css/bootstrap.css') ?>" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?= Base_Url('css/navbar-top-fixed.css') ?>" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?= Base_Url('css/sidebar.css') ?>" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <?= $this->renderSection('custom_css') ?>

</head>

<body>

<!-- Start: Navigation -->
<?= $this->include('{namespace}\DefaultNavigation') ?>
<!-- End: Navigation -->

<div class="container-fluid">
  <div class="row">

    <!-- Start: Sidebar -->
    <nav class="col-sm-4 col-md-3 col-lg-2 d-md-block bg-light sidebar collapse width show" id="sidebar" >
        <div class="sidebar-sticky pt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="<?= Base_Url('#') ?>">
                      <i class="bi-house-door"></i>
                      Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <i class="bi-file"></i>
                      Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <i class="bi-bag"></i>
                      Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="users"></span>
                      Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="bar-chart-2"></span>
                      Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="layers"></span>
                      Integrations
                    </a>
                </li>
            </ul>

            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Saved reports</span>
                <a class="d-flex align-items-center text-muted" href="<?= Base_Url('#') ?>" aria-label="Add a new report">
                    <span data-feather="plus-circle"></span>
                </a>
            </h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="file-text"></span>
                      Current month
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="file-text"></span>
                      Last quarter
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="file-text"></span>
                      Social engagement
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= Base_Url('#') ?>">
                      <span data-feather="file-text"></span>
                      Year-end sale
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <!-- End: Sidebar -->
    
    <!-- Start: Content -->
    <div class="col-12 col-sm-8 col-md-9 col-lg-10 ml-sm-auto px-md-4">
  
<?= $this->renderSection('content') ?>

    </div>
    <!-- End: Content -->

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
