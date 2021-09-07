<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
    <a class="navbar-brand" href="<?= Base_Url('#') ?>"><span class="text-warning">Rakoitde:</span>Layout</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarCollapse">
    <ul class="navbar-nav mr-auto">
        <li class="nav-item active">
            <a class="nav-link" href="<?= Base_Url('#') ?>">Home <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= Base_Url('#') ?>">Link</a>
        </li>
        <li class="nav-item">
            <a class="nav-link disabled" href="<?= Base_Url('#') ?>" tabindex="-1" aria-disabled="true">Disabled</a>
        </li>
    </ul>
    <form class="form-inline mt-2 mt-md-0">
        <input class="form-control form-control-sm mr-sm-2" type="text" placeholder="Search" aria-label="Search">
        <button class="btn btn-sm btn-outline-warning my-2 my-sm-0" type="submit">Search</button>
    </form>
    </div>
</nav>