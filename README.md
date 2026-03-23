# Laravel DPoP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/labrodev/dpop.svg?style=flat-square)](https://packagist.org/packages/labrodev/dpop)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue?style=flat-square)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E12.0-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/labrodev/laravel-dpop?style=flat-square)](LICENSE)

RFC 9449 **Demonstration of Proof-of-Possession (DPoP)** for Laravel. Issues EC P-256-bound JWTs via a built-in token endpoint and verifies DPoP proofs on protected routes via middleware.

---

## What is DPoP?

DPoP ([RFC 9449](https://www.rfc-editor.org/rfc/rfc9449)) is an application-level mechanism for binding access tokens to a client's public key. Each request carries a short-lived, single-use proof-of-possession JWT signed with the client's private key. Even if a bearer token is stolen, it cannot be used without the corresponding private key.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.4` |
| Laravel | `^12.0` |
| `firebase/php-jwt` | `^6.0` |
| `web-token/jwt-library` | `^3.4` |
| `spatie/laravel-data` | `^4.0` |

---

## Installation

```bash
composer require labrodev/dpop
```

Run the interactive installer:

```bash
php artisan dpop:install
```

This writes all `DPOP_*` environment variables to `.env` and publishes the config file.

### Manual installation

Publish the config:

```bash
php artisan vendor:publish --provider="Labrodev\Dpop\DpopServiceProvider" --tag="dpop-config"
```

Add the required environment variables to your `.env`:

```env
DPOP_JWT_SECRET=your-64-char-secret
DPOP_JWT_ALGORITHM=HS256
DPOP_JWT_LIFETIME=3600
DPOP_CLOCK_SKEW=30
DPOP_PROOF_HEADER=DPoP
DPOP_ALLOWED_ORIGINS=
DPOP_TOKEN_ROUTE=api/dpop/token
DPOP_CACHE_STORE=
DPOP_JTI_TTL=600
```

---

## Configuration

After publishing, edit `config/dpop.php`:

```php
return [
    'jwt' => [
        'secret'    => env('DPOP_JWT_SECRET'),
        'algorithm' => env('DPOP_JWT_ALGORITHM', 'HS256'),
        'lifetime'  => env('DPOP_JWT_LIFETIME', 3600),
    ],

    // Acceptable clock skew in seconds for DPoP proof iat validation
    'clock_skew' => env('DPOP_CLOCK_SKEW', 30),

    // Cache store for JTI anti-replay and idempotency (null = app default)
    'cache_store' => env('DPOP_CACHE_STORE'),

    // How long a used JTI is retained to detect replays (seconds)
    'jti_ttl' => env('DPOP_JTI_TTL', 600),

    // Header name carrying the DPoP proof (default: DPoP)
    'proof_header' => env('DPOP_PROOF_HEADER', 'DPoP'),

    // Comma-separated list of allowed Origin values (empty = allow all)
    'allowed_origins' => explode(',', env('DPOP_ALLOWED_ORIGINS', '')),

    // Route URI for the token endpoint (null or empty = disabled)
    'token_route' => env('DPOP_TOKEN_ROUTE', 'api/dpop/token'),
];
```

---

## Token Endpoint

A `POST` endpoint is registered automatically at the URI defined in `dpop.token_route` (default: `POST /api/dpop/token`).

### Request

```http
POST /api/dpop/token
Content-Type: application/json

{
    "jwk": {
        "kty": "EC",
        "crv": "P-256",
        "x": "<base64url-encoded x>",
        "y": "<base64url-encoded y>"
    },
    "scope": "read write"
}
```

The `jwk` must be an EC P-256 **public** key. Including the private key component `d` will return a `422`.

### Response

```json
{
    "data": {
        "type": "token",
        "attributes": {
            "token": "<signed-jwt>",
            "expires_in": 3600
        }
    }
}
```

The response always includes `Cache-Control: no-store`.

### Issued JWT claims

| Claim | Value |
|---|---|
| `iss` | `config('app.url')` |
| `sub` | JWK thumbprint (RFC 7638) |
| `jkt` | JWK thumbprint (RFC 7638) |
| `scp` | Array of requested scopes |
| `iat` | Issued-at timestamp |
| `exp` | `iat + dpop.jwt.lifetime` |

---

## Protecting Routes

Apply the `dpop` middleware to any route or route group:

```php
// Single route
Route::get('/api/resource', ResourceController::class)
    ->middleware('dpop');

// With required scopes
Route::post('/api/orders', OrderStoreController::class)
    ->middleware('dpop:write');

// Multiple required scopes (all must be present)
Route::delete('/api/orders/{id}', OrderDeleteController::class)
    ->middleware('dpop:write,admin');

// Route group
Route::middleware('dpop:read')->group(function () {
    Route::get('/api/profile', ProfileController::class);
    Route::get('/api/orders', OrderIndexController::class);
});
```

### Accessing the verified token

After the middleware passes, the decoded JWT payload is available from the request:

```php
$jwt = $request->attributes->get('dpop_jwt');
$scopes = $jwt['scp'] ?? [];
$subject = $jwt['sub'];
```

---

## Idempotency Middleware

The package ships an optional `dpop.idempotency` middleware for unsafe HTTP methods (POST, PUT, PATCH, DELETE).

```php
Route::post('/api/payments', PaymentStoreController::class)
    ->middleware(['dpop', 'dpop.idempotency']);
```

Clients must send an `Idempotency-Key` header (UUID format):

```http
POST /api/payments
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

| Scenario | Response |
|---|---|
| First request | Normal response |
| Replay with same body | Cached response + `Idempotency-Replayed: true` header |
| Replay with different body | `409 Conflict` + `{"error": "E.I.2"}` |
| Missing / invalid key | `422 Unprocessable` + `{"error": "E.I.1"}` |

---

## Error Codes

All errors return JSON `{"error": "<code>"}` with the appropriate HTTP status.

| Code | Step | HTTP | Description |
|---|---|---|---|
| `D.E.1` | 1 | 401 | Missing or non-Bearer Authorization header |
| `D.E.2` | 2 | 401 | Invalid JWT signature |
| `D.E.3` | 3 | 401 | JWT expired or missing `exp` claim |
| `D.E.4` | 4 | 401 | Missing `jkt` claim in JWT |
| `D.E.5` | 5 | 401 | Missing DPoP proof header |
| `D.E.6` | 6 | 401 | DPoP proof `typ` is not `dpop+jwt` |
| `D.E.7` | 7 | 401 | DPoP proof `alg` is not `ES256` or key is not EC P-256 |
| `D.E.8` | 8 | 401 | DPoP proof JWS cryptographic signature invalid |
| `D.E.9` | 9 | 401 | `htm` does not match request method |
| `D.E.10` | 10 | 401 | `htu` does not match request URL |
| `D.E.11` | 11 | 401 | `iat` outside acceptable clock skew |
| `D.E.12` | 12 | 401 | `jti` replayed (anti-replay) |
| `D.E.13` | 13 | 401 | JWK thumbprint does not match `jkt` claim |
| `D.E.14` | — | 422 | JWK contains private key `d` |
| `C.O.1` | — | 401 | Origin not in allowed origins list |
| `S.1` | — | 401 | Required scope not present in token |
| `E.I.1` | — | 422 | Missing or invalid `Idempotency-Key` |
| `E.I.2` | — | 409 | Idempotency key reused with different request body |

---

## Client Example

A minimal JavaScript client using the Web Crypto API:

```js
// Generate an EC P-256 key pair
const keyPair = await crypto.subtle.generateKey(
    { name: 'ECDSA', namedCurve: 'P-256' },
    true,
    ['sign', 'verify'],
);

const publicJwk = await crypto.subtle.exportKey('jwk', keyPair.publicKey);

// 1. Obtain a DPoP-bound token
const tokenRes = await fetch('/api/dpop/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ jwk: publicJwk, scope: 'read' }),
});
const { data: { attributes: { token } } } = await tokenRes.json();

// 2. Build a DPoP proof for each request
async function buildProof(method, url) {
    const header = { alg: 'ES256', typ: 'dpop+jwt', jwk: publicJwk };
    const payload = { htm: method, htu: url, iat: Math.floor(Date.now() / 1000), jti: crypto.randomUUID() };
    const enc = (obj) => btoa(JSON.stringify(obj)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    const data = enc(header) + '.' + enc(payload);
    const sig = await crypto.subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, keyPair.privateKey, new TextEncoder().encode(data));
    const sigB64 = btoa(String.fromCharCode(...new Uint8Array(sig))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    return data + '.' + sigB64;
}

// 3. Make a protected request
const proof = await buildProof('GET', 'https://your-app.com/api/resource');
const res = await fetch('/api/resource', {
    headers: { 'Authorization': `Bearer ${token}`, 'DPoP': proof },
});
```

---

## Development

```bash
composer install
composer test          # run PHPUnit
composer pint          # fix code style
composer pint:test     # check code style without fixing
composer phpstan       # static analysis (level 8)
composer ci            # test + pint:test + phpstan
```

---

## License

MIT — see [LICENSE](LICENSE).
