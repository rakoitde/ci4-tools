

<!--
# table
- Hover:      table-hover 
- Dark:       table-dark
- Small:      table-sm
- Striped:    table-striped
- Border:     table-bordered, table-borderless
- Responsive: table-responsive{-sm|-md|-lg|-xl}

# thead
- Header: thead-light, thead-dark

# tr,td,th class
table-active
table-primary
table-secondary
table-success
table-danger
table-warning
table-info
table-light
table-dark

-->

<!-- ?= $this->include('layout/_navpagination') ? -->

<table class="table table-sm table-responsive-md">
    <caption>List of users</caption>
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">First</th>
            <th scope="col">Last</th>
            <th scope="col">Handle</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th scope="row">1</th>
            <td>Mark</td>
            <td class="table-warning">Otto</td>
            <td>@mdo</td>
        </tr>
        <tr class="table-success">
            <th scope="row">2</th>
            <td>Jacob</td>
            <td>Thornton</td>
            <td>@fat</td>
        </tr>
<?php 
    $rows[]=['a'=>'3','b'=>'Larry the Bird 3','c'=>'@twitter 3'];
    $rows[]=['a'=>'4','b'=>'Larry the Bird 4','c'=>'@twitter 4'];
    $rows[]=['a'=>'5','b'=>'Larry the Bird 5','c'=>'@twitter 5'];
?>
        <?php foreach ($rows as $row) : ?>
        <tr>
            <th class="table-dark" scope="row"><?= $row['a'] ?></th>
            <td colspan="2"><?= $row['b'] ?></td>
            <td><?= $row['c'] ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>

<!-- ?= $this->include('layout/_navpagination') ? -->
