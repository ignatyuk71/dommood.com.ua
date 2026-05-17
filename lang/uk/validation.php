<?php

return [
    'accepted' => 'Поле ":attribute" потрібно прийняти.',
    'array' => 'Поле ":attribute" має бути масивом.',
    'boolean' => 'Поле ":attribute" має бути так або ні.',
    'date' => 'Поле ":attribute" має бути коректною датою.',
    'distinct' => 'Поле ":attribute" містить дубльоване значення.',
    'email' => 'Поле ":attribute" має бути коректною email-адресою.',
    'exists' => 'Вибране значення для поля ":attribute" некоректне.',
    'image' => 'Поле ":attribute" має бути зображенням.',
    'in' => 'Вибране значення для поля ":attribute" некоректне.',
    'integer' => 'Поле ":attribute" має бути цілим числом.',
    'mimes' => 'Поле ":attribute" має бути файлом типу: :values.',
    'numeric' => 'Поле ":attribute" має бути числом.',
    'required' => 'Поле ":attribute" обовʼязкове.',
    'string' => 'Поле ":attribute" має бути текстом.',
    'unique' => 'Таке значення поля ":attribute" вже використовується.',

    'max' => [
        'array' => 'Поле ":attribute" не може містити більше ніж :max елементів.',
        'file' => 'Файл ":attribute" не може бути більшим за :max КБ.',
        'numeric' => 'Поле ":attribute" не може бути більшим за :max.',
        'string' => 'Поле ":attribute" не може бути довшим за :max символів.',
    ],

    'min' => [
        'array' => 'Поле ":attribute" має містити щонайменше :min елементів.',
        'file' => 'Файл ":attribute" має бути не меншим за :min КБ.',
        'numeric' => 'Поле ":attribute" має бути не меншим за :min.',
        'string' => 'Поле ":attribute" має містити щонайменше :min символів.',
    ],

    'size' => [
        'array' => 'Поле ":attribute" має містити :size елементів.',
        'file' => 'Файл ":attribute" має бути розміром :size КБ.',
        'numeric' => 'Поле ":attribute" має дорівнювати :size.',
        'string' => 'Поле ":attribute" має містити :size символів.',
    ],

    'custom' => [
        'image' => [
            'max' => 'Зображення не може бути більшим за 4 МБ.',
            'mimes' => 'Зображення має бути у форматі JPG, PNG або WebP.',
        ],
        'images.*' => [
            'max' => 'Кожне фото товару не може бути більшим за 6 МБ.',
            'mimes' => 'Фото товару має бути у форматі JPG, PNG або WebP.',
        ],
        'mobile_image' => [
            'max' => 'Мобільне зображення не може бути більшим за 4 МБ.',
            'mimes' => 'Мобільне зображення має бути у форматі JPG, PNG або WebP.',
        ],
    ],

    'attributes' => [
        'name' => 'назва',
        'title' => 'заголовок',
        'slug' => 'slug',
        'description' => 'опис',
        'image' => 'зображення',
        'images' => 'фото товару',
        'images.*' => 'фото товару',
        'mobile_image' => 'мобільне зображення',
        'delete_image' => 'видалити зображення',
        'delete_mobile_image' => 'видалити мобільне зображення',
        'parent_id' => 'батьківська категорія',
        'primary_category_id' => 'основна категорія',
        'category_ids' => 'категорії',
        'filter_attribute_ids' => 'фільтри категорії',
        'filter_attribute_ids.*' => 'фільтр категорії',
        'sort_order' => 'порядок',
        'meta_title' => 'SEO title',
        'meta_description' => 'SEO description',
        'seo_text' => 'SEO текст',
        'price' => 'ціна',
        'old_price' => 'стара ціна',
        'cost_price' => 'собівартість',
        'sku' => 'SKU',
        'currency' => 'валюта',
        'status' => 'статус',
        'stock_status' => 'статус наявності',
    ],
];
