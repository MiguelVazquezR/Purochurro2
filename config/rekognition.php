<?php

return [
    'collection_id' => env('AWS_REKOGNITION_COLLECTION_ID', 'purochurro_employees'),
    'confidence_threshold' => 82, // Mínimo % de similitud para aceptar
];