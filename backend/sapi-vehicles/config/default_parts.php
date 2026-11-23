<?php

/*
| Plano de peças por defeito (docs/data/data-model.md, secção 5).
| Usado ao criar um veículo (POST /vehicles) e no seeder de desenvolvimento.
| Todas as peças: 10 000 km, sem intervalo em meses, origem CATALOGO.
| last_service_km e last_service_date não estão aqui: são a km do veículo e a data de criação.
*/

$catalogo = [
    'interval_km' => 10000,
    'interval_months' => null,
    'interval_source' => 'CATALOGO',
];

return [

    'CARRO' => [
        ['name' => 'Óleo e filtro de óleo'] + $catalogo,
        ['name' => 'Filtro de ar'] + $catalogo,
        ['name' => 'Filtro de habitáculo'] + $catalogo,
        ['name' => 'Filtro de combustível'] + $catalogo,
        ['name' => 'Pastilhas de travão'] + $catalogo,
        ['name' => 'Pneus'] + $catalogo,
        ['name' => 'Velas de ignição'] + $catalogo,
    ],

    'MOTO' => [
        ['name' => 'Óleo e filtro de óleo'] + $catalogo,
        ['name' => 'Filtro de ar'] + $catalogo,
        ['name' => 'Pastilhas de travão'] + $catalogo,
        ['name' => 'Corrente de transmissão'] + $catalogo,
        ['name' => 'Pneus'] + $catalogo,
        ['name' => 'Velas de ignição'] + $catalogo,
    ],

];
