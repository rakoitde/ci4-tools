
<?php
	$breadcrumb = [];
	$breadcrumb[] = ['href'=>'#', 'text'=>'Home',    'active'=>false];
	$breadcrumb[] = ['href'=>'#', 'text'=>'Library', 'active'=>false];
	$breadcrumb[] = ['href'=>'#', 'text'=>'Data',    'active'=>true];
?>

<!-- Start: Breadcrumb -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
<?php foreach ($breadcrumb as $bc) : ?>
<?php if ($bc['active']) : ?>
    <li class="breadcrumb-item active" aria-current="page"><a href="<?= Base_Url($bc['href']) ?>"><?= $bc['text'] ?></a></li>
<?php else : ?>
    <li class="breadcrumb-item"><a href="<?= Base_Url($bc['href']) ?>"><?= $bc['text'] ?></a></li>
<?php endif ?>
<?php endforeach ?>
  </ol>
</nav>
<!-- End: Breadcrumb -->