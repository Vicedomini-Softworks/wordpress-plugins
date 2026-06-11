# OpenProvider WooCommerce - API Architecture

## Overview

This document describes the architecture of the OpenProvider WooCommerce plugin's API integration layer.

## Service Layer

### AuthService

Handles OpenProvider API authentication and token management.

**Key Methods:**
- `get_token()`: Returns cached token or performs fresh login
- `login()`: POSTs to `/v1beta/auth/login` and caches the result
- `invalidate_token()`: Clears cached token (used on 401 errors)

**Token Caching:**
- Stored in WordPress transient `opwc_auth_token`
- TTL = `expires_in - 60` seconds (default 3300s = 55 minutes)
- Automatically refreshed before expiry

### DomainService

Handles domain availability checks and registration.

**Key Methods:**
- `check_bulk(array $domains)`: Checks multiple domains at once
- `check(string $name, string $extension)`: Single domain check
- `register(array $data)`: Registers a domain

**Retry Logic:**
- On 401 error, invalidates token and retries once

### PricingService

Handles domain pricing retrieval.

**Key Methods:**
- `get_price(string $name, string $extension, string $operation, int $period)`: Gets pricing for a domain

**Note:** This service is cache-agnostic. Callers (REST controllers) handle transient caching.

## Response Mapping Isolation

Each service has private `map_*_response()` methods that isolate raw OpenProvider field names. This allows easy correction when sandbox testing reveals different field names than expected.

Example:
```php
private function map_login_response(array $raw): array {
    $token = $raw['data']['token'] ?? $raw['token'] ?? '';
    // Handle API variations
}
```

## Caching Strategy

| Cache Type | Key Prefix | TTL | Location |
|------------|------------|-----|----------|
| Auth Token | `opwc_auth_token` | expires_in - 60s | Transient |
| Search Results | `opwc_search_*` | 5 min (configurable) | Transient |
| Pricing | `opwc_price_*` | 12 hours (configurable) | Transient |

## Rate Limiting

Public REST endpoints are rate-limited per IP using transient counters:

| Endpoint | Limit | Window |
|----------|-------|--------|
| `/search` | 30 req/min | 60s |
| `/check` | 20 req/min | 60s |
| `/cart/add` | 10 req/min | 60s |
| `/pricing` | 60 req/min | 60s |

## Premium Domain Markup Formula

```php
$marked_up = $base_price * (1 + markup_percent / 100);
$capped = min($marked_up, $base_price * (1 + cap_percent / 100));
$final = round_up($capped, rounding_mode);
```

Rounding modes:
- `nearest_99`: Round up to nearest .99
- `nearest_1`: Round up to nearest whole number
- `nearest_5`: Round up to nearest .95 or .00
