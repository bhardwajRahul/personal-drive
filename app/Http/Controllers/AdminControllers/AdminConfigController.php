<?php

namespace App\Http\Controllers\AdminControllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\AdminConfigRequest;
use App\Http\Requests\AdminRequests\TwoFactorCodeCheckRequest;
use App\Models\LocalFile;
use App\Models\Setting;
use App\Services\AdminConfigService;
use App\Services\LocalFileStatsService;
use App\Services\TwoFactorService;
use App\Traits\FlashMessages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class AdminConfigController extends Controller
{
    use FlashMessages;

    protected AdminConfigService $adminConfigService;
    protected TwoFactorService $twoFactorService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        AdminConfigService    $adminConfigService,
        LocalFileStatsService $localFileStatsService,
        TwoFactorService      $twoFactorService
    )
    {
        $this->adminConfigService = $adminConfigService;
        $this->localFileStatsService = $localFileStatsService;
        $this->twoFactorService = $twoFactorService;
    }

    public function index(Request $request): Response
    {
        $setupMode = (bool)$request->query('setupMode');
        $storagePath = Setting::getStoragePath();
        $twoFactorStatus = $this->twoFactorService->getStatus();
        $show_two_factor_option = !config('app.disable_auth');

        $user = Auth::user();
        $tokens = $user->tokens()
            ->select('id', 'name', 'created_at', 'last_used_at', 'abilities')
            ->get();

        $server_configs = [
            ['name' => 'php-fpm', 'instruction' => 'Edit the www.conf file', 'code' => "php_value[upload_max_filesize] = 1G\nphp_value[post_max_size] = 1G\nphp_value[max_file_uploads] = 1000"],
            ['name' => 'PHP', 'instruction' => 'Edit 3 variables in php.ini file', 'code' => "upload_max_filesize = 1G\npost_max_size = 1G\nmax_file_uploads = 10000"],
            ['name' => 'apache', 'instruction' => 'edit the .htaccess file in /public', 'code' => "php_value upload_max_filesize 64M\nphp_value post_max_size 64M\nphp_value max_file_uploads 10000"],
            ['name' => 'nginx', 'instruction' => 'Increase client_max_body_size param', 'code' => "http {\n    client_max_body_size 1000M;\n}"],
            ['name' => 'Caddy', 'instruction' => 'Increase request_timeout param', 'code' => "demo.personaldrive.xyz {\n    root * /some/folder\n    php_fastcgi unix/{{ php_fpm_socket.stdout }}\n    file_server\n    request_body {\n        max_size 1G\n        timeout 1000s\n    }\n}"],
        ];

        $api_endpoints = [
            ['title' => 'Files', 'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/files', 'description' => 'List files (paginated)', 'params' => 'path, per_page'],
                ['method' => 'GET', 'url' => '/api/v1/files/:id', 'description' => 'Get file info', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/files/upload', 'description' => 'Upload files', 'params' => 'files[], path'],
                ['method' => 'POST', 'url' => '/api/v1/files/create', 'description' => 'Create file/folder', 'params' => 'name, type, path'],
                ['method' => 'GET', 'url' => '/api/v1/files/:id/download', 'description' => 'Download file', 'params' => ''],
                ['method' => 'DELETE', 'url' => '/api/v1/files/:id', 'description' => 'Delete file', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/files/move', 'description' => 'Move files', 'params' => 'fileList[], destination'],
                ['method' => 'POST', 'url' => '/api/v1/files/:id/rename', 'description' => 'Rename file', 'params' => 'name'],
                ['method' => 'POST', 'url' => '/api/v1/files/:id/save', 'description' => 'Save file content', 'params' => 'content'],
            ]],
            ['title' => 'Search', 'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/search?q=...', 'description' => 'Search files (paginated)', 'params' => 'q'],
            ]],
            ['title' => 'Favorites', 'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/favorites', 'description' => 'List favorites (paginated)', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/favorites', 'description' => 'Add favorite', 'params' => 'local_file_ids[]'],
                ['method' => 'DELETE', 'url' => '/api/v1/favorites/:id', 'description' => 'Remove favorite', 'params' => ''],
            ]],
            ['title' => 'Shares', 'endpoints' => [
                ['method' => 'GET', 'url' => '/api/v1/shares', 'description' => 'List shares (paginated)', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/shares', 'description' => 'Create share', 'params' => 'fileList[], slug?, password?, expiry?'],
                ['method' => 'DELETE', 'url' => '/api/v1/shares/:id', 'description' => 'Delete share', 'params' => ''],
                ['method' => 'POST', 'url' => '/api/v1/shares/:id/toggle', 'description' => 'Toggle share enabled/disabled', 'params' => ''],
            ]],
        ];

        return Inertia::render(
            'Admin/Settings',
            [
                'storage_path' => $storagePath,
                'php_max_upload_size' => $this->adminConfigService->getPhpUploadMaxFilesize(),
                'php_post_max_size' => $this->adminConfigService->getPhpPostMaxSize(),
                'php_max_file_uploads' => $this->adminConfigService->getPhpMaxFileUploads(),
                'setupMode' => $setupMode,
                'twoFactorStatus' => $twoFactorStatus,
                'show_two_factor_option' => $show_two_factor_option,
                'tokens' => $tokens,
                'server_configs' => $server_configs,
                'api_endpoints' => $api_endpoints,
                'flash' => [
                    'plain_text_token' => session('plain_text_token'),
                    'token_name' => session('token_name'),
                ],
            ]
        );
    }

    public function update(AdminConfigRequest $request): RedirectResponse
    {
        $storagePath = $request->validated('storage_path');
        $storagePath = trim(rtrim($storagePath, '/'));
        try {
            $updateStoragePathRes = DB::transaction(function () use ($storagePath): array {
                $result = $this->adminConfigService->updateStoragePath($storagePath);
                if ($result['status']) {
                    LocalFile::clearTable();
                    $this->localFileStatsService->generateStats();
                }
                return $result;
            });
        } catch (UnexpectedValueException) {
            return $this->error(
                'Storage scan failed because a file or folder cannot be accessed. Check its permissions and try again.'
            );
        }
        session()->flash('message', $updateStoragePathRes['message']);
        session()->flash('status', $updateStoragePathRes['status']);
        if ($updateStoragePathRes['status']) {
            return redirect()->route('drive');
        }

        return redirect()->back();
    }

    public function twoFactorGetQr(): JsonResponse
    {
        if ($this->twoFactorService->isTwoFactorEnabled()) {
            $twoFactorSecret = $this->twoFactorService->getSecret();
        } else {
            $twoFactorSecret = $this->twoFactorService->generateTwoFactorSecret();
            $this->twoFactorService->setSecret($twoFactorSecret);
        }
        $qrCodeSvgStr = $this->twoFactorService->generateQr($twoFactorSecret);
        return ResponseHelper::json($qrCodeSvgStr);
    }

    public function twoFactorCodeEnable(TwoFactorCodeCheckRequest $request): RedirectResponse
    {
        if ($this->twoFactorService->isTwoFactorEnabled()) {
            return $this->error('Two Factor is already enabled');
        }
        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $this->twoFactorService->getSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);
        if ($isVerified) {
            $this->twoFactorService->setStatus(true);
            return $this->success('Two Factor Authentication Enabled');
        }
        return $this->error('Incorrect OTP. Please try again');
    }

    public function twoFactorCodeDisable(TwoFactorCodeCheckRequest $request): RedirectResponse
    {
        if (!$this->twoFactorService->isTwoFactorEnabled()) {
            return $this->error('Two Factor is already disabled');
        }
        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $this->twoFactorService->getSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);
        if ($isVerified) {
            $this->twoFactorService->setStatus(false);
            return $this->success('Two Factor Authentication Disabled');
        }
        return $this->error('Incorrect OTP. Please try again');
    }
}
