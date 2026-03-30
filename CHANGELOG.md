# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.5] — 2026-03-30

### Fixed

- DPoP proof `htu` (step 10) accepts URLs canonically equivalent to `Request::fullUrl()` (e.g. differing GET query parameter order), matching Symfony/Laravel query normalization.

## [1.0.4] — 2026-03-30

### Added

- Add extra claims argument for Token Data to implement extra payload code inside the generated token 

## [1.0.3] — 2026-03-23

### Added

- Fixed firebase vurnerable version from 6x to 7x

## [1.0.2] — 2026-03-23

### Added

- Fixed composer requirements

## [1.0.1] — 2026-03-23

### Added

- PHP8.5 and Laravel 13 support added

## [1.0.0] — 2026-03-23

### Added

- RFC 9449 DPoP implementation with all 13 verification steps
- EC P-256 key pair binding via JWK thumbprint (RFC 7638)
- `POST /api/dpop/token` token endpoint with `Cache-Control: no-store`
- `dpop` middleware — runs `CheckOrigin → VerifyProof → CheckScope` pipeline
- `dpop.idempotency` middleware — deduplication for unsafe HTTP methods
- `dpop:install` Artisan command — interactive setup, writes `.env`, publishes config
- Anti-replay JTI store backed by Laravel cache (`dpop:jti:{jti}`)
- Idempotency cache store (`dpop:idempotency:{key}`)
- Scope enforcement via `dpop:scope1,scope2` middleware parameter
- Configurable clock skew, JTI TTL, proof header name, allowed origins, cache store
- Private key `d` in JWK rejected with `422` (`D.E.14`)
- Full error code catalogue: `D.E.1` – `D.E.14`, `C.O.1`, `S.1`, `E.I.1`, `E.I.2`
- 49 tests, 103 assertions covering all 13 DPoP steps individually

[1.0.0]: https://github.com/labrodev/laravel-dpop/releases/tag/v1.0.0
