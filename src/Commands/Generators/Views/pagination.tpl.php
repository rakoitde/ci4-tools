<!--

# ul
- Size: pagination-lg, pagination-sm
- Alignment: justify-content-center, justify-content-end

# li
- active
- disabled + add <a ... tabindex="-1">


-->
<nav aria-label="...">
    <ul class="pagination justify-content-end">
        <li class="page-item disabled">
            <a class="page-link" href="#" aria-label="Previous" tabindex="-1">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        <li class="page-item disabled" >
            <a class="page-link" href="#" tabindex="-1">Previous</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="#">1</a>
        </li>
        <li class="page-item active">
            <a class="page-link" href="#">2 <span class="sr-only">(current)</span></a>
        </li>
        <li class="page-item">
            <a class="page-link" href="#">3</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="#">Next</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="#" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
                <span class="sr-only">Next</span>
            </a>
        </li>
    </ul>
</nav>