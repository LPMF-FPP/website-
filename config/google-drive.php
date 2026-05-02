<?php

return [
    'application_name' => env('GOOGLE_DRIVE_APPLICATION_NAME', env('APP_NAME', 'LPMF LIMS')),
    'api_base_url' => env('GOOGLE_DRIVE_API_BASE_URL', 'https://www.googleapis.com'),
    'auth_client_id' => env('GOOGLE_DRIVE_AUTH_CLIENT_ID'),
    'auth_client_secret' => env('GOOGLE_DRIVE_AUTH_CLIENT_SECRET'),
    'auth_redirect_uri' => env('GOOGLE_DRIVE_AUTH_REDIRECT_URI'),
    'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
    'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    'uploads_folder_name' => env('GOOGLE_DRIVE_UPLOADS_FOLDER_NAME', 'LPMF LIMS Uploads'),
    'impersonate_user' => env('GOOGLE_DRIVE_IMPERSONATE_USER'),
    'supports_all_drives' => env('GOOGLE_DRIVE_SUPPORTS_ALL_DRIVES', false),
    'scope' => env('GOOGLE_DRIVE_SCOPE', 'https://www.googleapis.com/auth/drive.file'),
];
