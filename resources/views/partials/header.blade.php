<header class="border-bottom bg-white">
    <nav class="navbar navbar-expand-lg container py-3">
        <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Відкрити меню">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav ms-auto gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="#">Каталог</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Новинки</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Доставка</a></li>
                <li class="nav-item"><a class="btn btn-dark ms-lg-2" href="#">Кошик</a></li>
            </ul>
        </div>
    </nav>
</header>
