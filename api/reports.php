<?php
require_once __DIR__ . '/../app/bootstrap.php';

function report_period(): array
{
    return [query_date('date_from', date('Y-m-01')), query_date('date_to', date('Y-m-d'))];
}

$handlers = [
    'sales' => function () {
        list($from, $to) = report_period();
        return ['rows' => ReportRepository::sales($from, $to)];
    },
    'profit' => function () {
        list($from, $to) = report_period();
        return [
            'rows'       => ReportRepository::profit($from, $to),
            'top'        => ReportRepository::topParts($from, $to, 10),
            'categories' => ReportRepository::revenueByCategory($from, $to),
        ];
    },
    'stock' => function () {
        return ['rows' => ReportRepository::stock()];
    },
];

api_dispatch($handlers);
