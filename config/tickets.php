<?php
return [
    'close_reasons' => array_values(array_filter(
        array_map('trim', explode(';', env('CLOSE_REASONS', '')))
    )),
];