<?php

require_once('crest.php');

//1. Узнать количество контактов с заполненным полем COMMENTS
$with_comments = CRest::call(
    'crm.contact.list',
    [
        'FILTER' => [
            '!COMMENTS' => "",
        ],
    ]
        );


//2. Найти все сделки без контактов
$deals_without_contacts = Crest::Call(
    'crm.deal.list',
    [
        'FILTER' => ['CONTACT_ID' => "0"],
    ]
    );


// 3. Узнать сколько сделок в каждой из существующих Направлений
//Получение существующих Направлений
$deals_categories = Crest::Call(
    'crm.category.list',
    [
        'entityTypeId' => 2,
    ]
    );

// Подготовка параметров для batch запроса для каждого Направления
foreach ($deals_categories["result"]["categories"] as $key => $value) {
    $keys[] = "deals_in_cat_".$value["id"];
    $values[] = array(
            "method" => "crm.deal.list",
            "params" => [
                'FILTER' => ['CATEGORY_ID' => $value["id"]],
            ]
            );
}

sleep(1); //задержка навсякий случай

// Получение всех сделок по Направлениям
$deals_on_cats = CRest::callBatch(
    array_combine($keys, $values),
);

// Запись промежуточных результатов в массив $result
$result = array(
    'count_with_comments' => $with_comments["total"],
    'deals_without_contacts' => $deals_without_contacts["total"],

);
$result = array_merge($result, $deals_on_cats["result"]["result_total"]);


// 4. Посчитать сумму значений поля "Баллы" (предварительно узнав его код) из всех существующих элементов Смарт процесса

// Функция вычисления суммы всех значений поля Баллы
function getPointSums($array, $sum=0)
{

    array_walk_recursive($array, function($item, $key) use (&$sum) {
        if ($key == 'ufCrm5_1734072847') {
            $sum += $item;
        }
    });

    return $sum;
}

// Параметры запроса для получения поля Балы смарт-процесса с id 1038
$points_params = [
    'entityTypeId' => 1038, //смарт-процесс dynamic id 1038
    'select' => ['ufCrm5_1734072847'], // Поле Баллы
    'filter' => [
        '!ufCrm5_1734072847' => ''
    ]   
    ];

// Получение поля Баллы
$points_list = CRest::call(
    'crm.item.list',
    $points_params
);
// Получить сумму первых 50 записей поля Баллы
$points_sum = getPointSums($points_list);

// Получение количества страниц пагинаций
$points_pages = ceil($points_list['total']/50);

// Если количество страниц больше 1, то необходимо получить остальные данные по страницам
if ($points_pages > 1) {
    $point_keys=[];
    $point_values=[];
    $i = 1;

    // Подготовка параметров для batch запроса последующих страниц
    while ($i < $points_pages)
    {
        $i++; // Т.к первую страницу уже получили и вычислили сумму, то следует начать со 2-й страницы
        $point_keys[] = "points_" . $i;
        $point_values[] = array_merge(
            ['method' => 'crm.item.list'],
            ['params' => array_merge($points_params, [
            'start' => ($i-1) * 50
            ])]
        );
    }

    $points_params = array_combine($point_keys, $point_values);

    // Получение последующих страниц
    $points_list = CRest::callBatch($points_params);

    // Обновление суммы
    $points_sum = getPointSums($points_list, $points_sum);

}

$result = array_merge($result, ['points_sum' => $points_sum]);

print_r($result);