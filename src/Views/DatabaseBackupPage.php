

<div class="container-fluid">
  <div class="row">
    <div class="col-3">.col-3</div>
    <div class="col-7">.col-4<br>Since 9 + 4 = 13 &gt; 12, this 4-column-wide div gets wrapped onto a new line as one contiguous unit.</div>
    <div class="col-2">.col-6<br>Subsequent columns continue along the new line.</div>
  </div>
</div>

<div class="accordion" id="tables">

<?php foreach ($tables as $table) : ?>

  <div class="card">
    <div class="card-header_ p-0" id="heading_table_<?= $table ?>">
      <h2 class="mb-0">
        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#table_<?= $table ?>" aria-expanded="true" aria-controls="table_<?= $table ?>">
          <?= $table ?>
        </button>
      </h2>
    </div>

    <div id="table_<?= $table ?>" class="collapse" aria-labelledby="heading_table_<?= $table ?>" data-parent="#tables">
      <div class="card-body">
    	<pre class="line-num_bers"><code class="language-sql" data-table="<?= $table ?>"></code></pre>
      </div>
    </div>
  </div>

<?php endforeach ?>
</div>

