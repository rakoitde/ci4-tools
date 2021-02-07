
<!--
# Colors: primary, secondary, success, danger, warning, info, light, dark
-->
<?php
	$alerts = $alerts ?? [];
?>

<!-- Start: Alert -->
<?php foreach ($alerts as $alert) : ?>
<div class="alert alert-<?= $alert['color'] ?? 'warning' ?> alert-dismissible fade show" role="alert">
	<?= $alert['html'] ?>
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
<?php endforeach ?>
<!-- End: Alert -->
