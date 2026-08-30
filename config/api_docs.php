<?php

return [
    'server_configs' => [
        [
            'name' => 'php-fpm',
            'instruction' => 'Edit the www.conf file',
            'code' => "php_value[upload_max_filesize] = 1G\nphp_value[post_max_size] = 1G\nphp_value[max_file_uploads] = 1000",
        ],
        [
            'name' => 'PHP',
            'instruction' => 'Edit 3 variables in php.ini file',
            'code' => "upload_max_filesize = 1G\npost_max_size = 1G\nmax_file_uploads = 10000",
        ],
        [
            'name' => 'apache',
            'instruction' => 'edit the .htaccess file in /public',
            'code' => "php_value upload_max_filesize 64M\nphp_value post_max_size 64M\nphp_value max_file_uploads 10000",
        ],
        [
            'name' => 'nginx',
            'instruction' => 'Increase client_max_body_size param',
            'code' => "http {\n    client_max_body_size 1000M;\n}",
        ],
        [
            'name' => 'Caddy',
            'instruction' => 'Increase request_timeout param',
            'code' => "demo.personaldrive.xyz {\n    root * /some/folder\n    php_fastcgi unix/{{ php_fpm_socket.stdout }}\n    file_server\n    request_body {\n        max_size 1G\n        timeout 1000s\n    }\n}",
        ],
    ],

    'api_endpoints' => [
        [
            'title' => 'Files',
            'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/files', 'description' => 'List files (paginated)', 'params' => 'path, per_page'],
                ['method' => 'GET', 'url' => '/api/v1/files/:id', 'description' => 'Get file info', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/files/upload', 'description' => 'Upload files', 'params' => 'files[], path'],
                ['method' => 'POST', 'url' => '/api/v1/files/create', 'description' => 'Create file/folder', 'params' => 'name, type, path'],
                ['method' => 'GET', 'url' => '/api/v1/files/:id/download', 'description' => 'Download file', 'params' => ''],
                ['method' => 'DELETE', 'url' => '/api/v1/files/:id', 'description' => 'Delete file', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/files/move', 'description' => 'Move files', 'params' => 'fileList[], destination'],
                ['method' => 'POST', 'url' => '/api/v1/files/:id/rename', 'description' => 'Rename file', 'params' => 'name'],
                ['method' => 'POST', 'url' => '/api/v1/files/:id/save', 'description' => 'Save file content', 'params' => 'content'],
            ],
        ],
        [
            'title' => 'Search',
            'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/search?q=...', 'description' => 'Search files (paginated)', 'params' => 'q'],
            ],
        ],
        [
            'title' => 'Favorites',
            'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/favorites', 'description' => 'List favorites (paginated)', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/favorites', 'description' => 'Add favorite', 'params' => 'local_file_ids[]'],
                ['method' => 'DELETE', 'url' => '/api/v1/favorites/:id', 'description' => 'Remove favorite', 'params' => ''],
            ],
        ],
        [
            'title' => 'Shares',
            'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/shares', 'description' => 'List shares (paginated)', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/shares', 'description' => 'Create share', 'params' => 'fileList[], slug?, password?, expiry?'],
                ['method' => 'DELETE', 'url' => '/api/v1/shares/:id', 'description' => 'Delete share', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/shares/:id/toggle', 'description' => 'Toggle share enabled/disabled', 'params' => ''],
            ],
        ],
    ],
];
