<?php
return [
    'upload_max_bytes'          => 2097152, // 2 MB
    'upload_allowed_doc_mimes'  => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'upload_allowed_photo_mimes' => [
        'image/jpeg',
        'image/png',
    ],
    'upload_path' => 'storage/uploads/students/',
];
