

<div class="container-fluid bg-light">
  <div class="row pt-3">

	<!-- Menü Left -->
	<div class="col-lg-3 col-md-6 col-sm-12 col-12 mb-3">
	  <div class="list-group list-group-flush">
		<button type="button" class="list-group-item list-group-item-action list-group-item-dark d-flex justify-content-between align-items-center" aria-current="true">
		  <h4>Backup Jobs</h4> <h4><i id="btnAddJob" class="bi bi-plus-circle"></i></h4>
		</button>
<?php foreach ($backupjobs as $j) : ?>
		<div class="btn-group d-flex justify-content-between align-items-center list-group-item list-group-item-action">
		  <a href="<?= 'backupdb/' . $j->id ?>"><?= $j->jobname ?></a>
		  <i type="" class="dropdown-toggle bi bi-three-dots-ver_tical" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></i>
		  <div class="dropdown-menu dropdown-menu-right">
			<button class="dropdown-item" type="button">Action</button>
			<button class="dropdown-item" type="button">Another action</button>
			<button class="dropdown-item" type="button">Something else here</button>
		  </div>
		</div>
<?php endforeach ?>
	  </div>
	</div>

<?php if ($job) : ?>
	<!-- Job Details -->
	<div class="col-lg-6 col-md-6 col-sm-12 col-12 mb-3">
	  <ul class="list-group list-group-flush">
		<li class="list-group-item d-flex justify-content-between align-items-center">
		  <div class="h4"><?= $job->jobname ?>&nbsp;&nbsp;&nbsp;<i class="bi bi-pencil"></i></div>
		  <button class="btn btn-sm btn-outline-success" data-id="<?= $job->id ?>">Start</button>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-hdd-rack-fill"></i>&nbsp;&nbsp;&nbsp;Server</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1">Group: <?= $job->dbgroup ?> Hostname: <?= $db->hostname ?>:<?= $db->port ?></p>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-server"></i>&nbsp;&nbsp;&nbsp;Datenbank</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1"><?= $db->database ?></p>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-table"></i>&nbsp;&nbsp;&nbsp;Tabellen</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1"><?= count(explode(',', $job->tables)); ?> von <?= count($tables); ?>: <?= $job->tables ?></p>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-folder2-open"></i>&nbsp;&nbsp;&nbsp;Speicherort</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1"><?= $job->destination ?></p>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-calendar3"></i>&nbsp;&nbsp;&nbsp;Zeitplan</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1">Kein Zeitplan aktiviert</p>
		</li>
		<li class="list-group-item justify-content-between align-items-center">
		  <div class="d-flex w-100 justify-content-between">
			<h5 class="mb-1"><i class="h3 bi bi-envelope-open"></i>&nbsp;&nbsp;&nbsp;Benachrichtigung</h5>
			<button class="h6 btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i></button>
		  </div>
		  <p class="mb-1"><?= $job->mailto ?></p>
		</li>

	  </ul>
	</div>
<?php endif ?>
	<!-- History -->
	<div class="col-lg-3 col-md-12 col-sm-12 col-12 mb-3">
	  <ul class="list-group list-group-flush">
		<li class="list-group-item"><h4>Backups & Restore</h4></li>
		<li class="list-group-item">db_rakoitde_2021-09-12_11-23-59.sql</li>
		<li class="list-group-item">tbl_2021-09-12_11-23-59.sql</li>
	  </ul>
	</div>

  <!-- Start: MultiStep Form -->
  <div class="wizard" id="add_job_wizard" data-title="Server Deployment ">
	  <div class="wizard-card" data-cardname="card1">
		  <h3>Server / Datenbank</h3>
		  <input class="form-control form-control-sm" id="Vorname" name="Vorname" value="Horst">
		  <input class="form-control form-control-sm" id="Nachname" name="Nachname" value="Murksi">
	  </div>

	  <div class="wizard-card" data-cardname="card2">
		  <h3>Tabellen</h3>
		  <input class="form-control form-control-sm" name="Betriebssystem" value="Windows Server 2016">
		  <input class="form-control form-control-sm" name="Sprache" value="deutsch">
	  </div>

	  <div class="wizard-card" data-cardname="card3">
		  <h3>Speicherort</h3>
		  <input class="form-control form-control-sm" name="Option1" value="IIS">
		  <input class="form-control form-control-sm" name="Option2" value="RDP">
	  </div>
	  <div class="wizard-card" data-cardname="card3">
		  <h3>Zeitplan</h3>
		  <input class="form-control form-control-sm" name="Option1" value="IIS">
		  <input class="form-control form-control-sm" name="Option2" value="RDP">
	  </div>
	  <div class="wizard-card" data-cardname="card3">
		  <h3>Benachrichtigung</h3>
		  <input class="form-control form-control-sm" name="Option1" value="ralf.kornberger">
	  </div>
	  <div class="wizard-card overflow-auto" data-cardname="card4">
		  <h3>Zusammenfassung</h3>
		  <div class="overflow-auto">
			<div class="form-group">
			  <label for="exampleInputEmail1">Email address</label>
			  <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
			  <small id="emailHelp" class="form-text text-muted">We'll never share your email with anyone else.</small>
			</div>
			<div class="form-group">
			  <label for="exampleInputPassword1">Password</label>
			  <input type="password" class="form-control" id="exampleInputPassword1">
			</div>
			<div class="form-group form-check">
			  <input type="checkbox" class="form-check-input" id="exampleCheck1">
			  <label class="form-check-label" for="exampleCheck1">Check me out</label>
			</div>
		  </div>
	  </div>

	  <!-- begin special status cards below: -->
	  <div class="wizard-success">
		  submission succeeded!
	  </div>

	  <div class="wizard-error">
		  submission had an error
	  </div>

	  <div class="wizard-failure">
		  submission failed
	  </div>

  </div>
  <!-- End: MultiStep Form -->

  </div>
</div>
