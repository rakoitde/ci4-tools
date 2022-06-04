<!-- Layout -->
<?= $this->extend('App\Views\DefaultLayout') ?>

<!-- Custom CSS -->
<?= $this->section('custom_css') ?>

<?= link_tag('css/bootstrap-wizard.css'); ?>
<?= link_tag('css/prism.css'); ?>
<?= $this->endSection() ?>

<!-- Content -->
<?= $this->section('content') ?>
<!-- ################ Start: Content ################ -->

<!-- Start: Header -->
<nav class="navbar navbar-expand-lg navbar-light border-bottom px-0">
  <p class="h4 my-auto pr-5">Datenbank:  <span class="text-secondary">Backup</span></p>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
	<span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
	<!-- Start: Tabs -->
	<ul class="nav nav-tabs mr-auto justify-content-center" id="tabfff" role="tablist">
	  <div class="nav" id="project_navtab_" role="tablist">
		<a class="nav-link active" id="tab1-tab" data-toggle="tab" href="#tab1"  role="tab" aria-controls="tab1" aria-selected="true">Backup Jobs</a>
		<a class="nav-link" id="tab2-tab" data-toggle="tab" href="#tab2"  role="tab" aria-controls="tab2" aria-selected="false">Backup</a>
		<a class="nav-link" id="tab3-tab" data-toggle="tab" href="#tab3"  role="tab" aria-controls="nav-profile" aria-selected="false">Sync</a>
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
	<?= $this->include('Rakoitde\Tools\Views\DatabaseBackupJobsPage') ?>
  </div>  <!-- End: Project Tab Project -->
  <!-- Start: Project Tab Database -->
  <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
	<?= $this->include('Rakoitde\Tools\Views\DatabaseBackupPage') ?>
  </div>
  <!-- Start: Project Tab Controller -->
  <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="tab3-tab">
	<?= $this->include('Rakoitde\Tools\Views\DatabaseBackupSync') ?>
  </div>
  <!-- Start: Project Tab Controller -->
  <div class="tab-pane fade" id="tab4" role="tabpanel" aria-labelledby="tab4-tab">
	< ?= $this->include('layout/_alert') ?>
  </div>
</div>
<!-- End: Tab Content -->

<!-- ################ Stop: Content ################ -->
<?= $this->endSection() ?>


<!-- Custom JS -->
<?= $this->section('custom_js') ?>

  <?= script_tag('js/bootstrap-wizard.js'); ?>
  <?= script_tag('js/prism.js'); ?>


  <script type="text/javascript">

	$(function() {

		// Show sql snippet
		$('.collapse').on('show.bs.collapse', function () {
		  // $(this).find("code").load("db/auth_groups_permissions/backup_");
		  var $code = $(this).find("code")
		  var table = $code.data("table")
		  if ($code.data("loaded")!="true") {
			$.get("db/"+table+"/backup", function( html ) {
				$code.html(Prism.highlight( html, Prism.languages.sql, 'sql'))
				$code.data("loaded","true")
			});
		  }
		})

		// Load Sync
		$("#sync_sql_commands").load("comparedb");

		// Wizard
		$.fn.wizard.logging = true;

		var options = {
		  show:false,
		  contentHeight:400,
		  contentWidth:700,
		  submitUrl: 'wizard',
		 };

		wizard = $("#add_job_wizard").wizard(options);
		//wizard.setTitle("Client Deployment");
		//wizard.setSubtitle("192.168.178.33");
		//wizard.show();

		wizard.cards["card1"].on("validate", function(card) {

		  var input = card.el.find("#Vorname");
		  var name = input.val();
		  console.log("Name: "+name);
		  if (name == "") {
			  card.wizard.errorPopover(input, "Name cannot be empty");
			  return false;
		  }
		  return true;

		});

		$("#btnAddJob").click(function() {
		   wizard.show();
		});

		$("#btnStartBackup").click(function() {
		  alert("Backup ist gestartet")
		})

	});
  </script>

<?= $this->endSection() ?>
