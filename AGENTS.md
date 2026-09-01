laravel react project - Self Hosted Google drive alternative - personal drive 
https://github.com/gyaaniguy/personal-drive/


During developement you can use browser to access and check the website here - do this sparingly , only if required or asked to. http://localhost:82/ user: aakthoo pass: asdasdasd

## Code Conventions

### PHP / Laravel

- **Thin controllers.** Validation in FormRequest, logic in services, controller wires them together.
- **Dependency injection.** Inject services via constructor or method signature. Avoid `app()` or `resolve()` in business code.
- **DRY.** If web and API do the same thing, extract to a service. No copy-pasted logic across controllers.
- **Named methods over closures.** Business logic belongs in named methods, not anonymous functions. Closures only for trivial transforms (array_map, collection filters).
- **Services return results, not exceptions.** For expected failures (file not found, ownership mismatch), return `['success' => bool, 'message' => string, ...]`. Reserve exceptions for truly exceptional cases (disk full, path traversal).
- **Models own their queries.** Static query helpers (`getById()`, `getByIds()`, `getFilesForPublicPath()`) live on the model. No raw query builder chains in controllers.
- **Shared validation rules** go in `CommonRequest` as static methods. Reuse across FormRequest classes.

### This App

- **Single-user.** Use `auth()->user()->id` to get the current user. No `$request->user()` parameter passing.
- **API responses.** `ResponseHelper::json($message, $status, $httpCode)` for simple success/error. Raw `response()->json()` only for data payloads (file lists, favorites, shares).
- **Web responses.** `FlashMessages` trait — `$this->success($msg)` / `$this->error($msg)`.
- **Paginated API.** `HasJsonPagination` trait — `$this->paginateJson($paginator, 'key')`.
- **FormRequest always.** Never validate in controller. API requests: `authorize()` returns `true` (auth is middleware).


