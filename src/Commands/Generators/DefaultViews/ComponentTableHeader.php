<!-- Start: Header -->
<nav class="navbar navbar-expand-lg navbar-light border-bottom px-0">
    <p class="h4 my-auto pr-5">Navbar:  <span class="text-secondary">Table</span></p>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
        <?= $pager->links() ?>
        <form>
            <div class="input-group input-group-sm pl-3">
                <select name="perPage" class="custom-select custom-select-sm w-auto">
                    <option value="10" <?= $request->getGet('perPage')=="10" ? 'selected' : '';  ?>>10</option>
                    <option value="25" <?= $request->getGet('perPage')=="25" ? 'selected' : '';  ?>>25</option>
                    <option value="50" <?= $request->getGet('perPage')=="50" ? 'selected' : '';  ?>>50</option>
                    <option value="100"<?= $request->getGet('perPage')=="100" ? 'selected' : '';  ?>>100</option>
                </select>
                <input type="search" name="ts" class="form-control form-control-sm w-auto" aria-label="Text input with segmented dropdown button" value="<?= $request->getGet('ts') ?>">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="submit">Suchen</button>
                </div>
            </div>
        </form>
    </div>
</nav>
<!-- End: Header -->