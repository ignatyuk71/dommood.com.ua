@extends('layouts.app')

@section('title', config('app.name') . ' - інтернет-магазин для дому')
@section('meta_description', 'Стартова сторінка нового e-commerce сайту DomMood.')

@section('content')
    <section class="section-spacing">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="text-uppercase small fw-semibold text-muted-soft mb-3">Новий сайт</p>
                    <h1 class="display-5 fw-semibold mb-4">DomMood: e-commerce платформа з чистою архітектурою</h1>
                    <p class="lead text-muted-soft mb-4">
                        Базовий Laravel + Vue + Bootstrap 5 каркас готовий для каталогу, checkout, SEO-сторінок, аналітики та рекламного tracking.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-dark btn-lg" href="#">Почати з каталогу</a>
                        <a class="btn btn-outline-secondary btn-lg" href="#">Структура</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded-3 p-4 bg-light">
                        <h2 class="h5 fw-semibold mb-3">Перші модулі</h2>
                        <ul class="list-unstyled mb-0 text-muted-soft">
                            <li class="py-2 border-bottom">Catalog: категорії, товари, варіанти, фільтри</li>
                            <li class="py-2 border-bottom">Checkout: кошик, замовлення, оплата, доставка</li>
                            <li class="py-2 border-bottom">Marketing: SEO, GA4, GTM, Meta CAPI</li>
                            <li class="py-2">Content: landing pages, банери, промо-блоки</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
